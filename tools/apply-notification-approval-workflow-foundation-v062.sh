#!/usr/bin/env bash
set -Eeuo pipefail

REPO="${1:-}"
EXPECTED_BRANCH="v0.6.2-notification-approval-workflow-dev"
EXPECTED_HEAD="6074851"

if [[ -z "$REPO" ]]; then
    echo "Usage: bash apply-notification-approval-workflow-foundation-v062.sh /path/to/repo"
    exit 2
fi

cd "$REPO"

CURRENT_BRANCH="$(git branch --show-current)"
CURRENT_HEAD="$(git rev-parse --short HEAD)"

if [[ "$CURRENT_BRANCH" != "$EXPECTED_BRANCH" ]]; then
    echo "Unexpected branch: $CURRENT_BRANCH"
    echo "Expected branch: $EXPECTED_BRANCH"
    exit 3
fi

if [[ "$CURRENT_HEAD" != "$EXPECTED_HEAD" ]]; then
    echo "Unexpected HEAD: $CURRENT_HEAD"
    echo "Expected HEAD: $EXPECTED_HEAD"
    exit 4
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "Working tree or index is not clean. Patch stopped."
    git status --short --branch
    exit 5
fi

MIGRATION_FILE="public_html/system/Database/Migrations/CreateNotificationApprovalWorkflowFoundation.php"
STATE_MACHINE_FILE="public_html/app/Services/NotificationApprovalStateMachine.php"
MIGRATE_FILE="public_html/public/migrate.php"
TEST_FILE="tests/NotificationApprovalWorkflowFoundationTest.php"
TOOL_FILE="tools/apply-notification-approval-workflow-foundation-v062.sh"

cleanup() {
    local status=$?

    if [[ $status -ne 0 ]]; then
        echo
        echo "PATCH FAILED; RESTORING CLEAN TREE"

        git restore --staged --worktree -- \
            "$MIGRATE_FILE" \
            "$MIGRATION_FILE" \
            "$STATE_MACHINE_FILE" \
            "$TEST_FILE" \
            "$TOOL_FILE" \
            2>/dev/null || true

        rm -f -- \
            "$MIGRATION_FILE" \
            "$STATE_MACHINE_FILE" \
            "$TEST_FILE" \
            "$TOOL_FILE"

        git restore --staged -- \
            "$MIGRATION_FILE" \
            "$STATE_MACHINE_FILE" \
            "$TEST_FILE" \
            "$TOOL_FILE" \
            2>/dev/null || true
    fi

    exit $status
}
trap cleanup EXIT

echo
echo "=== Create Approval State Machine ==="

cat > "$STATE_MACHINE_FILE" <<'PHP'
<?php

namespace App\Services;

use DomainException;

final class NotificationApprovalStateMachine
{
    public const DRAFT = 'draft';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const DISPATCHING = 'dispatching';
    public const DISPATCHED = 'dispatched';
    public const PARTIALLY_DISPATCHED =
        'partially_dispatched';
    public const FAILED = 'failed';

    private const TRANSITIONS = [
        self::DRAFT => [
            self::PENDING,
            self::CANCELLED,
        ],
        self::PENDING => [
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
            self::EXPIRED,
        ],
        self::APPROVED => [
            self::DISPATCHING,
        ],
        self::DISPATCHING => [
            self::DISPATCHED,
            self::PARTIALLY_DISPATCHED,
            self::FAILED,
        ],
        self::PARTIALLY_DISPATCHED => [
            self::DISPATCHING,
        ],
        self::FAILED => [
            self::DISPATCHING,
            self::CANCELLED,
        ],
        self::REJECTED => [],
        self::CANCELLED => [],
        self::EXPIRED => [],
        self::DISPATCHED => [],
    ];

    private const LABELS = [
        self::DRAFT => 'پیش‌نویس',
        self::PENDING => 'در انتظار تأیید',
        self::APPROVED => 'تأییدشده',
        self::REJECTED => 'ردشده',
        self::CANCELLED => 'لغوشده',
        self::EXPIRED => 'منقضی‌شده',
        self::DISPATCHING => 'در حال ارسال',
        self::DISPATCHED => 'ارسال‌شده',
        self::PARTIALLY_DISPATCHED =>
            'ارسال ناقص',
        self::FAILED => 'ناموفق',
    ];

    public function statuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public function label(string $status): string
    {
        $this->assertKnownStatus($status);

        return self::LABELS[$status];
    }

    public function canTransition(
        string $from,
        string $to
    ): bool {
        $this->assertKnownStatus($from);
        $this->assertKnownStatus($to);

        return in_array(
            $to,
            self::TRANSITIONS[$from],
            true
        );
    }

