<?php

namespace App\Repositories;

use IPKF\Database\Database;

class ApplicationModuleRepository extends BaseRepository
{
    public function available(): bool
    {
        return Database::tableExists(
            'application_modules'
        );
    }


    public function all(): array
    {
        if (!$this->available()) {
            return [];
        }

        return $this->connection()
            ->query("
                SELECT *
                FROM application_modules
                ORDER BY sort_order, display_name
            ")
            ->fetchAll() ?: [];
    }


    public function save(array $data): void
    {
        $statement = $this->connection()->prepare("
            INSERT INTO application_modules
            (
                module_key,
                display_name,
                base_url,
                sso_callback_url,

                database_connection_name,
                database_host,
                database_port,
                database_name,
                database_username,
                database_charset,
                database_ssl_mode,
                connection_timeout,
                runtime_mode,
                secret_reference,

                icon_code,
                color_code,
                route_path,
                permission_key,
                sidebar_enabled,
                dashboard_enabled,
                dashboard_description,

                is_active,
                sort_order
            )
            VALUES
            (
                :module_key,
                :display_name,
                :base_url,
                :sso_callback_url,

                :database_connection_name,
                :database_host,
                :database_port,
                :database_name,
                :database_username,
                :database_charset,
                :database_ssl_mode,
                :connection_timeout,
                :runtime_mode,
                :secret_reference,

                :icon_code,
                :color_code,
                :route_path,
                :permission_key,
                :sidebar_enabled,
                :dashboard_enabled,
                :dashboard_description,

                :is_active,
                :sort_order
            )
            ON DUPLICATE KEY UPDATE
                display_name =
                    VALUES(display_name),

                base_url =
                    VALUES(base_url),

                sso_callback_url =
                    VALUES(sso_callback_url),

                database_connection_name =
                    VALUES(database_connection_name),

                database_host =
                    VALUES(database_host),

                database_port =
                    VALUES(database_port),

                database_name =
                    VALUES(database_name),

                database_username =
                    VALUES(database_username),

                database_charset =
                    VALUES(database_charset),

                database_ssl_mode =
                    VALUES(database_ssl_mode),

                connection_timeout =
                    VALUES(connection_timeout),

                runtime_mode =
                    VALUES(runtime_mode),

                secret_reference =
                    VALUES(secret_reference),

                icon_code =
                    VALUES(icon_code),

                color_code =
                    VALUES(color_code),

                route_path =
                    VALUES(route_path),

                permission_key =
                    VALUES(permission_key),

                sidebar_enabled =
                    VALUES(sidebar_enabled),

                dashboard_enabled =
                    VALUES(dashboard_enabled),

                dashboard_description =
                    VALUES(dashboard_description),

                is_active =
                    VALUES(is_active),

                sort_order =
                    VALUES(sort_order)
        ");

        $statement->execute($data);
    }
}
