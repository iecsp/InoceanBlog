<?php declare(strict_types=1);

namespace Inocean\Blog\Administration;

use Inocean\Blog\Content\PublishedPosts;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class PreviewController
{
    public function __construct(private readonly PublishedPosts $posts)
    {
    }

    #[Route(
        path: '/api/_action/ino-blog/preview',
        name: 'api.action.ino-blog.preview',
        defaults: ['_routeScope' => ['api'], '_acl' => ['ino_blog_post:read']],
        methods: ['POST'],
    )]
    public function preview(Request $request): JsonResponse
    {
        $content = $request->toArray()['content'] ?? '';
        if (!\is_string($content) || \strlen($content) > 2_000_000) {
            throw new BadRequestHttpException('Invalid preview content.');
        }

        return new JsonResponse(['html' => $this->posts->sanitizeContent($content)], headers: ['Cache-Control' => 'no-store']);
    }
}
