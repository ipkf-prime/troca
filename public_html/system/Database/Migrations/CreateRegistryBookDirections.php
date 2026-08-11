<?php

namespace IPKF\Database\Migrations;

use RuntimeException;

class CreateRegistryBookDirections extends Migration
{
    public function up(): void
    {
        if (!$this->tableExists('registry_books')) {
            return;
        }

        $invalidScopeCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM registry_books

                    WHERE scope_code IS NULL
                       OR scope_code NOT IN (
                            'incoming',
                            'outgoing',
                            'internal',
                            'general'
                       )
                ")
                ->fetchColumn();

        if ($invalidScopeCount !== 0) {
            throw new RuntimeException(
                'registry_book_direction_invalid_legacy_scope'
            );
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS
                registry_book_directions (
                    id BIGINT UNSIGNED
                        AUTO_INCREMENT
                        PRIMARY KEY,

                    registry_book_id
                        BIGINT UNSIGNED
                        NOT NULL,

                    direction_code
                        VARCHAR(30)
                        CHARACTER SET ascii
                        COLLATE ascii_bin
                        NOT NULL,

                    created_at TIMESTAMP NULL,
                    updated_at TIMESTAMP NULL,

                    UNIQUE KEY
                        registry_book_directions_unique (
                            registry_book_id,
                            direction_code
                        ),

                    INDEX
                        registry_book_directions_direction_index (
                            direction_code
                        ),

                    CONSTRAINT
                        registry_book_directions_book_fk
                        FOREIGN KEY (
                            registry_book_id
                        )
                        REFERENCES registry_books (
                            id
                        )
                        ON DELETE CASCADE
                        ON UPDATE RESTRICT
                )
                ENGINE=InnoDB
                DEFAULT CHARSET=utf8mb4
                COLLATE=utf8mb4_unicode_ci
        ");

        /*
         * Backfill legacy single-scope books.
         *
         * Legacy `general` represents all three
         * correspondence directions.
         */

        $this->db->exec("
            INSERT IGNORE INTO
                registry_book_directions (
                    registry_book_id,
                    direction_code,
                    created_at,
                    updated_at
                )

            SELECT
                id,
                'incoming',
                COALESCE(
                    created_at,
                    UTC_TIMESTAMP()
                ),
                COALESCE(
                    updated_at,
                    created_at,
                    UTC_TIMESTAMP()
                )

            FROM registry_books

            WHERE scope_code IN (
                'incoming',
                'general'
            )
        ");

        $this->db->exec("
            INSERT IGNORE INTO
                registry_book_directions (
                    registry_book_id,
                    direction_code,
                    created_at,
                    updated_at
                )

            SELECT
                id,
                'outgoing',
                COALESCE(
                    created_at,
                    UTC_TIMESTAMP()
                ),
                COALESCE(
                    updated_at,
                    created_at,
                    UTC_TIMESTAMP()
                )

            FROM registry_books

            WHERE scope_code IN (
                'outgoing',
                'general'
            )
        ");

        $this->db->exec("
            INSERT IGNORE INTO
                registry_book_directions (
                    registry_book_id,
                    direction_code,
                    created_at,
                    updated_at
                )

            SELECT
                id,
                'internal',
                COALESCE(
                    created_at,
                    UTC_TIMESTAMP()
                ),
                COALESCE(
                    updated_at,
                    created_at,
                    UTC_TIMESTAMP()
                )

            FROM registry_books

            WHERE scope_code IN (
                'internal',
                'general'
            )
        ");

        $missingDirectionCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM registry_books b

                    LEFT JOIN
                        registry_book_directions d
                        ON d.registry_book_id =
                            b.id

                    WHERE d.id IS NULL
                ")
                ->fetchColumn();

        if ($missingDirectionCount !== 0) {
            throw new RuntimeException(
                'registry_book_direction_backfill_incomplete'
            );
        }

        $invalidDirectionCount =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM registry_book_directions

                    WHERE direction_code NOT IN (
                        'incoming',
                        'outgoing',
                        'internal'
                    )
                ")
                ->fetchColumn();

        if ($invalidDirectionCount !== 0) {
            throw new RuntimeException(
                'registry_book_direction_invalid_code'
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
}
