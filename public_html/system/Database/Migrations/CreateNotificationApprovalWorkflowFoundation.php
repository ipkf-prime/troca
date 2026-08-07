<?php

namespace IPKF\Database\Migrations;

class CreateNotificationApprovalWorkflowFoundation extends Migration
{
    private const PERMISSIONS = [
        [
            'notifications.approvals.view',
            'view',
            'مشاهده درخواست‌های تأیید اعلان',
            'مشاهده کارتابل و جزئیات درخواست‌های تأیید اعلان.',
            80,
            0,
        ],
        [
            'notifications.approvals.decide',
            'decide',
            'بررسی و تصمیم‌گیری درخواست‌های اعلان',
            'تأیید یا رد درخواست‌های ارسال اعلان با ثبت دلیل.',
            81,
            1,
        ],
        [
            'notifications.approvals.manage',
            'manage',
            'مدیریت گردش تأیید اعلان‌ها',
            'مدیریت قواعد، مراحل و عملیات اجرایی گردش تأیید اعلان.',
            82,
            1,
        ],
        [
            'notifications.approvals.cancel_own',
            'cancel_own',
            'لغو درخواست تأیید خود',
            'لغو درخواست ثبت‌شده توسط کاربر تا پیش از تصمیم نهایی.',
            83,
            0,
        ],
    ];

