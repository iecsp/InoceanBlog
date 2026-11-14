<?php declare(strict_types=1);

namespace Inocean\Blog\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Inocean\Blog\Content\{PostDefinition, PostTranslationDefinition};
use Shopware\Core\Content\Seo\SeoUrlUpdater;
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