    public function assertTransition(
        string $from,
        string $to
    ): void {
        if (!$this->canTransition($from, $to)) {
            throw new DomainException(
                'notification_approval_transition_invalid'
            );
        }
    }

    public function isTerminal(string $status): bool
    {
        $this->assertKnownStatus($status);

        return self::TRANSITIONS[$status] === [];
    }

    public function isDecisionPending(
        string $status
    ): bool {
        $this->assertKnownStatus($status);

        return $status === self::PENDING;
    }

    public function isDispatchable(
        string $status
    ): bool {
        $this->assertKnownStatus($status);

        return in_array(
            $status,
            [
                self::APPROVED,
                self::PARTIALLY_DISPATCHED,
                self::FAILED,
            ],
            true
        );
    }

    private function assertKnownStatus(
        string $status
    ): void {
        if (!array_key_exists(
            $status,
            self::TRANSITIONS
        )) {
            throw new DomainException(
                'notification_approval_status_invalid'
            );
        }
    }
}
PHP

echo "CREATED: $STATE_MACHINE_FILE"

echo
echo "=== Create Approval Workflow Migration ==="

cat > "$MIGRATION_FILE" <<'PHP'
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
PHP

echo "CREATED: $MIGRATION_FILE"

echo
echo "=== Register Migration ==="

cat > /tmp/ipkf-register-notification-approval-foundation.pl <<'PERL'
use strict;
use warnings;

local $/;
my $file = shift @ARGV;

open my $in, '<:raw', $file
    or die "Cannot read $file: $!";

my $content = <$in>;
close $in;

my $needle =
    "        new \\IPKF\\Database\\Migrations\\"
    . "CompleteAccessControlCatalogDescriptions(),\n";

my $addition =
    "        new \\IPKF\\Database\\Migrations\\"
    . "CreateNotificationApprovalWorkflowFoundation(),\n";

index($content, $addition) < 0
    or die "Approval workflow migration is already registered.\n";

index($content, $needle) >= 0
    or die "Migration registration anchor was not found.\n";

$content =~ s/\Q$needle\E/$needle$addition/;

open my $out, '>:raw', $file
    or die "Cannot write $file: $!";

print {$out} $content;
close $out;
PERL

perl \
  /tmp/ipkf-register-notification-approval-foundation.pl \
  "$MIGRATE_FILE"

rm -f \
  /tmp/ipkf-register-notification-approval-foundation.pl

echo "UPDATED: $MIGRATE_FILE"

echo
echo "=== Add Foundation Regression Test ==="

cat > "$TEST_FILE" <<'PHP'
<?php

$root = dirname(__DIR__);

require_once $root
    . '/public_html/app/Services/'
    . 'NotificationApprovalStateMachine.php';

$migration = file_get_contents(
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateNotificationApprovalWorkflowFoundation.php'
);

$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($migrate)) {
    fwrite(
        STDERR,
        "Notification approval foundation sources are missing.\n"
    );
    exit(1);
}

$machine =
    new \App\Services\NotificationApprovalStateMachine();

$statuses = [
    'draft',
    'pending',
    'approved',
    'rejected',
    'cancelled',
    'expired',
    'dispatching',
    'dispatched',
    'partially_dispatched',
    'failed',
];

if ($machine->statuses() !== $statuses) {
    fwrite(STDERR, "Approval statuses are incomplete.\n");
    exit(1);
}

$allowedTransitions = [
    ['draft', 'pending'],
    ['pending', 'approved'],
    ['pending', 'rejected'],
    ['pending', 'cancelled'],
    ['pending', 'expired'],
    ['approved', 'dispatching'],
    ['dispatching', 'dispatched'],
    ['dispatching', 'partially_dispatched'],
    ['dispatching', 'failed'],
    ['partially_dispatched', 'dispatching'],
    ['failed', 'dispatching'],
];

foreach ($allowedTransitions as $transition) {
    if (!$machine->canTransition(
        $transition[0],
        $transition[1]
    )) {
        fwrite(
            STDERR,
            "Missing transition: "
            . implode(' -> ', $transition)
            . "\n"
        );
        exit(1);
    }
}

foreach ([
    'rejected',
    'cancelled',
    'expired',
    'dispatched',
] as $terminal) {
    if (!$machine->isTerminal($terminal)) {
        fwrite(
            STDERR,
            "Status is not terminal: {$terminal}\n"
        );
        exit(1);
    }
}

