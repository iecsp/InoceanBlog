<?php declare(strict_types=1);

namespace Inocean\Blog\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Inocean\Blog\Content\{PostDefinition, PostTranslationDefinition};
use Shopware\Core\Content\Seo\{ConfiguredEntitySeoUrlRoute, SeoUrlGenerator, SeoUrlPersister, SeoUrlUpdater};
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\{EntityIndexer, EntityIndexingMessage};
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

final class BlogSeoIndexer extends EntityIndexer
{
    public function __construct(
        private readonly IteratorFactory $iterators,
        private readonly SeoUrlUpdater $updater,
        private readonly Connection $connection,
        private readonly BlogSeoRoute $route,
        private readonly SeoUrlGenerator $generator,
        private readonly SeoUrlPersister $persister,
        private readonly EntityRepository $salesChannels,
    ) {
    }

    public function getName(): string
    {
        return 'ino_blog_post.seo-url';
    }

    public function iterate(?array $offset): ?EntityIndexingMessage
    {
        $iterator = $this->iterators->createIterator(PostDefinition::ENTITY_NAME, $offset);
        $iterator->getQuery()->addOrderBy('ino_blog_post.id');
        $ids = array_values($iterator->fetch());

        return $ids === [] ? null : new EntityIndexingMessage($ids, $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $ids = $event->getPrimaryKeys(PostDefinition::ENTITY_NAME);
        foreach ($event->getPrimaryKeys(PostTranslationDefinition::ENTITY_NAME) as $key) {
            if (isset($key['inoBlogPostId'])) {
                $ids[] = $key['inoBlogPostId'];
            }
        }

        return $ids === [] ? null : new EntityIndexingMessage(array_values(array_unique($ids)), null, $event->getContext());
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();
        if (!\is_array($ids) || $ids === []) {
            return;
        }

        // A channel reassignment or deletion must also retire paths from previous channels.
        $this->connection->executeStatement(<<<'SQL'
            UPDATE seo_url AS url
            LEFT JOIN ino_blog_post AS post ON post.id = url.foreign_key
            SET url.is_deleted = 1
            WHERE url.route_name = :route AND url.foreign_key IN (:ids)
              AND (post.id IS NULL OR post.active = 0 OR post.published_at IS NULL
                   OR NOT (post.sales_channel_id <=> url.sales_channel_id))
            SQL, [
            'route' => BlogSeoRoute::NAME,
            'ids' => Uuid::fromHexToBytesList($ids),
        ], ['ids' => ArrayParameterType::BINARY]);

        $this->updater->update(BlogSeoRoute::NAME, $ids);
        $this->indexLanguagesWithoutExternalDomains($ids);
    }

    private function indexLanguagesWithoutExternalDomains(array $ids): void
    {
        // Shopware's headless updater only visits external storefront domains.
        $languages = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT LOWER(HEX(channel.id)) AS channelId, LOWER(HEX(language.id)) AS languageId,
                   LOWER(HEX(language.parent_id)) AS parentId,
                   COALESCE(custom.template, fallback.template) AS template
            FROM sales_channel channel
            JOIN sales_channel_language supported ON supported.sales_channel_id = channel.id
            JOIN language ON language.id = supported.language_id
            JOIN seo_url_template fallback
                ON fallback.route_name = :route AND fallback.sales_channel_id IS NULL AND fallback.is_headless = 1
            LEFT JOIN seo_url_template custom
                ON custom.route_name = :route AND custom.sales_channel_id = channel.id AND custom.is_headless = 1
            WHERE channel.active = 1 AND channel.type_id = :headless
              AND EXISTS (SELECT 1 FROM ino_blog_post post WHERE post.sales_channel_id = channel.id AND post.id IN (:ids))
              AND NOT EXISTS (
                  SELECT 1 FROM sales_channel_domain domain
                  WHERE domain.sales_channel_id = channel.id AND domain.language_id = language.id
                    AND domain.is_external_storefront = 1
              )
            SQL, [
            'route' => BlogSeoRoute::NAME,
            'headless' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_API),
            'ids' => Uuid::fromHexToBytesList($ids),
        ], ['ids' => ArrayParameterType::BINARY]);

        if ($languages === []) {
            return;
        }

        $channels = $this->salesChannels->search(
            new Criteria(array_values(array_unique(array_column($languages, 'channelId')))),
            Context::createDefaultContext(),
        )->getEntities();
        $route = new ConfiguredEntitySeoUrlRoute($this->route);
        foreach ($languages as $language) {
            $channel = $channels->get($language['channelId']);
            if ($channel === null || $language['template'] === '') {
                continue;
            }

            $chain = array_values(array_unique(array_filter([
                $language['languageId'], $language['parentId'], Defaults::LANGUAGE_SYSTEM,
            ])));
            $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);
            $urls = $this->generator->generate($ids, $language['template'], $route, $context, $channel);
            $this->persister->updateSeoUrls($context, BlogSeoRoute::NAME, $ids, $urls, $channel);
        }
    }

    public function getTotal(): int
    {
        return $this->iterators->createIterator(PostDefinition::ENTITY_NAME)->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(self::class);
    }
}
