<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class CreatePublicRuntimePresence extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                online_user_presence (
                    user_id BIGINT UNSIGNED
                        NOT NULL,
                    last_seen_at DATETIME
                        NOT NULL,
                    created_at DATETIME
                        NOT NULL,
                    updated_at DATETIME
                        NOT NULL,

                    PRIMARY KEY (user_id),

                    INDEX
                        online_user_presence_last_seen
                        (last_seen_at)
                )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");

        $rows = [
            [
                'runtime_status_position',
                'right',
                120,
            ],
            [
                'runtime_online_position',
                'right',
                130,
            ],
            [
                'runtime_datetime_position',
                'center',
                140,
            ],
            [
                'runtime_version_position',
                'left',
                150,
            ],
            [
                'runtime_deploy_position',
                'left',
                160,
            ],
        ];

        $statement = $this->db->prepare("
            INSERT INTO public_page_settings (
                setting_key,
                setting_value,
                sort_order
            )
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_key =
                    VALUES(setting_key)
        ");

        foreach ($rows as $row) {
            $statement->execute($row);
        }
    }

    public function down(): void
    {
        $this->db->exec("
            DELETE FROM public_page_settings
            WHERE setting_key IN (
                'runtime_status_position',
                'runtime_online_position',
                'runtime_datetime_position',
                'runtime_version_position',
                'runtime_deploy_position'
            )
        ");

        $this->db->exec("
            DROP TABLE IF EXISTS
                online_user_presence
        ");
    }
}
