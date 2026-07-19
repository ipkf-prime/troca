<?php

namespace App\Repositories;

use IPKF\Database\Database;

class ApplicationModuleRepository extends BaseRepository
{
    public function available(): bool
    {
        return Database::tableExists('application_modules');
    }

    public function all(): array
    {
        if (!$this->available()) {
            return [];
        }

        return $this->connection()->query('SELECT * FROM application_modules ORDER BY sort_order, display_name')->fetchAll() ?: [];
    }

    public function save(array $data): void
    {
        $statement = $this->connection()->prepare("
            INSERT INTO application_modules
                (module_key, display_name, base_url, sso_callback_url, database_connection_name,
                 database_host, database_port, database_name, secret_reference, is_active, sort_order)
            VALUES
                (:module_key, :display_name, :base_url, :sso_callback_url, :database_connection_name,
                 :database_host, :database_port, :database_name, :secret_reference, :is_active, :sort_order)
            ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name), base_url = VALUES(base_url),
                sso_callback_url = VALUES(sso_callback_url), database_connection_name = VALUES(database_connection_name),
                database_host = VALUES(database_host), database_port = VALUES(database_port),
                database_name = VALUES(database_name), secret_reference = VALUES(secret_reference),
                is_active = VALUES(is_active), sort_order = VALUES(sort_order)
        ");
        $statement->execute($data);
    }
}
