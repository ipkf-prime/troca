<?php

namespace IPKF\Database\Migrations;

use RuntimeException;

class AddRegistryNumberReservationIdempotency extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists(
                'registry_number_reservations'
            )
        ) {
            return;
        }

        if (
            !$this->columnExists(
                'registry_number_reservations',
                'idempotency_key'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    registry_number_reservations

                ADD COLUMN
                    idempotency_key
                    VARCHAR(100)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL
                    AFTER public_reference
            ");
        }

        if (!$this->idempotencyColumnValid()) {
            throw new RuntimeException(
                'registry_number_res_idempotency_column_invalid'
            );
        }

        $blankKeyCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM registry_number_reservations

                    WHERE idempotency_key
                            IS NOT NULL
                      AND idempotency_key = ''
                ")
                ->fetchColumn();

        if ($blankKeyCount !== 0) {
            throw new RuntimeException(
                'registry_number_res_idempotency_blank_key'
            );
        }

        $duplicateCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM (
                        SELECT
                            root_organization_id,
                            idempotency_key

                        FROM
                            registry_number_reservations

                        WHERE idempotency_key
                                IS NOT NULL

                        GROUP BY
                            root_organization_id,
                            idempotency_key

                        HAVING COUNT(*) > 1
                    ) duplicate_keys
                ")
                ->fetchColumn();

        if ($duplicateCount !== 0) {
            throw new RuntimeException(
                'registry_number_res_idempotency_duplicate_key'
            );
        }

        if (
            !$this->indexExists(
                'registry_number_reservations',
                'registry_number_res_idempotency_unique'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    registry_number_reservations

                ADD UNIQUE KEY
                    registry_number_res_idempotency_unique (
                        root_organization_id,
                        idempotency_key
                    )
            ");
        }

        if (!$this->idempotencyIndexValid()) {
            throw new RuntimeException(
                'registry_number_res_idempotency_index_invalid'
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

    private function idempotencyColumnValid(): bool
    {
        $statement =
            $this->db->query("
                SELECT
                    COLUMN_TYPE,
                    IS_NULLABLE,
                    CHARACTER_SET_NAME,
                    COLLATION_NAME

                FROM information_schema.columns

                WHERE table_schema = DATABASE()
                  AND table_name =
                        'registry_number_reservations'
                  AND column_name =
                        'idempotency_key'

                LIMIT 1
            ");

        $row =
            $statement->fetch(
                \PDO::FETCH_ASSOC
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
            ) === 'varchar(100)'
            && (string) (
                $row['IS_NULLABLE']
                ?? ''
            ) === 'YES'
            && strtolower(
                (string) (
                    $row[
                        'CHARACTER_SET_NAME'
                    ]
                    ?? ''
                )
            ) === 'ascii'
            && strtolower(
                (string) (
                    $row[
                        'COLLATION_NAME'
                    ]
                    ?? ''
                )
            ) === 'ascii_bin';
    }

    private function idempotencyIndexValid(): bool
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
                        'registry_number_reservations'
                  AND index_name =
                        'registry_number_res_idempotency_unique'

                ORDER BY SEQ_IN_INDEX
            ");

        $rows =
            $statement->fetchAll(
                \PDO::FETCH_ASSOC
            ) ?: [];

        if (count($rows) !== 2) {
            return false;
        }

        return
            (int) $rows[0][
                'NON_UNIQUE'
            ] === 0
            && (int) $rows[1][
                'NON_UNIQUE'
            ] === 0
            && (int) $rows[0][
                'SEQ_IN_INDEX'
            ] === 1
            && (int) $rows[1][
                'SEQ_IN_INDEX'
            ] === 2
            && (string) $rows[0][
                'COLUMN_NAME'
            ] === 'root_organization_id'
            && (string) $rows[1][
                'COLUMN_NAME'
            ] === 'idempotency_key';
    }
}
