<?php declare(strict_types=1);

namespace Inocean\Blog\Content;

use Inocean\Blog\Seo\BlogSeoRoute;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, EqualsAnyFilter, RangeFilter};
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class PublishedPosts
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct(
        private readonly EntityRepository $posts,
        private readonly EntityRepository $seoUrls,
    ) {
        // Keep the public HTML allowlist active even when the shop disables its DAL sanitizer.
        $this->sanitizer = new HtmlSanitizer(cacheEnabled: false);
    }

    public function criteria(SalesChannelContext $context, bool $indexableOnly = false, string $prefix = ''): Criteria
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter($prefix . 'salesChannelId', $context->getSalesChannelId()),
            new EqualsFilter($prefix . 'active', true),
            new RangeFilter($prefix . 'publishedAt', [RangeFilter::LTE => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT)]),
        );
        if ($indexableOnly) {
            $criteria->addFilter(new EqualsFilter($prefix . 'indexable', true));
        }

        return $criteria;
    }

    public function canonicalPaths(array $ids, SalesChannelContext $context): array
    {
        if ($ids === []) {
            return [];
        }

        $criteria = (new Criteria())->addFilter(
            new EqualsAnyFilter('foreignKey', $ids),
            new EqualsFilter('routeName', BlogSeoRoute::NAME),
            new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            new EqualsFilter('languageId', $context->getLanguageId()),
            new EqualsFilter('isCanonical', true),
            new EqualsFilter('isDeleted', false),
        );

        $paths = [];
        foreach ($this->seoUrls->search($criteria, $context->getContext())->getEntities() as $url) {
            $paths[$url->getForeignKey()] = $url->getSeoPathInfo();
        }

        return $paths;
    }

    public function resolvePath(string $path, SalesChannelContext $context): ?string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));
        $criteria = (new Criteria())->addFilter(
            new EqualsAnyFilter('seoPathInfo', array_unique([$path, $encoded])),
            new EqualsFilter('routeName', BlogSeoRoute::NAME),
            new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            new EqualsFilter('isDeleted', false),
        );

        $matches = $this->seoUrls->search($criteria, $context->getContext())->getEntities();
        $ids = [];
        foreach ($matches as $url) {
            if ($url->getLanguageId() === $context->getLanguageId()) {
                return $url->getForeignKey();
            }
            $ids[$url->getForeignKey()] = true;
        }

        // A language switch may carry the previous language's path. Resolve it
        // only when it unambiguously identifies the same article in this channel.
        return count($ids) === 1 ? array_key_first($ids) : null;
    }

    public function domain(SalesChannelContext $context): ?string
    {
        $domains = $context->getSalesChannel()->getDomains();
        if ($domains === null) {
            return null;
        }

        $candidates = [];
        foreach ($domains as $domain) {
            if ($domain->getIsExternalStorefront() && $domain->getLanguageId() === $context->getLanguageId()) {
                $candidates[$domain->getId()] = rtrim($domain->getUrl(), '/');
            }
        }

        ksort($candidates);

        return $candidates[$context->getDomainId()] ?? reset($candidates) ?: null;
    }

    public function repository(): EntityRepository
    {
        return $this->posts;
    }

    public function format(Entity $post, ?string $path, ?string $domain, bool $detail): array
    {
        $category = $post->get('category');
        $title = $this->plain($post->getTranslation('title'));
        $excerpt = $this->plain($post->getTranslation('excerpt'));
        $url = $path !== null && $domain !== null ? $domain . '/' . ltrim($path, '/') : null;
        $result = [
            'id' => $post->getUniqueIdentifier(),
            'title' => $title,
            'excerpt' => $excerpt,
            'author' => $this->plain($post->get('author')),
            'publishedAt' => $post->get('publishedAt')->format(\DATE_ATOM),
            'modifiedAt' => ($post->get('updatedAt') ?? $post->get('createdAt'))->format(\DATE_ATOM),
            'pinned' => $post->get('pinned'),
            'indexable' => $post->get('indexable'),
            'seoPath' => $path,
            'canonicalUrl' => $url,
            'metaTitle' => $this->plain($post->getTranslation('metaTitle')) ?: $title,
            'metaDescription' => $this->plain($post->getTranslation('metaDescription')) ?: $excerpt,
            'category' => $category === null ? null : [
                'id' => $category->getUniqueIdentifier(),
                'name' => $this->plain($category->getTranslation('name')),
            ],
            'tags' => array_values(array_map(
                fn (Entity $tag): array => ['id' => $tag->getUniqueIdentifier(), 'name' => $this->plain($tag->get('name'))],
                $post->get('tags')?->getElements() ?? [],
            )),
            'cover' => $this->media($post->get('cover'), 'image/'),
        ];

        if ($detail) {
            $result['video'] = $this->media($post->get('video'), 'video/');
            $result['content'] = $this->sanitizeContent((string) $post->getTranslation('content'));
            $result['text'] = $this->plain(preg_replace('~</(?:p|h[1-6]|li|tr|blockquote)>|<br\s*/?>~i', '$0 ', $result['content']));
        }

        return $result;
    }

    public function sanitizeContent(string $html): string
    {
        return $this->sanitizer->sanitize($html, [
            'p' => [], 'br' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
            'blockquote' => [], 'pre' => [], 'code' => [], 'b' => [], 'strong' => [], 'i' => [],
            'em' => [], 'u' => [], 's' => [], 'hr' => [], 'ul' => [], 'ol' => ['start'], 'li' => [],
            'a' => ['href', 'title'], 'img' => ['src', 'alt', 'title', 'width', 'height'],
            'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'],
            'td' => ['colspan', 'rowspan'],
        ], true);
    }

    public function plain(?string $value): string
    {
        return trim(html_entity_decode($this->sanitizer->stripTags($value ?? ''), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }

    private function media(?MediaEntity $media, string $mimePrefix): ?array
    {
        if ($media === null || $media->isPrivate() || !str_starts_with((string) $media->getMimeType(), $mimePrefix) || !$media->getUrl()) {
            return null;
        }

        return ['url' => $media->getUrl(), 'alt' => $media->getTranslation('alt'), 'mimeType' => $media->getMimeType()];
    }
}
