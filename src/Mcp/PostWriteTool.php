<?php declare(strict_types=1);

namespace Inocean\Blog\Mcp;

use Mcp\Capability\Attribute\{McpTool, Schema};
use Shopware\Core\Framework\Mcp\Attribute\{McpToolGroup, McpToolRequires};

#[McpTool(name: 'inocean-blog-post-write', description: 'Create a Blog draft, revise an existing article, or set its publication date and SEO options. Defaults to validating the change.')]
#[McpToolGroup('inocean-blog')]
#[McpToolRequires('ino_blog_post:create')]
#[McpToolRequires('ino_blog_post:update')]
final class PostWriteTool extends BlogWriteTool
{
    public function __invoke(
        #[Schema(description: 'One JSON object. Omit id to create with title, author and salesChannelId. Writable fields: title, author, salesChannelId, categoryId, coverId, videoId, active, pinned, indexable, publishedAt (UTC ISO 8601), excerpt, content (HTML), metaTitle, metaDescription, slug. Use the native entity tools for tags.')]
        string $payload,
        #[Schema(description: 'Validate and roll back with true. Use false to save.')]
        bool $dryRun = true,
    ): string {
        return $this->write($payload, $dryRun, [
            'title', 'author', 'salesChannelId', 'categoryId', 'coverId', 'videoId', 'active', 'pinned',
            'indexable', 'publishedAt', 'excerpt', 'content', 'metaTitle', 'metaDescription', 'slug',
        ]);
    }
}
