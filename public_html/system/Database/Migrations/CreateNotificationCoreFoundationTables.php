<?php

namespace IPKF\Database\Migrations;

class CreateNotificationCoreFoundationTables extends Migration
{
    public function up(): void
    {
        $userIdType = $this->referenceColumnType(
            'users',
            'id',
            'BIGINT UNSIGNED'
        );

        foreach (
            $this->statements($userIdType)
            as $statement
        ) {
            $this->db->exec($statement);
        }

        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function statements(
        string $userIdType
    ): array {
        $options = 'ENGINE=InnoDB '
            . 'DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS notification_channels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                title VARCHAR(190) NOT NULL,
                driver_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                is_internal TINYINT(1)
                    NOT NULL DEFAULT 0,
                supports_subject TINYINT(1)
                    NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_channels_code_unique
                    (code),
                INDEX
                    notification_channels_active_sort_index
                    (is_active, sort_order)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                event_type VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                channel_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                locale VARCHAR(15)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'fa',
                title_template VARCHAR(500) NULL,
                body_template LONGTEXT NOT NULL,
                action_url_template VARCHAR(1000) NULL,
                format_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'plain',
                version INT UNSIGNED NOT NULL DEFAULT 1,
                is_active TINYINT(1)
                    NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_templates_version_unique (
                        code,
                        channel_code,
                        locale,
                        version
                    ),
                INDEX
                    notification_templates_event_channel_index (
                        event_type,
                        channel_code,
                        is_active
                    )
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                idempotency_key VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                event_type VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                source_module VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                source_entity_type VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,
                source_entity_reference VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,
                actor_user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,
                payload_json LONGTEXT NOT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'recorded',
                occurred_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_events_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_events_idempotency_unique
                    (idempotency_key),
                INDEX notification_events_source_index (
                    source_module,
                    source_entity_type,
                    source_entity_reference
                ),
                INDEX notification_events_type_time_index (
                    event_type,
                    occurred_at
                )
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_outbox (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                event_id BIGINT UNSIGNED NOT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'pending',
                priority_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'normal',
                available_at DATETIME NOT NULL,
                locked_at DATETIME NULL,
                locked_by VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,
                attempts_count INT UNSIGNED
                    NOT NULL DEFAULT 0,
                max_attempts INT UNSIGNED
                    NOT NULL DEFAULT 8,
                processed_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_outbox_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_outbox_event_unique
                    (event_id),
                INDEX notification_outbox_claim_index (
                    status_code,
                    available_at,
                    priority_code,
                    id
                )
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                event_id BIGINT UNSIGNED NOT NULL,
                template_code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,
                title VARCHAR(500) NOT NULL,
                body LONGTEXT NOT NULL,
                action_url VARCHAR(1000) NULL,
                priority_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'normal',
                category_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'general',
                expires_at DATETIME NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notifications_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notifications_event_unique
                    (event_id),
                INDEX
                    notifications_category_created_index
                    (category_code, created_at),
                INDEX notifications_expires_index
                    (expires_at)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_recipients (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_id BIGINT UNSIGNED NOT NULL,
                user_id {$userIdType} NULL,
                user_reference VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                delivery_policy_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'immediate',
                seen_at DATETIME NULL,
                read_at DATETIME NULL,
                archived_at DATETIME NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_recipients_unique (
                        notification_id,
                        user_reference
                    ),
                INDEX
                    notification_recipients_user_unread_index (
                        user_id,
                        read_at,
                        archived_at,
                        id
                    ),
                INDEX
                    notification_recipients_reference_unread_index (
                        user_reference,
                        read_at,
                        archived_at,
                        id
                    )
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_deliveries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipient_id BIGINT UNSIGNED NOT NULL,
                channel_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'pending',
                destination_snapshot VARCHAR(500) NULL,
                provider_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,
                provider_message_reference
                    VARCHAR(500) NULL,
                available_at DATETIME NOT NULL,
                attempt_count INT UNSIGNED
                    NOT NULL DEFAULT 0,
                max_attempts INT UNSIGNED
                    NOT NULL DEFAULT 8,
                last_attempt_at DATETIME NULL,
                sent_at DATETIME NULL,
                delivered_at DATETIME NULL,
                failed_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_deliveries_recipient_channel_unique (
                        recipient_id,
                        channel_code
                    ),
                INDEX notification_deliveries_queue_index (
                    channel_code,
                    status_code,
                    available_at,
                    id
                )
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_delivery_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                delivery_id BIGINT UNSIGNED NOT NULL,
                attempt_number INT UNSIGNED NOT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                provider_response_code VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL,
                provider_response_message
                    VARCHAR(2000) NULL,
                attempted_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_delivery_attempts_unique (
                        delivery_id,
                        attempt_number
                    ),
                INDEX
                    notification_delivery_attempts_time_index
                    (attempted_at)
            ) {$options}",

            "CREATE TABLE IF NOT EXISTS notification_preferences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id {$userIdType} NOT NULL,
                event_type VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT '*',
                channel_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL,
                is_enabled TINYINT(1)
                    NOT NULL DEFAULT 1,
                quiet_start TIME NULL,
                quiet_end TIME NULL,
                timezone VARCHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'Asia/Tehran',
                digest_mode VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL DEFAULT 'immediate',
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_preferences_unique (
                        user_id,
                        event_type,
                        channel_code
                    ),
                INDEX
                    notification_preferences_channel_index (
                        channel_code,
                        is_enabled
                    )
            ) {$options}",
        ];
    }

