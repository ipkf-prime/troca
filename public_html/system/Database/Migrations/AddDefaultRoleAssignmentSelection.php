<?php

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;

class AddDefaultRoleAssignmentSelection extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists(
                'user_role_assignments'
            )
        ) {
            return;
        }

        if (
            !$this->columnExists(
                'user_role_assignments',
                'is_default'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    user_role_assignments

                ADD COLUMN
                    is_default
                    TINYINT(1)
                    NOT NULL
                    DEFAULT 0
                    AFTER is_active
            ");
        }

        $invalidFlags =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM user_role_assignments

                    WHERE is_default
                        NOT IN (0, 1)
                ")
                ->fetchColumn();

        if ($invalidFlags !== 0) {
            throw new RuntimeException(
                'default_role_assignment_invalid_flag'
            );
        }

        $duplicates =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM (
                        SELECT user_id

                        FROM user_role_assignments

                        WHERE is_default = 1

                        GROUP BY user_id

                        HAVING COUNT(*) > 1
                    ) duplicate_defaults
                ")
                ->fetchColumn();

        if ($duplicates !== 0) {
            throw new RuntimeException(
                'default_role_assignment_duplicate_user'
            );
        }

        if (
            !$this->columnExists(
                'user_role_assignments',
                'default_user_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    user_role_assignments

                ADD COLUMN
                    default_user_id
                    BIGINT UNSIGNED
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN is_default = 1
                                THEN user_id
                            ELSE NULL
                        END
                    )
                    STORED
                    AFTER is_default
            ");
        }

        if (
            !$this->indexExists(
                'user_role_assignments',
                'user_role_assignments_one_default_per_user'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    user_role_assignments

                ADD UNIQUE KEY
                    user_role_assignments_one_default_per_user (
                        default_user_id
                    )
            ");
        }

        if (!$this->isDefaultColumnValid()) {
            throw new RuntimeException(
                'default_role_assignment_column_invalid'
            );
        }

        if (!$this->generatedColumnValid()) {
            throw new RuntimeException(
                'default_role_assignment_generated_column_invalid'
            );
        }

        if (!$this->uniqueIndexValid()) {
            throw new RuntimeException(
                'default_role_assignment_unique_index_invalid'
            );
        }
    }

    public function down(): void
    {
    }

    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.tables

                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
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

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.statistics

                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND index_name = ?
            ");

        $statement->execute([
            $table,
            $index,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function isDefaultColumnValid(): bool
    {
        $statement =
            $this->db->query("
                SELECT
                    COLUMN_TYPE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT

                FROM information_schema.columns

                WHERE table_schema = DATABASE()
                  AND table_name =
                        'user_role_assignments'
                  AND column_name =
                        'is_default'

                LIMIT 1
            ");

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return false;
        }

        return
            strtolower(
                (string) (
                    $row['COLUMN_TYPE']
                    ?? ''
                )
            ) === 'tinyint(1)'
            && (string) (
                $row['IS_NULLABLE']
                ?? ''
            ) === 'NO'
            && (string) (
                $row['COLUMN_DEFAULT']
                ?? ''
            ) === '0';
    }

    private function generatedColumnValid(): bool
    {
        $statement =
            $this->db->query("
                SELECT
                    COLUMN_TYPE,
                    EXTRA,
                    GENERATION_EXPRESSION

                FROM information_schema.columns

                WHERE table_schema = DATABASE()
                  AND table_name =
                        'user_role_assignments'
                  AND column_name =
                        'default_user_id'

                LIMIT 1
            ");

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return false;
        }

        $extra =
            strtolower(
                (string) (
                    $row['EXTRA']
                    ?? ''
                )
            );

        $expression =
            strtolower(
                preg_replace(
                    '/\s+/',
                    ' ',
                    (string) (
                        $row[
                            'GENERATION_EXPRESSION'
                        ]
                        ?? ''
                    )
                )
            );

        return
            str_contains(
                strtolower(
                    (string) (
                        $row['COLUMN_TYPE']
                        ?? ''
                    )
                ),
                'bigint'
            )
            && str_contains(
                $extra,
                'generated'
            )
            && str_contains(
                $expression,
                'is_default'
            )
            && str_contains(
                $expression,
                'user_id'
            );
    }

    private function uniqueIndexValid(): bool
    {
        $statement =
            $this->db->query("
                SELECT
                    NON_UNIQUE,
                    SEQ_IN_INDEX,
                    COLUMN_NAME

                FROM information_schema.statistics

                WHERE table_schema = DATABASE()
                  AND table_name =
                        'user_role_assignments'
                  AND index_name =
                        'user_role_assignments_one_default_per_user'

                ORDER BY SEQ_IN_INDEX
            ");

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        return
            count($rows) === 1
            && (int) $rows[0][
                'NON_UNIQUE'
            ] === 0
            && (int) $rows[0][
                'SEQ_IN_INDEX'
            ] === 1
            && (string) $rows[0][
                'COLUMN_NAME'
            ] === 'default_user_id';
    }
}
