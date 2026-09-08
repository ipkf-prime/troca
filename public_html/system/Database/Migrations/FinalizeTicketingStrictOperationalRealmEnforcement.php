<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;


/**
 * TICKETING_STRICT_OPERATIONAL_REALM_ENFORCEMENT_V1
 *
 * Final strict Realm gate.
 *
 * Executed only after Realm-aware runtime writers are deployed.
 *
 * - catch-up backfills any rows created during the deployment bridge;
 * - validates Ticket / Assignment / Topology Realm integrity;
 * - makes all operational realm_id columns NOT NULL;
 * - removes obsolete Project-only topology uniqueness.
 */
final class FinalizeTicketingStrictOperationalRealmEnforcement
    extends Migration
{
    public function up(): void
    {
        $this->catchUpTickets();
        $this->catchUpAssignments();

        $this->assertReady();

        $this->makeRealmRequired();

        $this->dropProjectOnlyUniqueIndexes();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         */
    }


    private function catchUpTickets(): void
    {
        $this->db->exec("
            UPDATE
                ticketing_tickets t

            INNER JOIN
                ticketing_support_projects p
                ON p.id =
                    t.support_project_id

            LEFT JOIN
                ticketing_support_layers l
                ON l.id =
                    t.current_support_layer_id

            LEFT JOIN
                ticketing_support_nodes n
                ON n.id =
                    t.current_support_node_id

            LEFT JOIN
                ticketing_support_queues q
                ON q.id =
                    t.current_support_queue_id

            LEFT JOIN
                ticketing_support_teams tm
                ON tm.id =
                    t.current_support_team_id

            SET
                t.realm_id =
                    COALESCE(
                        n.realm_id,
                        q.realm_id,
                        tm.realm_id,
                        l.realm_id,
                        p.default_realm_id
                    ),

                t.updated_at =
                    t.updated_at

            WHERE
                t.realm_id IS NULL
        ");
    }


    private function catchUpAssignments(): void
    {
        $this->db->exec("
            UPDATE
                ticketing_assignments a

            INNER JOIN
                ticketing_tickets t
                ON t.id =
                    a.ticket_id

            INNER JOIN
                ticketing_support_projects p
                ON p.id =
                    t.support_project_id

            LEFT JOIN
                ticketing_support_nodes n
                ON n.id =
                    a.support_node_id

            LEFT JOIN
                ticketing_support_queues q
                ON q.id =
                    a.support_queue_id

            LEFT JOIN
                ticketing_support_teams tm
                ON tm.id =
                    a.support_team_id

            SET
                a.realm_id =
                    COALESCE(
                        n.realm_id,
                        q.realm_id,
                        tm.realm_id,
                        t.realm_id,
                        p.default_realm_id
                    )

            WHERE
                a.realm_id IS NULL
        ");
    }


    private function assertReady(): void
    {
        foreach ([
            'ticketing_support_layers',
            'ticketing_support_nodes',
            'ticketing_support_node_relations',
            'ticketing_support_teams',
            'ticketing_support_team_nodes',
            'ticketing_support_queues',
            'ticketing_support_team_queues',
            'ticketing_support_team_members',
            'ticketing_tickets',
            'ticketing_assignments',
        ] as $table) {

            $count =
                (int) $this->db
                    ->query("
                        SELECT COUNT(*)
                        FROM {$table}
                        WHERE realm_id IS NULL
                    ")
                    ->fetchColumn();

            if ($count !== 0) {
                throw new \RuntimeException(
                    'strict_realm_null_rows:'
                    . $table
                    . ':'
                    . $count
                );
            }
        }


        $ticketMismatch =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM ticketing_tickets t

                    INNER JOIN
                        ticketing_support_realms r
                        ON r.id =
                            t.realm_id

                    LEFT JOIN
                        ticketing_support_layers l
                        ON l.id =
                            t.current_support_layer_id

                    LEFT JOIN
                        ticketing_support_nodes n
                        ON n.id =
                            t.current_support_node_id

                    LEFT JOIN
                        ticketing_support_queues q
                        ON q.id =
                            t.current_support_queue_id

                    LEFT JOIN
                        ticketing_support_teams tm
                        ON tm.id =
                            t.current_support_team_id

                    WHERE
                        r.project_id <>
                            t.support_project_id

                        OR
                        (
                            l.id IS NOT NULL
                            AND l.realm_id <>
                                t.realm_id
                        )

                        OR
                        (
                            n.id IS NOT NULL
                            AND n.realm_id <>
                                t.realm_id
                        )

                        OR
                        (
                            q.id IS NOT NULL
                            AND q.realm_id <>
                                t.realm_id
                        )

                        OR
                        (
                            tm.id IS NOT NULL
                            AND tm.realm_id <>
                                t.realm_id
                        )
                ")
                ->fetchColumn();

        if ($ticketMismatch !== 0) {
            throw new \RuntimeException(
                'strict_ticket_realm_mismatch:'
                . $ticketMismatch
            );
        }


        $assignmentMismatch =
            (int) $this->db
                ->query("
                    SELECT COUNT(*)

                    FROM ticketing_assignments a

                    INNER JOIN
                        ticketing_tickets t
                        ON t.id =
                            a.ticket_id

                    INNER JOIN
                        ticketing_support_realms ar
                        ON ar.id =
                            a.realm_id

                    LEFT JOIN
                        ticketing_support_nodes n
                        ON n.id =
                            a.support_node_id

                    LEFT JOIN
                        ticketing_support_queues q
                        ON q.id =
                            a.support_queue_id

                    LEFT JOIN
                        ticketing_support_teams tm
                        ON tm.id =
                            a.support_team_id

                    WHERE
                        /*
                         * TICKETING_ACTIVE_ASSIGNMENT_CURRENT_REALM_V1
                         *
                         * Historical assignments preserve their Realm.
                         * Only the active Assignment must equal the
                         * Ticket current Realm.
                         */
                        ar.project_id <>
                            t.support_project_id

                        OR
                        (
                            a.unassigned_at IS NULL
                            AND a.realm_id <>
                                t.realm_id
                        )

                        OR
                        (
                            n.id IS NOT NULL
                            AND n.realm_id <>
                                a.realm_id
                        )

                        OR
                        (
                            q.id IS NOT NULL
                            AND q.realm_id <>
                                a.realm_id
                        )

                        OR
                        (
                            tm.id IS NOT NULL
                            AND tm.realm_id <>
                                a.realm_id
                        )
                ")
                ->fetchColumn();

        if ($assignmentMismatch !== 0) {
            throw new \RuntimeException(
                'strict_assignment_realm_mismatch:'
                . $assignmentMismatch
            );
        }
    }


    private function makeRealmRequired(): void
    {
        foreach ([
            'ticketing_support_layers',
            'ticketing_support_nodes',
            'ticketing_support_node_relations',
            'ticketing_support_teams',
            'ticketing_support_team_nodes',
            'ticketing_support_queues',
            'ticketing_support_team_queues',
            'ticketing_support_team_members',
            'ticketing_tickets',
            'ticketing_assignments',
        ] as $table) {

            $this->db->exec("
                ALTER TABLE
                    {$table}

                MODIFY COLUMN
                    realm_id
                    BIGINT UNSIGNED
                    NOT NULL
            ");
        }
    }


    private function dropProjectOnlyUniqueIndexes(): void
    {
        $indexes = [
            [
                'ticketing_support_layers',
                'ticketing_layers_project_code_unique',
            ],

            [
                'ticketing_support_layers',
                'ticketing_layers_project_rank_unique',
            ],

            [
                'ticketing_support_nodes',
                'ticketing_nodes_project_code_unique',
            ],

            [
                'ticketing_support_node_relations',
                'ticketing_node_rel_unique',
            ],

            [
                'ticketing_support_teams',
                'ticketing_teams_project_code_unique',
            ],

            [
                'ticketing_support_queues',
                'ticketing_queues_project_code_unique',
            ],
        ];


        foreach (
            $indexes
            as [$table, $index]
        ) {
            if (
                !$this->indexExists(
                    $table,
                    $index
                )
            ) {
                continue;
            }

            $this->db->exec("
                DROP INDEX
                    {$index}

                ON
                    {$table}
            ");
        }
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
