<?php declare(strict_types=1);

namespace Inocean\Blog\Seo;

use Inocean\Blog\Content\{PostDefinition, PublishedPosts};
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Content\Sitemap\Struct\{Url, UrlResult};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class BlogSitemapProvider extends AbstractUrlProvider
{
    public function __construct(private readonly PublishedPosts $published)
    {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return PostDefinition::ENTITY_NAME;
    }

    public function getUrls(SalesChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $criteria = $this->published->criteria($context, true)
            ->addSorting(new FieldSorting('id'))
            ->setOffset($offset ?? 0)
            ->setLimit($limit);
        $posts = $this->published->repository()->search($criteria, $context->getContext())->getEntities();
        $paths = $this->published->canonicalPaths($posts->getIds(), $context);
        $urls = [];
        foreach ($posts as $post) {
            $id = $post->getUniqueIdentifier();
            if (!isset($paths[$id])) {
                continue;
            }

            $url = new Url();
            $url->setLoc($paths[$id]);
            $url->setLastmod($post->get('updatedAt') ?? $post->get('createdAt'));
            $url->setChangefreq('weekly');
            $url->setResource(PostDefinition::ENTITY_NAME);
            $url->setIdentifier($id);
            $urls[] = $url;
        }

        return new UrlResult($urls, $posts->count() === $limit ? ($offset ?? 0) + $limit : null);
    }
}
