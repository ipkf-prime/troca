<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;

/**
 * TRANSACTIONAL_IDENTITY_ACCESS_GOVERNANCE_WRITE_SET_V1
 *
 * The legacy schema was created while the server default storage
 * engine was MyISAM. Application services nevertheless use PDO
 * transactions across identity and authorization tables.
 *
 * This migration converts only IPKF tables participating in those
 * transactional write sets. It intentionally does NOT change the
 * server/global default storage engine.
 */
class EnsureTransactionalIdentityAccessGovernanceWriteSet extends Migration
{
    private const TABLES = [
        'persons',
        'person_profiles',
        'users',
        'person_contacts',
        'person_addresses',
        'identity_change_requests',
        'user_org_assignments',
        'organization_appointments',

        'roles',
        'role_permissions',
        'role_scope_policies',
        'role_identity_requirements',

        'user_role_assignments',
        'role_assignment_scopes',
        'role_assignment_constraints',
        'user_permission_overrides',

        'access_control_change_logs',
    ];

    public function up(): void
    {
        $this->assertInnoDbAvailable();

        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $engine =
                strtoupper(
                    $this->tableEngine(
                        $table
                    )
                );

            if (
                in_array(
                    $engine,
                    [
                        'INNODB',
                        'XTRADB',
                    ],
                    true
                )
            ) {
                continue;
            }

            $this->assertSafeConversion(
                $table
            );

            $quoted =
                $this->quotedIdentifier(
                    $table
                );

            $this->db->exec(
                "ALTER TABLE {$quoted} ENGINE=InnoDB"
            );

            $after =
                strtoupper(
                    $this->tableEngine(
                        $table
                    )
                );

            if ($after !== 'INNODB') {
                throw new RuntimeException(
                    'transactional_engine_conversion_failed:'
                    . $table
                );
            }
        }

        $this->validate();
    }

    public function down(): void
    {
        /*
         * Intentionally non-destructive.
         *
         * Reverting these tables to MyISAM would silently invalidate
         * transaction guarantees used by identity/access services.
         */
    }

    private function validate(): void
    {
        foreach (self::TABLES as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $engine =
                strtoupper(
                    $this->tableEngine(
                        $table
                    )
                );

            if (
                !in_array(
                    $engine,
                    [
                        'INNODB',
                        'XTRADB',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'non_transactional_governance_table:'
                    . $table
                );
            }
        }
    }

    private function assertInnoDbAvailable(): void
    {
        $support =
            strtoupper(
                (string) (
                    $this->db
                        ->query("
                            SELECT SUPPORT
                            FROM information_schema.ENGINES
                            WHERE ENGINE = 'InnoDB'
                            LIMIT 1
                        ")
                        ->fetchColumn()
                    ?: ''
                )
            );

        if (
            !in_array(
                $support,
                [
                    'YES',
                    'DEFAULT',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'innodb_not_supported'
            );
        }
    }

    private function assertSafeConversion(
        string $table
    ): void {
        $special =
            $this->scalarCount(
                "
                    SELECT COUNT(*)
                    FROM information_schema.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND UPPER(INDEX_TYPE)
                            IN (
                                'FULLTEXT',
                                'SPATIAL'
                            )
                ",
                [
                    $table,
                ]
            );

        $triggers =
            $this->scalarCount(
                "
                    SELECT COUNT(*)
                    FROM information_schema.TRIGGERS
                    WHERE TRIGGER_SCHEMA = DATABASE()
                      AND EVENT_OBJECT_TABLE = ?
                ",
                [
                    $table,
                ]
            );

        $partitions =
            $this->scalarCount(
                "
                    SELECT COUNT(*)
                    FROM information_schema.PARTITIONS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND PARTITION_NAME
                            IS NOT NULL
                ",
                [
                    $table,
                ]
            );

        $foreignKeys =
            $this->scalarCount(
                "
                    SELECT COUNT(*)
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND REFERENCED_TABLE_NAME
                            IS NOT NULL
                      AND (
                            TABLE_NAME = ?
                            OR REFERENCED_TABLE_NAME = ?
                          )
                ",
                [
                    $table,
                    $table,
                ]
            );

        if (
            $special !== 0
            || $triggers !== 0
            || $partitions !== 0
            || $foreignKeys !== 0
        ) {
            throw new RuntimeException(
                'unsafe_transactional_engine_conversion:'
                . $table
            );
        }
    }

    private function scalarCount(
        string $sql,
        array $parameters
    ): int {
        $statement =
            $this->db->prepare(
                $sql
            );

        $statement->execute(
            $parameters
        );

        return
            (int) $statement
                ->fetchColumn();
    }

    private function tableExists(
        string $table
    ): bool {
        return
            $this->scalarCount(
                "
                    SELECT COUNT(*)
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                ",
                [
                    $table,
                ]
            ) > 0;
    }

    private function tableEngine(
        string $table
    ): string {
        $statement =
            $this->db->prepare("
                SELECT ENGINE
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                LIMIT 1
            ");

        $statement->execute([
            $table,
        ]);

        return
            (string) (
                $statement->fetchColumn()
                ?: ''
            );
    }

    private function quotedIdentifier(
        string $identifier
    ): string {
        if (
            preg_match(
                '/^[a-z][a-z0-9_]*$/',
                $identifier
            ) !== 1
        ) {
            throw new RuntimeException(
                'invalid_migration_identifier'
            );
        }

        return
            '`'
            . $identifier
            . '`';
    }
}
