<?php

namespace IPKF\Database\Migrations;

class AddRegistryBookPublicReference extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('registry_books')) {
            return;
        }

        if (
            !$this->columnExists(
                'registry_books',
                'public_reference'
            )
        ) {
            $this->db->exec("
                ALTER TABLE registry_books
                ADD COLUMN public_reference
                    VARCHAR(40)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL
                AFTER id
            ");
        }

        $this->db->exec("
            UPDATE registry_books

            SET public_reference =
                CONCAT(
                    'RBOOK-MIG-',
                    LPAD(
                        CAST(id AS CHAR),
                        20,
                        '0'
                    )
                )

            WHERE public_reference IS NULL
               OR TRIM(public_reference) = ''
        ");

        $duplicateCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)
                    FROM (
                        SELECT public_reference
                        FROM registry_books
                        GROUP BY public_reference
                        HAVING COUNT(*) > 1
                    ) duplicates
                ")
                ->fetchColumn();

        if ($duplicateCount !== 0) {
            throw new \RuntimeException(
                'registry_book_public_reference_duplicate'
            );
        }

        $emptyCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)
                    FROM registry_books
                    WHERE public_reference IS NULL
                       OR TRIM(public_reference) = ''
                ")
                ->fetchColumn();

        if ($emptyCount !== 0) {
            throw new \RuntimeException(
                'registry_book_public_reference_missing'
            );
        }

        $this->db->exec("
            ALTER TABLE registry_books
            MODIFY COLUMN public_reference
                VARCHAR(40)
                CHARACTER SET ascii
                COLLATE ascii_bin
                NOT NULL
        ");

        if (
            !$this->indexExists(
                'registry_books',
                'registry_books_reference_unique'
            )
        ) {
            $this->db->exec("
                ALTER TABLE registry_books
                ADD UNIQUE KEY
                    registry_books_reference_unique (
                        public_reference
                    )
            ");
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
}
