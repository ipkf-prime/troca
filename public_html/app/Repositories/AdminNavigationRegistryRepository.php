<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

class AdminNavigationRegistryRepository extends BaseRepository
{
    public function items(string $shellKey): array
    {
        if (!Database::tableExists('admin_navigation_items')) {
            return [];
        }

        $statement = $this->connection()->prepare("
            SELECT *
            FROM admin_navigation_items
            WHERE shell_key = ?
              AND is_active = 1
            ORDER BY
                parent_id IS NOT NULL ASC,
                sort_order ASC,
                id ASC
        ");
        $statement->execute([$shellKey]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function routeRules(string $method): array
    {
        if (!Database::tableExists('admin_route_permissions')) {
            return [];
        }

        $statement = $this->connection()->prepare("
            SELECT *
            FROM admin_route_permissions
            WHERE http_method = ?
              AND is_active = 1
            ORDER BY
                priority DESC,
                CHAR_LENGTH(route_pattern) DESC,
                id ASC
        ");
        $statement->execute([strtoupper($method)]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
