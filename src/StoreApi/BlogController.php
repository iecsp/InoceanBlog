<?php declare(strict_types=1);

namespace Inocean\Blog\StoreApi;

use Inocean\Blog\Content\PublishedPosts;
use Shopware\Core\Defaults;
use Inocean\Blog\Seo\BlogSeoRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, RangeFilter};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
final class BlogController
{
    private const ID = '[0-9a-fA-F]{32}';

    public function __construct(
        private readonly PublishedPosts $published,
        private readonly EntityRepository $categories,
    ) {
    }

    #[Route(path: '/store-api/ino-blog/posts', name: 'store-api.ino-blog.post.list', methods: ['GET'])]
    public function posts(Request $request, SalesChannelContext $context): JsonResponse
    {
        return new JsonResponse($this->listing($request, $context, false));
    }

    #[Route(path: '/store-api/ino-blog/posts/{postId}', name: BlogSeoRoute::NAME, requirements: ['postId' => self::ID], methods: ['GET'])]
    public function post(string $postId, SalesChannelContext $context): JsonResponse
    {
        $postId = strtolower($postId);
        $criteria = $this->published->criteria($context)
            ->setIds([$postId])
            ->addAssociations(['category', 'cover', 'video', 'tags']);
        $post = $this->published->repository()->search($criteria, $context->getContext())->getEntities()->first();
        if ($post === null) {
            throw new NotFoundHttpException('Blog post not found.');
        }

        $path = $this->published->canonicalPaths([$postId], $context)[$postId] ?? null;
        $data = $this->published->format($post, $path, $this->published->domain($context), true);
        $response = new JsonResponse($data);
        if (!$data['indexable']) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    #[Route(path: '/store-api/ino-blog/categories', name: 'store-api.ino-blog.category.list', methods: ['GET'])]
    public function categories(Request $request, SalesChannelContext $context): JsonResponse
    {
        [$page, $limit] = $this->pagination($request);
        $criteria = $this->published->criteria($context, prefix: 'posts.')
            ->addSorting(new FieldSorting('position'), new FieldSorting('name'), new FieldSorting('id'))
            ->setOffset(($page - 1) * $limit)
            ->setLimit($limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $result = $this->categories->search($criteria, $context->getContext());
        $elements = [];
        foreach ($result->getEntities() as $category) {
            $elements[] = [
                'id' => $category->getUniqueIdentifier(),
                'name' => $this->published->plain($category->getTranslation('name')),
                'description' => $this->published->plain($category->getTranslation('description')),
            ];
        }

        return new JsonResponse(['elements' => $elements, 'total' => $result->getTotal(), 'page' => $page, 'limit' => $limit]);
    }

    #[Route(path: '/store-api/ino-blog/index', name: 'store-api.ino-blog.index', methods: ['GET'])]
    public function index(Request $request, SalesChannelContext $context): JsonResponse
    {
        return new JsonResponse($this->listing($request, $context, true));
    }

    #[Route(path: '/store-api/ino-blog/feed.xml', name: 'store-api.ino-blog.feed', methods: ['GET'])]
    public function feed(Request $request, SalesChannelContext $context): Response
    {
        $domain = $this->published->domain($context);
        if ($domain === null) {
            throw new NotFoundHttpException('Configure an external storefront domain for this language.');
        }

        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->startElement('channel');
        $title = $this->published->plain($context->getSalesChannel()->getTranslation('name')) ?: 'Blog';
        $xml->writeElement('title', $title);
        $xml->writeElement('link', $domain);
        $xml->writeElement('description', $title);

        foreach ($this->listing($request, $context, true)['elements'] as $post) {
            if ($post['canonicalUrl'] === null) {
                continue;
            }

            $xml->startElement('item');
            $xml->writeElement('title', $post['title']);
            $xml->writeElement('link', $post['canonicalUrl']);
            $xml->writeElement('guid', $post['canonicalUrl']);
            $xml->writeElement('description', $post['excerpt']);
            $xml->writeElement('pubDate', (new \DateTimeImmutable($post['publishedAt']))->format(\DATE_RSS));
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return new Response($xml->outputMemory(), Response::HTTP_OK, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    private function listing(Request $request, SalesChannelContext $context, bool $indexable): array
    {
        [$page, $limit] = $this->pagination($request);
        $criteria = $this->published->criteria($context, $indexable)
            ->addAssociations($indexable ? ['category', 'cover', 'video', 'tags'] : ['category', 'cover', 'tags'])
            ->setOffset(($page - 1) * $limit)
            ->setLimit($limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $sort = $request->query->getString('sort', $indexable ? 'newest' : 'pinned');
        if (!\in_array($sort, ['pinned', 'newest', 'oldest'], true)) {
            throw new BadRequestHttpException('sort must be pinned, newest or oldest.');
        }
        if ($sort === 'pinned') {
            $criteria->addSorting(new FieldSorting('pinned', FieldSorting::DESCENDING));
        }
        $criteria->addSorting(
            new FieldSorting('publishedAt', $sort === 'oldest' ? FieldSorting::ASCENDING : FieldSorting::DESCENDING),
            new FieldSorting('id'),
        );
        foreach (['categoryId' => 'categoryId', 'tagId' => 'tags.id'] as $parameter => $field) {
            $id = $request->query->getString($parameter);
            if ($id !== '') {
                if (!preg_match('/^' . self::ID . '$/D', $id)) {
                    throw new BadRequestHttpException("Invalid $parameter.");
                }
                $criteria->addFilter(new EqualsFilter($field, strtolower($id)));
            }
        }

        $from = $this->date($request, 'publishedFrom');
        $to = $this->date($request, 'publishedTo');
        if ($from !== null && $to !== null && $from > $to) {
            throw new BadRequestHttpException('publishedFrom must be on or before publishedTo.');
        }
        if ($from !== null) {
            $criteria->addFilter(new RangeFilter('publishedAt', [RangeFilter::GTE => $from->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        }
        if ($to !== null) {
            $criteria->addFilter(new RangeFilter('publishedAt', [RangeFilter::LT => $to->modify('+1 day')->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        }

        $term = trim(mb_substr($request->query->getString('q'), 0, 100));
        if ($term !== '') {
            $criteria->setTerm($term);
        }

        $result = $this->published->repository()->search($criteria, $context->getContext());
        $posts = $result->getEntities();
        $paths = $this->published->canonicalPaths($posts->getIds(), $context);
        $domain = $this->published->domain($context);
        $elements = [];
        foreach ($posts as $post) {
            $elements[] = $this->published->format($post, $paths[$post->getUniqueIdentifier()] ?? null, $domain, $indexable);
        }

        return ['elements' => $elements, 'total' => $result->getTotal(), 'page' => $page, 'limit' => $limit];
    }

    private function date(Request $request, string $parameter): ?\DateTimeImmutable
    {
        $value = $request->query->getString($parameter);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException("$parameter must be a valid UTC date in YYYY-MM-DD format.");
        }

        return $date;
    }

    private function pagination(Request $request): array
    {
        return [
            max(1, min(100000, $request->query->getInt('page', 1))),
            max(1, min(100, $request->query->getInt('limit', 20))),
        ];
    }
}
