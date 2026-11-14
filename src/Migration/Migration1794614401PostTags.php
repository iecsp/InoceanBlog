<?php declare(strict_types=1);

namespace Inocean\Blog\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1794614401PostTags extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794614401;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ino_blog_post_tag` (
                `post_id` BINARY(16) NOT NULL,
                `tag_id` BINARY(16) NOT NULL,
                PRIMARY KEY (`post_id`, `tag_id`),
                INDEX `idx.ino_blog_post_tag.tag_post` (`tag_id`, `post_id`),
                CONSTRAINT `fk.ino_blog_post_tag.post` FOREIGN KEY (`post_id`)
                    REFERENCES `ino_blog_post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.ino_blog_post_tag.tag` FOREIGN KEY (`tag_id`)
                    REFERENCES `tag` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $indexes = $connection->createSchemaManager()->listTableIndexes('ino_blog_post');
        foreach ([
            'idx.ino_blog_post.channel_date' => 'sales_channel_id, active, published_at, id',
            'idx.ino_blog_post.category_date' => 'sales_channel_id, category_id, active, published_at, id',
            'idx.ino_blog_post.channel_pinned' => 'sales_channel_id, active, pinned, published_at, id',
        ] as $name => $columns) {
            if (!isset($indexes[$name])) {
                $connection->executeStatement("CREATE INDEX `$name` ON ino_blog_post ($columns)");
            }
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
