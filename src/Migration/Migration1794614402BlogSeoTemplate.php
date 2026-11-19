<?php declare(strict_types=1);

namespace Inocean\Blog\Migration;

use Doctrine\DBAL\Connection;
use Inocean\Blog\Seo\BlogSeoRoute;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1794614402BlogSeoTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794614402;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            'UPDATE seo_url_template SET template = :template, updated_at = UTC_TIMESTAMP(3)
             WHERE route_name = :route AND is_headless = 1 AND BINARY template = :legacy',
            ['template' => BlogSeoRoute::TEMPLATE, 'route' => BlogSeoRoute::NAME, 'legacy' => BlogSeoRoute::LEGACY_TEMPLATE],
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
