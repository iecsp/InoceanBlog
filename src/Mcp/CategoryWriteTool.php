<?php declare(strict_types=1);

namespace Inocean\Blog\Mcp;

use Mcp\Capability\Attribute\{McpTool, Schema};
use Shopware\Core\Framework\Mcp\Attribute\{McpToolGroup, McpToolRequires};

#[McpTool(name: 'inocean-blog-category-write', description: 'Create or update a Blog category, including its localized name and description or sort position. Defaults to validating the change.')]
#[McpToolGroup('inocean-blog')]
#[McpToolRequires('ino_blog_category:create')]
#[McpToolRequires('ino_blog_category:update')]
final class CategoryWriteTool extends BlogWriteTool
{
    public function __invoke(
        #[Schema(description: 'One JSON object with name, description and position. Omit id to create a category. Include an existing category id to update fields.')]
        string $payload,
        #[Schema(description: 'Validate and roll back with true. Use false to save.')]
        bool $dryRun = true,
    ): string {
        return $this->write($payload, $dryRun, ['name', 'description', 'position']);
    }
}
