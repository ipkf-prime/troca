<?php

namespace IPKF\Database\Migrations;

class CreateAuthenticationLoginHistoryTable extends Migration
{
    public function up(): void
    {
        $userIdType = $this->referenceColumnType(
            'users',
            'id',
            'BIGINT UNSIGNED'
        );
        $roleAssignmentIdType = $this->referenceColumnType(
            'user_role_assignments',
            'id',
            'BIGINT UNSIGNED'
        );

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS auth_login_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id {$userIdType} NOT NULL,
                role_assignment_id {$roleAssignmentIdType} NULL,
                role_code_snapshot VARCHAR(100) NULL,
                role_title_snapshot VARCHAR(190) NULL,
                auth_method VARCHAR(40)
                    NOT NULL DEFAULT 'session',
                mfa_verified TINYINT(1)
                    NOT NULL DEFAULT 0,
                session_hash CHAR(64) NULL,
                ip_address VARCHAR(64) NULL,
                user_agent TEXT NULL,
                browser_label VARCHAR(190) NULL,
                logged_in_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL
                    DEFAULT CURRENT_TIMESTAMP,
                INDEX auth_login_history_user_time_index (
                    user_id,
                    logged_in_at,
                    id
                ),
                INDEX auth_login_history_role_assignment_index (
                    role_assignment_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addForeignKeyIfPossible(
            'auth_login_history',
            'auth_login_history_user_fk',
            'user_id',
            'users',
            'id',
            'CASCADE'
        );

        $this->addForeignKeyIfPossible(
            'auth_login_history',
            'auth_login_history_role_assignment_fk',
            'role_assignment_id',
            'user_role_assignments',
            'id',
            'SET NULL'
        );
    }

    public function down(): void
    {
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
        $statement->execute([$table, $column]);
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

        $allowedDeleteActions = [
            'CASCADE',
            'SET NULL',
            'RESTRICT',
            'NO ACTION',
        ];
        $onDelete = in_array(
            $onDelete,
            $allowedDeleteActions,
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
        $statement->execute([$table, $column]);

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
