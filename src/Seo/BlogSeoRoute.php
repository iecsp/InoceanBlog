<?php declare(strict_types=1);

namespace Inocean\Blog\Seo;

use Inocean\Blog\Content\PostDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\{EntitySeoUrlRouteInterface, SeoUrlRouteConfig};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, NotFilter};
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final class BlogSeoRoute implements EntitySeoUrlRouteInterface
{
    public const NAME = 'store-api.ino-blog.post.detail';
    public const TEMPLATE = 'blog/{{ inoBlogPost.translated.slug ?: inoBlogPost.translated.title|slice(0, 100) }}-{{ inoBlogPost.id }}';

    public function __construct(private readonly PostDefinition $definition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig($this->definition, self::NAME, self::TEMPLATE, true, 'postId');
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(
            new EqualsFilter('active', true),
            new EqualsFilter('salesChannelId', $salesChannel->getId()),
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('publishedAt', null)]),
        );
    }
}
