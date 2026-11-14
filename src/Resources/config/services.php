<?php declare(strict_types=1);

use Inocean\Blog\Administration\PreviewController;
use Inocean\Blog\Mcp\{CategoryWriteTool, PostWriteTool};
use Inocean\Blog\Content\{CategoryDefinition, CategoryTranslationDefinition, PostDefinition, PostTagDefinition, PostTranslationDefinition, PublishedPosts};
use Inocean\Blog\Seo\{BlogSeoIndexer, BlogSeoRoute, BlogSitemapProvider};
use Inocean\Blog\StoreApi\BlogController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $config): void {
    $services = $config->services()->defaults()->autowire()->autoconfigure();

    $services->set(CategoryDefinition::class);
    $services->set(CategoryTranslationDefinition::class);
    $services->set(PostDefinition::class);
    $services->set(PostTranslationDefinition::class);
    $services->set(PostTagDefinition::class);
    $services->set(PreviewController::class)->public();

    $services->set(PostWriteTool::class)
        ->arg('$repository', service('ino_blog_post.repository'))->tag('shopware.mcp.tool');
    $services->set(CategoryWriteTool::class)
        ->arg('$repository', service('ino_blog_category.repository'))->tag('shopware.mcp.tool');

    $services->set(BlogSeoRoute::class)->tag('shopware.entity.seo_url.route');
    $services->set(BlogSeoIndexer::class);
    $services->set(BlogSitemapProvider::class);
    $services->set(PublishedPosts::class)
        ->args([service('ino_blog_post.repository'), service('seo_url.repository')]);
    $services->set(BlogController::class)
        ->arg('$categories', service('ino_blog_category.repository'))
        ->public();
};