    private function addForeignKeys(): void
    {
        foreach ([
            [
                'notification_outbox',
                'notification_outbox_event_fk',
                'event_id',
                'notification_events',
                'id',
                'CASCADE',
            ],
            [
                'notifications',
                'notifications_event_fk',
                'event_id',
                'notification_events',
                'id',
                'CASCADE',
            ],
            [
                'notification_recipients',
                'notification_recipients_notification_fk',
                'notification_id',
                'notifications',
                'id',
                'CASCADE',
            ],
            [
                'notification_recipients',
                'notification_recipients_user_fk',
                'user_id',
                'users',
                'id',
                'SET NULL',
            ],
            [
                'notification_deliveries',
                'notification_deliveries_recipient_fk',
                'recipient_id',
                'notification_recipients',
                'id',
                'CASCADE',
            ],
            [
                'notification_delivery_attempts',
                'notification_delivery_attempts_delivery_fk',
                'delivery_id',
                'notification_deliveries',
                'id',
                'CASCADE',
            ],
            [
                'notification_preferences',
                'notification_preferences_user_fk',
                'user_id',
                'users',
                'id',
                'CASCADE',
            ],
        ] as $foreignKey) {
            $this->addForeignKeyIfPossible(
                ...$foreignKey
            );
        }
    }

    private function referenceColumnType(
        string $table,
        string $column,
        string $default
    ): string {
        if (
            !$this->tableExists($table)
            || !$this->columnExists(
                $table,
                $column
            )
        ) {
            return $default;
        }

        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([
            $table,
            $column,
        ]);
        $type = strtoupper(
            trim((string) $statement->fetchColumn())
        );

        return preg_match(
            '/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)'
            . '(\\(\\d+\\))?( UNSIGNED)?$/',
            $type
        ) === 1
            ? $type
            : $default;
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (
            !$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists(
                $referenceTable,
                $referenceColumn
            )
            || $this->foreignKeyExists(
                $table,
                $constraint
            )
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys(
                $referenceTable
            )
            || $this->columnType($table, $column)
                !== $this->columnType(
                    $referenceTable,
                    $referenceColumn
                )
        ) {
            return;
        }

        $onDelete = in_array(
            $onDelete,
            ['CASCADE', 'SET NULL', 'RESTRICT'],
            true
        ) ? $onDelete : 'RESTRICT';

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column})
            REFERENCES {$referenceTable}
                ({$referenceColumn})
            ON UPDATE CASCADE
            ON DELETE {$onDelete}
        ");
    }

    private function tableExists(string $table): bool
    {
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

    private function columnType(
        string $table,
        string $column
    ): string {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([
            $table,
            $column,
        ]);

        return strtolower(
            trim((string) $statement->fetchColumn())
        );
    }

    private function supportsForeignKeys(
        string $table
    ): bool {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower(
            trim((string) $statement->fetchColumn())
        ) === 'innodb';
    }

    private function foreignKeyExists(
        string $table,
        string $constraint
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
        ");
        $statement->execute([
            $table,
            $constraint,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
