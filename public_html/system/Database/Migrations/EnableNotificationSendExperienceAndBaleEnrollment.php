<?php

namespace IPKF\Database\Migrations;

class EnableNotificationSendExperienceAndBaleEnrollment extends Migration
{
    public function up(): void
    {
        $this->ensureMessageTypeColumn();
        $this->createMediaFoundation();
        $this->createEnrollmentTables();
        $this->extendBaleSchema();
        $this->ensureInvitationRoute();
    }

    public function down(): void
    {
    }

    private function ensureMessageTypeColumn(): void
    {
        if (
            $this->tableExists('notifications')
            && !$this->columnExists(
                'notifications',
                'message_type_code'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notifications
                ADD COLUMN message_type_code
                    VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'text'
                AFTER template_code
            ");
        }
    }

    private function createMediaFoundation(): void
    {
        $options =
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_media_assets (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                actor_user_id BIGINT UNSIGNED NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_name VARCHAR(255)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                storage_path VARCHAR(1000) NOT NULL,
                mime_type VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                extension VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                media_kind VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL,
                checksum_sha256 CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'active',
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_media_assets_reference_unique
                    (public_reference),
                INDEX
                    notification_media_assets_actor_index
                    (actor_user_id, status_code, id),
                INDEX
                    notification_media_assets_checksum_index
                    (checksum_sha256)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_media_links (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                notification_id BIGINT UNSIGNED NOT NULL,
                asset_id BIGINT UNSIGNED NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_primary TINYINT(1)
                    NOT NULL DEFAULT 0,
                alt_text VARCHAR(500) NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_media_links_unique
                    (notification_id, asset_id),
                INDEX
                    notification_media_links_order_index
                    (notification_id, sort_order, id)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_delivery_media_results (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                delivery_id BIGINT UNSIGNED NOT NULL,
                asset_id BIGINT UNSIGNED NULL,
                part_type VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                part_order INT NOT NULL DEFAULT 0,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                provider_message_reference
                    VARCHAR(500) NULL,
                provider_response_code
                    VARCHAR(100) NULL,
                error_code VARCHAR(190) NULL,
                response_metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                INDEX
                    notification_delivery_media_results_delivery_index
                    (delivery_id, part_order, id)
            ) {$options}
        ");
    }

    private function createEnrollmentTables(): void
    {
        $options =
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_messenger_enrollments (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                provider_instance_id
                    BIGINT UNSIGNED NOT NULL,
                mobile_norm VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                token_hash CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'pending',
                expires_at DATETIME NOT NULL,
                started_at DATETIME NULL,
                verified_at DATETIME NULL,
                used_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                linked_chat_id VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NULL,
                linked_external_user_id VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                invited_by_user_id
                    BIGINT UNSIGNED NOT NULL,
                invite_delivery_reference
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NULL,
                last_error VARCHAR(190) NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_messenger_enrollments_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_messenger_enrollments_token_unique
                    (token_hash),
                INDEX
                    notification_messenger_enrollments_user_index
                    (user_id, status_code, id),
                INDEX
                    notification_messenger_enrollments_chat_index
                    (provider_instance_id, linked_chat_id),
                INDEX
                    notification_messenger_enrollments_expiry_index
                    (status_code, expires_at)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_messenger_bindings (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                provider_instance_id
                    BIGINT UNSIGNED NOT NULL,
                external_user_id VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                chat_id VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                mobile_norm VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                username VARCHAR(190) NULL,
                display_name VARCHAR(255) NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'active',
                verified_at DATETIME NOT NULL,
                last_activity_at DATETIME NULL,
                revoked_at DATETIME NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_messenger_bindings_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_messenger_bindings_user_provider_unique
                    (user_id, provider_instance_id),
                UNIQUE KEY
                    notification_messenger_bindings_chat_provider_unique
                    (chat_id, provider_instance_id),
                INDEX
                    notification_messenger_bindings_active_index
                    (user_id, status_code, id)
            ) {$options}
        ");
    }

    private function extendBaleSchema(): void
    {
        if (!$this->tableExists(
            'notification_provider_types'
        )) {
            return;
        }

        $statement = $this->db->prepare("
            SELECT config_schema_json
            FROM notification_provider_types
            WHERE code = 'bale_bot'
            LIMIT 1
        ");
        $statement->execute();

        $schema = json_decode(
            (string) $statement->fetchColumn(),
            true
        );

        if (!is_array($schema)) {
            $schema = [];
        }

        $keys = [];

        foreach ($schema as $field) {
            if (is_array($field)) {
                $keys[] = (string) (
                    $field['key'] ?? ''
                );
            }
        }

        if (!in_array(
            'bot_purpose_code',
            $keys,
            true
        )) {
            $schema[] = [
                'key' => 'bot_purpose_code',
                'type' => 'select',
                'required' => false,
                'label' => 'کاربرد بات بله',
                'description' =>
                    'برای بات جدید، عضویت و احراز هویت را انتخاب کنید.',
                'options' => [
                    'notifications',
                    'membership_auth',
                ],
                'option_labels' => [
                    'notifications' =>
                        'اعلان‌های عمومی',
                    'membership_auth' =>
                        'عضویت و احراز هویت',
                ],
            ];
        }

        if (!in_array(
            'enrollment_link_template',
            $keys,
            true
        )) {
            $schema[] = [
                'key' =>
                    'enrollment_link_template',
                'type' => 'text',
                'required' => false,
                'label' =>
                    'قالب لینک فعال‌سازی عضویت',
                'description' =>
                    'باید شامل {token} باشد؛ در صورت خالی بودن از نام کاربری بات ساخته می‌شود.',
            ];
        }

        $update = $this->db->prepare("
            UPDATE notification_provider_types
            SET config_schema_json = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE code = 'bale_bot'
        ");
        $update->execute([
            json_encode(
                $schema,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function ensureInvitationRoute(): void
    {
        if (!$this->tableExists(
            'admin_route_permissions'
        )) {
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO admin_route_permissions (
                route_pattern,
                http_method,
                permission_mode,
                permission_codes_json,
                priority,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                'POST',
                'any',
                ?,
                85,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                permission_mode = 'any',
                permission_codes_json =
                    VALUES(permission_codes_json),
                priority = VALUES(priority),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([
            '/admin/communications/settings/send/bale-invitations',
            json_encode(
                ['notifications.send.manage'],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function tableExists(
        string $table
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $statement->execute([
            $table,
            $column,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