    public function up(): void
    {
        $userIdType = $this->referenceColumnType(
            'users',
            'id',
            'BIGINT UNSIGNED'
        );
        $providerIdType = $this->referenceColumnType(
            'notification_provider_instances',
            'id',
            'BIGINT UNSIGNED'
        );
        $assetIdType = $this->referenceColumnType(
            'notification_media_assets',
            'id',
            'BIGINT UNSIGNED'
        );

        $this->createTables(
            $userIdType,
            $providerIdType,
            $assetIdType
        );
        $this->ensurePermissions();
        $this->assignSuperAdminPermissions();
        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function createTables(
        string $userIdType,
        string $providerIdType,
        string $assetIdType
    ): void {
        $options =
            'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 '
            . 'COLLATE=utf8mb4_unicode_ci';

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_approval_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                idempotency_key VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                requester_user_id {$userIdType}
                    NOT NULL,
                requester_scope_type VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'global',
                requester_scope_reference
                    VARCHAR(190)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT '*',
                requester_context_json LONGTEXT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'draft',
                approval_mode_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'single',
                current_step_order INT UNSIGNED NULL,
                total_steps INT UNSIGNED
                    NOT NULL DEFAULT 0,
                message_type_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'text',
                purpose_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'general',
                priority_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'normal',
                subject VARCHAR(500) NULL,
                body LONGTEXT NOT NULL,
                channels_json LONGTEXT NOT NULL,
                request_reason VARCHAR(1000) NULL,
                payload_checksum_sha256 CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                expires_at DATETIME NULL,
                submitted_at DATETIME NULL,
                approved_at DATETIME NULL,
                rejected_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                dispatch_started_at DATETIME NULL,
                dispatched_at DATETIME NULL,
                failed_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_approval_requests_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_approval_requests_idempotency_unique
                    (idempotency_key),
                INDEX
                    notification_approval_requests_requester_index
                    (requester_user_id, status_code, id),
                INDEX
                    notification_approval_requests_queue_index
                    (status_code, submitted_at, id),
                INDEX
                    notification_approval_requests_expiry_index
                    (status_code, expires_at, id)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_approval_targets (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                request_id BIGINT UNSIGNED NOT NULL,
                source_type VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                recipient_user_id {$userIdType} NULL,
                recipient_user_reference
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NULL,
                recipient_title VARCHAR(255) NOT NULL,
                channel_code VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                destination_snapshot VARCHAR(500)
                    NOT NULL,
                destination_masked VARCHAR(500)
                    NOT NULL,
                destination_hash CHAR(64)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                provider_instance_id
                    {$providerIdType} NULL,
                provider_type_code VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NULL,
                provider_title_snapshot
                    VARCHAR(255) NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'pending',
                sort_order INT NOT NULL DEFAULT 0,
                error_code VARCHAR(190) NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_approval_targets_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_approval_targets_destination_unique
                    (
                        request_id,
                        channel_code,
                        destination_hash
                    ),
                INDEX
                    notification_approval_targets_request_index
                    (request_id, status_code, sort_order, id),
                INDEX
                    notification_approval_targets_recipient_index
                    (recipient_user_id, request_id, id),
                INDEX
                    notification_approval_targets_provider_index
                    (provider_instance_id, status_code, id)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_approval_steps (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                request_id BIGINT UNSIGNED NOT NULL,
                step_order INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                approval_policy_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'any',
                approver_rule_json LONGTEXT NOT NULL,
                required_decisions INT UNSIGNED
                    NOT NULL DEFAULT 1,
                completed_decisions INT UNSIGNED
                    NOT NULL DEFAULT 0,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL
                    DEFAULT 'waiting',
                activated_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_approval_steps_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_approval_steps_order_unique
                    (request_id, step_order),
                INDEX
                    notification_approval_steps_queue_index
                    (status_code, step_order, id)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_approval_decisions (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                request_id BIGINT UNSIGNED NOT NULL,
                step_id BIGINT UNSIGNED NOT NULL,
                actor_user_id {$userIdType} NOT NULL,
                decision_code VARCHAR(20)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                reason VARCHAR(2000) NULL,
                actor_snapshot_json LONGTEXT NULL,
                decided_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_approval_decisions_actor_unique
                    (step_id, actor_user_id),
                INDEX
                    notification_approval_decisions_request_index
                    (request_id, decided_at, id)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_approval_media_links (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                request_id BIGINT UNSIGNED NOT NULL,
                asset_id {$assetIdType} NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_primary TINYINT(1)
                    NOT NULL DEFAULT 0,
                alt_text VARCHAR(500) NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_approval_media_links_unique
                    (request_id, asset_id),
                INDEX
                    notification_approval_media_links_order_index
                    (request_id, sort_order, id)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_approval_dispatch_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                request_id BIGINT UNSIGNED NOT NULL,
                attempt_number INT UNSIGNED NOT NULL,
                started_by_user_id
                    {$userIdType} NOT NULL,
                status_code VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                total_count INT UNSIGNED
                    NOT NULL DEFAULT 0,
                sent_count INT UNSIGNED
                    NOT NULL DEFAULT 0,
                failed_count INT UNSIGNED
                    NOT NULL DEFAULT 0,
                skipped_count INT UNSIGNED
                    NOT NULL DEFAULT 0,
                result_json LONGTEXT NULL,
                started_at DATETIME NOT NULL,
                completed_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_approval_dispatch_reference_unique
                    (public_reference),
                UNIQUE KEY
                    notification_approval_dispatch_attempt_unique
                    (request_id, attempt_number),
                INDEX
                    notification_approval_dispatch_status_index
                    (status_code, started_at, id)
            ) {$options}
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                notification_approval_events (
                id BIGINT UNSIGNED AUTO_INCREMENT
                    PRIMARY KEY,
                public_reference VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                request_id BIGINT UNSIGNED NOT NULL,
                actor_user_id {$userIdType} NULL,
                event_code VARCHAR(60)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NOT NULL,
                from_status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NULL,
                to_status VARCHAR(30)
                    CHARACTER SET ascii
                    COLLATE ascii_bin NULL,
                reason VARCHAR(2000) NULL,
                metadata_json LONGTEXT NULL,
                happened_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY
                    notification_approval_events_reference_unique
                    (public_reference),
                INDEX
                    notification_approval_events_request_index
                    (request_id, happened_at, id),
                INDEX
                    notification_approval_events_actor_index
                    (actor_user_id, happened_at, id),
                INDEX
                    notification_approval_events_code_index
                    (event_code, happened_at, id)
            ) {$options}
        ");
    }

    private function ensurePermissions(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $statement = $this->db->prepare("
            INSERT INTO permissions (
                code,
                module,
                resource,
                action,
                title,
                description,
                display_group,
                display_type,
                sort_order,
                is_sensitive,
                is_active,
                created_at,
                updated_at
            )
            VALUES (
                ?,
                'communications',
                'approval',
                ?,
                ?,
                ?,
                'تأیید اعلان',
                'operation',
                ?,
                ?,
                1,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                resource = VALUES(resource),
                action = VALUES(action),
                title = VALUES(title),
                description = VALUES(description),
                display_group = VALUES(display_group),
                display_type = VALUES(display_type),
                sort_order = VALUES(sort_order),
                is_sensitive = VALUES(is_sensitive),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::PERMISSIONS as $permission) {
            $statement->execute([
                $permission[0],
                $permission[1],
                $permission[2],
                $permission[3],
                $permission[4],
                $permission[5],
            ]);
        }
    }

    private function assignSuperAdminPermissions(): void
    {
        if (
            !$this->tableExists('roles')
            || !$this->tableExists('permissions')
            || !$this->tableExists('role_permissions')
        ) {
            return;
        }

        $role = $this->db->prepare("
            SELECT id
            FROM roles
            WHERE code = 'super_admin'
            LIMIT 1
        ");
        $role->execute();
        $roleId = (int) $role->fetchColumn();

        if ($roleId < 1) {
            return;
        }

        $permission = $this->db->prepare("
            SELECT id
            FROM permissions
            WHERE code = ?
            LIMIT 1
        ");

        $assign = $this->db->prepare("
            INSERT IGNORE INTO role_permissions (
                role_id,
                permission_id,
                created_at
            )
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");

        foreach (self::PERMISSIONS as $definition) {
            $permission->execute([$definition[0]]);
            $permissionId =
                (int) $permission->fetchColumn();

            if ($permissionId > 0) {
                $assign->execute([
                    $roleId,
                    $permissionId,
                ]);
            }
        }
    }

    private function addForeignKeys(): void
    {
        foreach ([
            [
                'notification_approval_requests',
                'notification_approval_requests_user_fk',
                'requester_user_id',
                'users',
                'id',
                'RESTRICT',
            ],
            [
                'notification_approval_targets',
                'notification_approval_targets_request_fk',
                'request_id',
                'notification_approval_requests',
                'id',
                'CASCADE',
            ],
            [
                'notification_approval_targets',
                'notification_approval_targets_user_fk',
                'recipient_user_id',
                'users',
                'id',
                'SET NULL',
            ],
            [
                'notification_approval_targets',
                'notification_approval_targets_provider_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'SET NULL',
            ],
            [
                'notification_approval_steps',
                'notification_approval_steps_request_fk',
                'request_id',
                'notification_approval_requests',
                'id',
                'CASCADE',
            ],
            [
                'notification_approval_decisions',
                'notification_approval_decisions_request_fk',
                'request_id',
                'notification_approval_requests',
                'id',
                'CASCADE',
            ],
            [
                'notification_approval_decisions',
                'notification_approval_decisions_step_fk',
                'step_id',
                'notification_approval_steps',
                'id',
                'CASCADE',
            ],
            [
                'notification_approval_decisions',
                'notification_approval_decisions_actor_fk',
                'actor_user_id',
                'users',
                'id',
                'RESTRICT',
            ],
            [
                'notification_approval_media_links',
                'notification_approval_media_request_fk',
                'request_id',
                'notification_approval_requests',
                'id',
                'CASCADE',
            ],
            [
                'notification_approval_media_links',
                'notification_approval_media_asset_fk',
                'asset_id',
                'notification_media_assets',
                'id',
                'RESTRICT',
            ],
            [
                'notification_approval_dispatch_runs',
                'notification_approval_dispatch_request_fk',
                'request_id',
                'notification_approval_requests',
                'id',
                'CASCADE',
            ],
            [
                'notification_approval_dispatch_runs',
                'notification_approval_dispatch_actor_fk',
                'started_by_user_id',
                'users',
                'id',
                'RESTRICT',
            ],
            [
                'notification_approval_events',
                'notification_approval_events_request_fk',
                'request_id',
                'notification_approval_requests',
                'id',
                'CASCADE',
            ],
            [
                'notification_approval_events',
                'notification_approval_events_actor_fk',
                'actor_user_id',
                'users',
                'id',
                'SET NULL',
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
            || !$this->columnExists($table, $column)
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

        $type = strtoupper(trim(
            (string) $statement->fetchColumn()
        ));

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

    private function foreignKeyExists(
        string $table,
        string $constraint
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
        ");
        $statement->execute([
            $table,
            $constraint,
        ]);

        return (int) $statement->fetchColumn() > 0;
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

        return strtolower(trim(
            (string) $statement->fetchColumn()
        )) === 'innodb';
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

        return strtolower(trim(
            (string) $statement->fetchColumn()
        ));
    }
}
