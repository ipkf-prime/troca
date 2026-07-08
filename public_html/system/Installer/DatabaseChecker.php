<?php

namespace IPKF\Installer;

use IPKF\Database\Database;
use IPKF\Support\Config;

class DatabaseChecker
{
    public function check(): array
    {
        $connectionAvailable = false;

        if (Database::configured()) {
            try {
                Database::connect();
                $connectionAvailable = true;
            } catch (\Throwable $exception) {
                $connectionAvailable = false;
            }
        }

        return [
            'database_config_loaded' => Config::has('database.connections.mysql'),
            'database_connection_available' => $connectionAvailable,
            'migration_system_available' => class_exists(\IPKF\Database\Migrations\MigrationRunner::class),
            'seeder_system_available' => class_exists(\IPKF\Database\Seeds\SeederRunner::class),
        ];
    }
}
