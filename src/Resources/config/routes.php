<?php declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('../../Administration/PreviewController.php', 'attribute');
    $routes->import('../../StoreApi/BlogController.php', 'attribute');
};
