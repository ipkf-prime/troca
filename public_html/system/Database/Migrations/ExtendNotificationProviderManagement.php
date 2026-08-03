<?php

namespace IPKF\Database\Migrations;

class ExtendNotificationProviderManagement extends Migration
{
    public function up(): void
    {
        $this->extendProviderInstances();
        $this->extendProviderDefaults();
        $this->createSecretSets();
        $this->createProviderAuditEvents();
        $this->createWebhookEndpoints();
        $this->createWebhookEvents();
        $this->backfillProviderInstances();
        $this->backfillProviderDefaults();
        $this->addIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function extendProviderInstances(): void
    {
        if (!$this->tableExists('notification_provider_instances')) {
            return;
        }

        $columns = [
            'public_reference' => "
                ADD COLUMN public_reference
                VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL
                AFTER id
            ",
            'description' => "
                ADD COLUMN description VARCHAR(1000) NULL
                AFTER title
            ",
            'instance_kind' => "
                ADD COLUMN instance_kind
                VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'account'
                AFTER description
            ",
            'is_enabled' => "
                ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 0
                AFTER status_code
            ",
            'configuration_version' => "
                ADD COLUMN configuration_version
                INT UNSIGNED NOT NULL DEFAULT 1
                AFTER configuration_json
            ",
            'health_status_code' => "
                ADD COLUMN health_status_code
                VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'unknown'
                AFTER balance_checked_at
            ",
            'last_tested_at' => "
                ADD COLUMN last_tested_at DATETIME NULL
                AFTER health_status_code
            ",
            'last_test_status_code' => "
                ADD COLUMN last_test_status_code
                VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL
                AFTER last_tested_at
            ",
            'last_test_message' => "
                ADD COLUMN last_test_message VARCHAR(1000) NULL
                AFTER last_test_status_code
            ",
            'created_by_user_id' => "
                ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL
                AFTER monthly_limit
            ",
            'updated_by_user_id' => "
                ADD COLUMN updated_by_user_id BIGINT UNSIGNED NULL
                AFTER created_by_user_id
            ",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'notification_provider_instances',
                $column
            )) {
                $this->db->exec(
                    "ALTER TABLE notification_provider_instances "
                    . $definition
                );
            }
        }
    }

    private function extendProviderDefaults(): void
    {
        if (!$this->tableExists('notification_provider_defaults')) {
            return;
        }

        $columns = [
            'purpose_code' => "
                ADD COLUMN purpose_code
                VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'general'
                AFTER channel_code
            ",
            'is_default' => "
                ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0
                AFTER priority
            ",
            'fallback_order' => "
                ADD COLUMN fallback_order INT UNSIGNED NOT NULL DEFAULT 0
                AFTER is_default
            ",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'notification_provider_defaults',
                $column
            )) {
                $this->db->exec(
                    "ALTER TABLE notification_provider_defaults "
                    . $definition
                );
            }
        }

        if ($this->indexExists(
            'notification_provider_defaults',
            'notification_provider_defaults_unique'
        )) {
            $this->db->exec("
                ALTER TABLE notification_provider_defaults
                DROP INDEX notification_provider_defaults_unique
            ");
        }

        if (!$this->indexExists(
            'notification_provider_defaults',
            'notification_provider_defaults_unique_v2'
        )) {
            $this->db->exec("
                ALTER TABLE notification_provider_defaults
                ADD UNIQUE KEY notification_provider_defaults_unique_v2 (
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code,
                    priority
                )
            ");
        }
    }

    private function createSecretSets(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_provider_secret_sets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider_instance_id BIGINT UNSIGNED NOT NULL,
                cipher_code
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                key_version INT UNSIGNED NOT NULL DEFAULT 1,
                encrypted_payload LONGTEXT NOT NULL,
                payload_checksum
                    CHAR(64) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                rotated_at DATETIME NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_provider_secret_instance_unique (
                    provider_instance_id
                ),
                INDEX notification_provider_secret_rotation_index (
                    rotated_at,
                    updated_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createProviderAuditEvents(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_provider_audit_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider_instance_id BIGINT UNSIGNED NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                action_code
                    VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                change_summary_json LONGTEXT NULL,
                request_reference
                    VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
                occurred_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX notification_provider_audit_instance_time_index (
                    provider_instance_id,
                    occurred_at,
                    id
                ),
                INDEX notification_provider_audit_actor_time_index (
                    actor_user_id,
                    occurred_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createWebhookEndpoints(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_webhook_endpoints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                provider_instance_id BIGINT UNSIGNED NOT NULL,
                endpoint_token_hash
                    CHAR(64) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                status_code
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'inactive',
                delivery_mode
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'webhook',
                subscribed_events_json LONGTEXT NULL,
                last_registered_at DATETIME NULL,
                last_verified_at DATETIME NULL,
                last_received_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_webhook_reference_unique (
                    public_reference
                ),
                UNIQUE KEY notification_webhook_instance_unique (
                    provider_instance_id
                ),
                INDEX notification_webhook_status_index (
                    status_code,
                    updated_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createWebhookEvents(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_webhook_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                webhook_endpoint_id BIGINT UNSIGNED NOT NULL,
                external_event_id
                    VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                event_type
                    VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                signature_status_code
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'unchecked',
                processing_status_code
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'pending',
                headers_json LONGTEXT NULL,
                payload_json LONGTEXT NOT NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                received_at DATETIME NOT NULL,
                processed_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_webhook_event_reference_unique (
                    public_reference
                ),
                UNIQUE KEY notification_webhook_external_event_unique (
                    webhook_endpoint_id,
                    external_event_id
                ),
                INDEX notification_webhook_processing_index (
                    processing_status_code,
                    received_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function backfillProviderInstances(): void
    {
        if (!$this->tableExists('notification_provider_instances')) {
            return;
        }

        $this->db->exec("
            UPDATE notification_provider_instances
            SET public_reference = CONCAT(
                    'npi_',
                    LOWER(LPAD(HEX(id), 24, '0'))
                )
            WHERE public_reference IS NULL
               OR public_reference = ''
        ");

        $this->db->exec("
            UPDATE notification_provider_instances
            SET is_enabled = CASE
                    WHEN status_code = 'active' THEN 1
                    ELSE 0
                END
        ");

        if ($this->columnExists(
            'notification_provider_instances',
            'public_reference'
        )) {
            $this->db->exec("
                ALTER TABLE notification_provider_instances
                MODIFY public_reference
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL
            ");
        }
    }

    private function backfillProviderDefaults(): void
    {
        if (
            !$this->tableExists('notification_provider_defaults')
            || !$this->columnExists(
                'notification_provider_defaults',
                'is_default'
            )
        ) {
            return;
        }

        $this->db->exec("
            UPDATE notification_provider_defaults
            SET is_default = 0
        ");

        $this->db->exec("
            UPDATE notification_provider_defaults AS defaults
            INNER JOIN (
                SELECT
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code,
                    MIN(priority) AS selected_priority
                FROM notification_provider_defaults
                WHERE is_active = 1
                GROUP BY
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code
            ) AS selected
              ON selected.scope_type = defaults.scope_type
             AND selected.scope_reference = defaults.scope_reference
             AND selected.channel_code = defaults.channel_code
             AND selected.purpose_code = defaults.purpose_code
             AND selected.selected_priority = defaults.priority
            SET defaults.is_default = 1
            WHERE defaults.is_active = 1
        ");
    }

    private function addIndexes(): void
    {
        if (
            $this->tableExists('notification_provider_instances')
            && !$this->indexExists(
                'notification_provider_instances',
                'notification_provider_instances_reference_unique'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_provider_instances
                ADD UNIQUE KEY
                    notification_provider_instances_reference_unique (
                        public_reference
                    )
            ");
        }

        if (
            $this->tableExists('notification_provider_instances')
            && !$this->indexExists(
                'notification_provider_instances',
                'notification_provider_instances_enabled_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_provider_instances
                ADD INDEX notification_provider_instances_enabled_index (
                    provider_type_id,
                    is_enabled,
                    priority,
                    id
                )
            ");
        }

        if (
            $this->tableExists('notification_provider_defaults')
            && !$this->indexExists(
                'notification_provider_defaults',
                'notification_provider_defaults_purpose_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_provider_defaults
                ADD INDEX notification_provider_defaults_purpose_index (
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code,
                    is_active,
                    is_default,
                    priority,
                    id
                )
            ");
        }
    }

    private function addForeignKeys(): void
    {
        foreach ([
            [
                'notification_provider_secret_sets',
                'notification_provider_secret_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'CASCADE',
            ],
            [
                'notification_provider_audit_events',
                'notification_provider_audit_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'SET NULL',
            ],
            [
                'notification_webhook_endpoints',
                'notification_webhook_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'CASCADE',
            ],
            [
                'notification_webhook_events',
                'notification_webhook_event_endpoint_fk',
                'webhook_endpoint_id',
                'notification_webhook_endpoints',
                'id',
                'CASCADE',
            ],
        ] as $foreignKey) {
            $this->addForeignKeyIfPossible(...$foreignKey);
        }
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
            || !$this->columnExists($referenceTable, $referenceColumn)
            || $this->foreignKeyExists($table, $constraint)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
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
            REFERENCES {$referenceTable} ({$referenceColumn})
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
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ");
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
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
        $statement->execute([$table, $constraint]);

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
        $statement->execute([$table, $column]);

        return strtolower(trim(
            (string) $statement->fetchColumn()
        ));
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower(trim(
            (string) $statement->fetchColumn()
        )) === 'innodb';
    }
}