if ($machine->canTransition('pending', 'dispatching')) {
    fwrite(
        STDERR,
        "Pending request bypasses approval.\n"
    );
    exit(1);
}

$invalidTransitionRejected = false;

try {
    $machine->assertTransition(
        'draft',
        'dispatched'
    );
} catch (\DomainException $exception) {
    $invalidTransitionRejected =
        $exception->getMessage()
            === 'notification_approval_transition_invalid';
}

if (!$invalidTransitionRejected) {
    fwrite(
        STDERR,
        "Invalid transition was not rejected.\n"
    );
    exit(1);
}

$tables = [
    'notification_approval_requests',
    'notification_approval_targets',
    'notification_approval_steps',
    'notification_approval_decisions',
    'notification_approval_media_links',
    'notification_approval_dispatch_runs',
    'notification_approval_events',
];

foreach ($tables as $table) {
    if (!str_contains($migration, $table)) {
        fwrite(
            STDERR,
            "Missing approval table: {$table}\n"
        );
        exit(1);
    }
}

$permissions = [
    'notifications.approvals.view',
    'notifications.approvals.decide',
    'notifications.approvals.manage',
    'notifications.approvals.cancel_own',
];

foreach ($permissions as $permission) {
    if (!str_contains($migration, $permission)) {
        fwrite(
            STDERR,
            "Missing approval permission: {$permission}\n"
        );
        exit(1);
    }
}

foreach ([
    'payload_checksum_sha256',
    'approver_rule_json',
    'destination_hash',
    'provider_instance_id',
    'result_json',
    'from_status',
    'to_status',
] as $marker) {
    if (!str_contains($migration, $marker)) {
        fwrite(
            STDERR,
            "Missing snapshot or audit marker: {$marker}\n"
        );
        exit(1);
    }
}

if (
    !str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'CreateNotificationApprovalWorkflowFoundation()'
    )
) {
    fwrite(
        STDERR,
        "Approval workflow migration is not registered.\n"
    );
    exit(1);
}

echo "Notification approval workflow foundation checks passed.\n";
PHP

echo "CREATED: $TEST_FILE"

echo
echo "=== Copy Reproducible Tool ==="

mkdir -p tools
cp "$0" "$TOOL_FILE"
chmod +x "$TOOL_FILE"

echo "CREATED: $TOOL_FILE"

echo
echo "=== Normalize Final Newlines ==="

perl -0pi -e 's/\s*\z/\n/' \
    "$MIGRATION_FILE" \
    "$STATE_MACHINE_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE" \
    "$TOOL_FILE"

echo
echo "=== Cached Validation ==="

if command -v php >/dev/null 2>&1; then
    php -l "$MIGRATION_FILE"
    php -l "$STATE_MACHINE_FILE"
    php -l "$MIGRATE_FILE"
    php "$TEST_FILE"
else
    echo "PHP_NOT_AVAILABLE_ON_WINDOWS=SKIPPED"
fi

echo
echo "=== Approval Foundation Markers ==="

grep -Fn \
    "CreateNotificationApprovalWorkflowFoundation" \
    "$MIGRATION_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE"

grep -Fn \
    "notification_approval_requests" \
    "$MIGRATION_FILE"

grep -Fn \
    "notifications.approvals.decide" \
    "$MIGRATION_FILE"

grep -Fn \
    "PARTIALLY_DISPATCHED" \
    "$STATE_MACHINE_FILE"

echo
echo "=== Stage Patch ==="

git add -- \
    "$MIGRATION_FILE" \
    "$STATE_MACHINE_FILE" \
    "$MIGRATE_FILE" \
    "$TEST_FILE" \
    "$TOOL_FILE"

git diff --cached --check

echo
echo "=== Scope Checks ==="

migration_scope_changed=0

if git diff --cached --name-only | grep -Eq \
    '^public_html/(public/migrate\.php|system/Database/Migrations/)'
then
    migration_scope_changed=1
fi

echo "MIGRATION_SCOPE_CHANGED=$migration_scope_changed"
echo "MIGRATION_REQUIRED=YES"

echo
echo "=== Unstaged Changes Check ==="

unstaged_count="$(git diff --name-only | wc -l | tr -d ' ')"
echo "UNSTAGED_CHANGES=$unstaged_count"

if [[ "$unstaged_count" != "0" ]]; then
    echo "Unexpected unstaged changes detected."
    git status --short
    exit 8
fi

echo
echo "=== Cached Summary ==="

git diff --cached --stat

echo
echo "=== Final Status ==="

git status --short --branch

echo
echo "NOTIFICATION APPROVAL WORKFLOW FOUNDATION ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
