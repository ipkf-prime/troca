<?php

namespace IPKF\Database\Application;

use IPKF\Database\Migrations\MigrationExecutionException;
use PDO;
use Throwable;

class ApplicationMigrationRunner
{
    public function run(string $application, string $connectionName, array $migrations, PDO $connection): void
    {
        if ($migrations === []) {
            return;
        }

        $this->createHistoryTable($connection);

        foreach ($migrations as $migration) {
            $name = get_class($migration);
            $statement = $connection->prepare("
                SELECT id
                FROM application_migrations
                WHERE application_code = ?
                  AND connection_name = ?
                  AND migration = ?
                LIMIT 1
            ");
            $statement->execute([$application, $connectionName, $name]);

            if ($statement->fetch()) {
                continue;
            }

            try {
                $migration->up();
            } catch (Throwable $exception) {
                throw new MigrationExecutionException($name, $exception);
            }

            $insert = $connection->prepare("
                INSERT INTO application_migrations (
                    application_code, connection_name, migration, created_at
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $insert->execute([$application, $connectionName, $name]);
        }
    }

    private function createHistoryTable(PDO $connection): void
    {
        $connection->exec("
            CREATE TABLE IF NOT EXISTS application_migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                application_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                connection_name VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                migration VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY application_migrations_unique (application_code, connection_name, migration),
                INDEX application_migrations_app_index (application_code),
                INDEX application_migrations_connection_index (connection_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
