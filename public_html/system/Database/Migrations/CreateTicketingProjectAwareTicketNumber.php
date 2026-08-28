<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;

final class CreateTicketingProjectAwareTicketNumber
    extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists(
                'ticketing_tickets'
            )
        ) {
            return;
        }

        if (
            !$this->columnExists(
                'ticketing_tickets',
                'ticket_number'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD COLUMN ticket_number
                    VARCHAR(80)
                    CHARACTER SET ascii
                    COLLATE ascii_bin
                    NULL
                    AFTER public_reference
            ");
        }

        $projectJoin =
            $this->tableExists(
                'ticketing_support_projects'
            );

        $sql = $projectJoin
            ? "
                SELECT
                    t.id,
                    t.ticket_number,
                    p.code AS project_code
                FROM ticketing_tickets t
                LEFT JOIN ticketing_support_projects p
                    ON p.id = t.support_project_id
                ORDER BY t.id
            "
            : "
                SELECT
                    t.id,
                    t.ticket_number,
                    NULL AS project_code
                FROM ticketing_tickets t
                ORDER BY t.id
            ";

        $rows =
            $this->db
                ->query($sql)
                ->fetchAll(
                    PDO::FETCH_ASSOC
                )
            ?: [];

        $update =
            $this->db->prepare("
                UPDATE ticketing_tickets
                SET ticket_number = ?
                WHERE id = ?
                  AND (
                        ticket_number IS NULL
                        OR ticket_number = ''
                  )
            ");

        foreach ($rows as $row) {
            if (
                trim(
                    (string) (
                        $row['ticket_number']
                        ?? ''
                    )
                ) !== ''
            ) {
                continue;
            }

            $update->execute([
                $this->number(
                    (string) (
                        $row['project_code']
                        ?? ''
                    ),
                    (int) $row['id']
                ),
                (int) $row['id'],
            ]);
        }

        if (
            !$this->indexExists(
                'ticketing_tickets',
                'ticketing_tickets_number_unique'
            )
        ) {
            $this->db->exec("
                ALTER TABLE ticketing_tickets
                ADD UNIQUE KEY
                    ticketing_tickets_number_unique
                    (ticket_number)
            ");
        }
    }


    public function down(): void
    {
    }


    private function number(
        string $projectCode,
        int $id
    ): string {
        $prefix =
            strtoupper(
                trim($projectCode)
            );

        $prefix =
            preg_replace(
                '/[^A-Z0-9]+/',
                '-',
                $prefix
            )
            ?? '';

        $prefix =
            trim(
                $prefix,
                '-'
            );

        if ($prefix === '') {
            $prefix = 'TKT';
        }

        $prefix =
            substr(
                $prefix,
                0,
                40
            );

        return
            $prefix
            . '-'
            . str_pad(
                (string) $id,
                6,
                '0',
                STR_PAD_LEFT
            );
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
            (int) $statement->fetchColumn()
            > 0;
    }
}
