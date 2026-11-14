<?php declare(strict_types=1);

namespace Inocean\Blog\Migration;

use Doctrine\DBAL\Connection;
use Inocean\Blog\Content\PostDefinition;
use Inocean\Blog\Seo\BlogSeoRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1794614400Blog extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794614400;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ino_blog_category` (
                `id` BINARY(16) NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ino_blog_category_translation` (
                `ino_blog_category_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` LONGTEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`ino_blog_category_id`, `language_id`),
                CONSTRAINT `fk.ino_blog_category_translation.category` FOREIGN KEY (`ino_blog_category_id`)
                    REFERENCES `ino_blog_category` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.ino_blog_category_translation.language` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ino_blog_post` (
                `id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `category_id` BINARY(16) NULL,
                `cover_id` BINARY(16) NULL,
                `video_id` BINARY(16) NULL,
                `author` VARCHAR(255) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 0,
                `pinned` TINYINT(1) NOT NULL DEFAULT 0,
                `indexable` TINYINT(1) NOT NULL DEFAULT 1,
                `published_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.ino_blog_post.publication` (`sales_channel_id`, `active`, `published_at`),
                CONSTRAINT `fk.ino_blog_post.sales_channel` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk.ino_blog_post.category` FOREIGN KEY (`category_id`)
                    REFERENCES `ino_blog_category` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.ino_blog_post.cover` FOREIGN KEY (`cover_id`)
                    REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT `fk.ino_blog_post.video` FOREIGN KEY (`video_id`)
                    REFERENCES `media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `ino_blog_post_translation` (
                `ino_blog_post_id` BINARY(16) NOT NULL,
                `language_id` BINARY(16) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `excerpt` LONGTEXT NULL,
                `content` LONGTEXT NULL,
                `meta_title` VARCHAR(255) NULL,
                `meta_description` VARCHAR(255) NULL,
                `slug` VARCHAR(100) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`ino_blog_post_id`, `language_id`),
                CONSTRAINT `fk.ino_blog_post_translation.post` FOREIGN KEY (`ino_blog_post_id`)
                    REFERENCES `ino_blog_post` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.ino_blog_post_translation.language` FOREIGN KEY (`language_id`)
                    REFERENCES `language` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO seo_url_template (id, entity_name, route_name, sales_channel_id, template, is_valid, is_headless, created_at)
            SELECT UNHEX(MD5(:route)), :entity, :route, NULL, :template, 1, 1, :created
            WHERE NOT EXISTS (
                SELECT 1 FROM seo_url_template WHERE route_name = :route AND sales_channel_id IS NULL AND is_headless = 1
            )
            SQL, [
            'entity' => PostDefinition::ENTITY_NAME,
            'route' => BlogSeoRoute::NAME,
            'template' => BlogSeoRoute::TEMPLATE,
            'created' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
