<?php

namespace IPKF\Database\Migrations;

class RepairPersonPublicReferencesForOnboarding
    extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists(
                'persons'
            )
            ||
            !$this->columnExists(
                'persons',
                'public_reference'
            )
        ) {
            throw new \RuntimeException(
                'persons_public_reference_schema_missing'
            );
        }


        $duplicateCount =
            $this->duplicateCount();

        if ($duplicateCount !== 0) {
            throw new \RuntimeException(
                'persons_public_reference_duplicate_preimage'
            );
        }


        $statement =
            $this->db->query("
                SELECT id

                FROM persons

                WHERE public_reference IS NULL
                   OR TRIM(public_reference) = ''

                ORDER BY id
            ");

        $ids =
            $statement->fetchAll(
                \PDO::FETCH_COLUMN
            ) ?: [];


        $update =
            $this->db->prepare("
                UPDATE persons

                SET
                    public_reference =
                        UUID(),
                    updated_at =
                        CURRENT_TIMESTAMP

                WHERE id = ?

                  AND (
                        public_reference IS NULL
                        OR TRIM(public_reference) = ''
                  )
            ");


        foreach ($ids as $id) {
            $personId =
                (int) $id;

            if ($personId < 1) {
                throw new \RuntimeException(
                    'person_public_reference_backfill_id_invalid'
                );
            }

            $update->execute([
                $personId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new \RuntimeException(
                    'person_public_reference_backfill_write_failed'
                );
            }
        }


        $missingCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM persons

                    WHERE public_reference IS NULL
                       OR TRIM(public_reference) = ''
                ")
                ->fetchColumn();

        if ($missingCount !== 0) {
            throw new \RuntimeException(
                'person_public_reference_backfill_incomplete'
            );
        }


        if ($this->duplicateCount() !== 0) {
            throw new \RuntimeException(
                'persons_public_reference_duplicate_after_backfill'
            );
        }


        if (
            !$this->uniqueSingleColumnIndexExists(
                'persons',
                'public_reference'
            )
        ) {
            $this->db->exec("
                ALTER TABLE persons

                ADD UNIQUE KEY
                    persons_public_reference_unique (
                        public_reference
                    )
            ");
        }


        if (
            !$this->uniqueSingleColumnIndexExists(
                'persons',
                'public_reference'
            )
        ) {
            throw new \RuntimeException(
                'persons_public_reference_unique_index_missing'
            );
        }
    }


    public function down(): void
    {
    }


    private function duplicateCount(): int
    {
        return
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM (
                        SELECT public_reference

                        FROM persons

                        WHERE public_reference
                                IS NOT NULL

                          AND TRIM(
                                public_reference
                              ) <> ''

                        GROUP BY public_reference

                        HAVING COUNT(*) > 1
                    ) duplicated
                ")
                ->fetchColumn();
    }


    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.tables

                WHERE table_schema =
                        DATABASE()

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

                WHERE table_schema =
                        DATABASE()

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


    private function uniqueSingleColumnIndexExists(
        string $table,
        string $column
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)

                FROM information_schema.statistics
                    AS candidate

                WHERE candidate.table_schema =
                        DATABASE()

                  AND candidate.table_name = ?

                  AND candidate.column_name = ?

                  AND candidate.non_unique = 0

                  AND (
                        SELECT COUNT(*)

                        FROM information_schema.statistics
                            AS index_columns

                        WHERE index_columns.table_schema =
                                candidate.table_schema

                          AND index_columns.table_name =
                                candidate.table_name

                          AND index_columns.index_name =
                                candidate.index_name
                  ) = 1
            ");

        $statement->execute([
            $table,
            $column,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }
}
