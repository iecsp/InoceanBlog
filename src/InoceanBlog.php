<?php declare(strict_types=1);

namespace Inocean\Blog;

use Doctrine\DBAL\Connection;
use Inocean\Blog\Seo\BlogSeoRoute;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

final class InoceanBlog extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        foreach (['seo_url', 'seo_url_template'] as $table) {
            $connection->delete($table, ['route_name' => BlogSeoRoute::NAME]);
        }

        foreach (['ino_blog_post_tag', 'ino_blog_post_translation', 'ino_blog_post', 'ino_blog_category_translation', 'ino_blog_category'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }
}
