<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;

/**
 * ROLE_ASSIGNMENT_LIFECYCLE_GOVERNANCE_V1
 *
 * Lifecycle state belongs to the role assignment itself.
 *
 * is_active remains the effective authorization gate:
 *
 *   active                  => is_active = 1
 *   pending_identity        => is_active = 0
 *   pending_scope           => is_active = 0
 *   pending_identity_scope  => is_active = 0
 *   revoked                 => is_active = 0
 *
 * Existing assignments are grandfathered according to their current
 * is_active state only when the lifecycle column is introduced.
 */
class AddRoleAssignmentLifecycleGovernance extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('user_role_assignments')) {
            return;
        }

        $statusAdded = false;

        if (
            !$this->columnExists(
                'user_role_assignments',
                'lifecycle_status_code'
            )
        ) {
            $this->db->exec("
                ALTER TABLE user_role_assignments

                ADD COLUMN lifecycle_status_code
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NOT NULL
                    DEFAULT 'active'
                    AFTER is_default
            ");

            $statusAdded = true;
        }

        $this->addColumnIfMissing(
            'requested_at',
            'TIMESTAMP NULL'
        );

        $this->addColumnIfMissing(
            'eligibility_checked_at',
            'TIMESTAMP NULL'
        );

        $this->addColumnIfMissing(
            'eligible_at',
            'TIMESTAMP NULL'
        );

        $this->addColumnIfMissing(
            'activated_at',
            'TIMESTAMP NULL'
        );

        $this->addColumnIfMissing(
            'activated_by',
            'BIGINT UNSIGNED NULL'
        );

        $this->addColumnIfMissing(
            'revoked_at',
            'TIMESTAMP NULL'
        );

        $this->addColumnIfMissing(
            'revoked_by',
            'BIGINT UNSIGNED NULL'
        );

        if ($statusAdded) {
            /*
             * This is intentionally one-time.
             *
             * Existing active assignments represent the known-good
             * production/dev authorization state and must not suddenly
             * become pending merely because the lifecycle foundation
             * was installed.
             */
            $this->db->exec("
                UPDATE user_role_assignments

                SET
                    lifecycle_status_code =
                        CASE
                            WHEN COALESCE(is_active, 0) = 1
                                THEN 'active'
                            ELSE 'revoked'
                        END,

                    requested_at =
                        COALESCE(
                            requested_at,
                            created_at,
                            updated_at,
                            CURRENT_TIMESTAMP
                        ),

                    activated_at =
                        CASE
                            WHEN COALESCE(is_active, 0) = 1
                                THEN COALESCE(
                                    activated_at,
                                    created_at,
                                    updated_at,
                                    CURRENT_TIMESTAMP
                                )
                            ELSE activated_at
                        END,

                    revoked_at =
                        CASE
                            WHEN COALESCE(is_active, 0) = 0
                                THEN COALESCE(
                                    revoked_at,
                                    updated_at,
                                    created_at,
                                    CURRENT_TIMESTAMP
                                )
                            ELSE revoked_at
                        END
            ");
        }

        if (
            !$this->indexExists(
                'user_role_assignments',
                'user_role_assignments_lifecycle_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE user_role_assignments

                ADD INDEX
                    user_role_assignments_lifecycle_index (
                        user_id,
                        lifecycle_status_code,
                        is_active,
                        role_id
                    )
            ");
        }

        $this->validate();
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * Role assignment lifecycle metadata participates in
         * authorization governance and audit history.
         */
    }

    private function addColumnIfMissing(
        string $column,
        string $definition
    ): void {
        if (
            $this->columnExists(
                'user_role_assignments',
                $column
            )
        ) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE user_role_assignments "
            . "ADD COLUMN `{$column}` {$definition}"
        );
    }

    private function validate(): void
    {
        $required = [
            'lifecycle_status_code',
            'requested_at',
            'eligibility_checked_at',
            'eligible_at',
            'activated_at',
            'activated_by',
            'revoked_at',
            'revoked_by',
        ];

        foreach ($required as $column) {
            if (
                !$this->columnExists(
                    'user_role_assignments',
                    $column
                )
            ) {
                throw new RuntimeException(
                    'role_assignment_lifecycle_column_missing:'
                    . $column
                );
            }
        }

        $invalid =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)
                    FROM user_role_assignments
                    WHERE lifecycle_status_code
                        NOT IN (
                            'active',
                            'pending_identity',
                            'pending_scope',
                            'pending_identity_scope',
                            'revoked'
                        )
                ")
                ->fetchColumn();

        if ($invalid !== 0) {
            throw new RuntimeException(
                'role_assignment_lifecycle_status_invalid'
            );
        }

        if (
            !$this->indexExists(
                'user_role_assignments',
                'user_role_assignments_lifecycle_index'
            )
        ) {
            throw new RuntimeException(
                'role_assignment_lifecycle_index_missing'
            );
        }
    }

    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND INDEX_NAME = ?
            ");

        $statement->execute([
            $table,
            $index,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
