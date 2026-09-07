<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use RuntimeException;

final class ExtendTicketingSlaPolicyScopes extends Migration
{
    public function up(): void
    {
        if (
            !$this->tableExists(
                'ticketing_sla_policies'
            )
        ) {
            throw new RuntimeException(
                'ticketing_sla_policies_missing'
            );
        }


        if (
            !$this->columnExists(
                'ticketing_sla_policies',
                'service_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_sla_policies

                ADD COLUMN
                    service_id
                    BIGINT UNSIGNED NULL

                AFTER project_id
            ");
        }


        if (
            !$this->columnExists(
                'ticketing_sla_policies',
                'topic_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_sla_policies

                ADD COLUMN
                    topic_id
                    BIGINT UNSIGNED NULL

                AFTER service_id
            ");
        }


        if (
            !$this->indexExists(
                'ticketing_sla_policies',
                'ticketing_sla_policy_resolution_v2_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_sla_policies

                ADD INDEX
                    ticketing_sla_policy_resolution_v2_index
                    (
                        priority_code,
                        topic_id,
                        service_id,
                        queue_id,
                        project_id,
                        status,
                        effective_from_at,
                        id
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
            (int) $statement->fetchColumn()
            === 1;
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
            === 1;
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
