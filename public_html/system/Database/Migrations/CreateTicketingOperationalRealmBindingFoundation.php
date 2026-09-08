<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;


/**
 * TICKETING_OPERATIONAL_REALM_BINDING_FOUNDATION_V1
 *
 * Safe deployment bridge:
 *
 * - adds nullable Realm identity to Ticket and Assignment;
 * - backfills current Ticket Realm from operational topology;
 * - backfills Assignment historical Realm from its own topology;
 * - installs same-Realm foreign keys and Realm-aware indexes;
 * - installs new Realm-scoped topology unique indexes;
 * - deliberately leaves realm_id nullable until the writer deployment.
 */
final class CreateTicketingOperationalRealmBindingFoundation
    extends Migration
{
    public function up(): void
    {
        $this->extendOperationalTables();

        $this->backfillTickets();
        $this->backfillAssignments();

        $this->ensureTicketIndexes();
        $this->ensureAssignmentIndexes();

        $this->ensureRealmScopedTopologyUniqueIndexes();

        $this->ensureTicketRealmConstraints();
        $this->ensureAssignmentRealmConstraints();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         */
    }


    private function extendOperationalTables(): void
    {
        if (
            !$this->columnExists(
                'ticketing_tickets',
                'realm_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_tickets

                ADD COLUMN
                    realm_id
                    BIGINT UNSIGNED
                    NULL

                AFTER
                    support_project_id
            ");
        }


        if (
            !$this->columnExists(
                'ticketing_assignments',
                'realm_id'
            )
        ) {
            $this->db->exec("
                ALTER TABLE
                    ticketing_assignments

                ADD COLUMN
                    realm_id
                    BIGINT UNSIGNED
                    NULL

                AFTER
                    ticket_id
            ");
        }
    }


    private function backfillTickets(): void
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


    private function backfillAssignments(): void
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


    private function ensureTicketIndexes(): void
    {
        $this->ensureUniqueIndex(
            'ticketing_tickets',
            'ticketing_tickets_id_realm_unique',
            'id, realm_id'
        );

        $this->ensureIndex(
            'ticketing_tickets',
            'ticketing_tickets_realm_status_index',
            'realm_id, status_code, archived_at, last_activity_at, id'
        );
    }


    private function ensureAssignmentIndexes(): void
    {
        $this->ensureIndex(
            'ticketing_assignments',
            'ticketing_assignments_ticket_realm_active_index',
            'ticket_id, realm_id, unassigned_at'
        );

        $this->ensureIndex(
            'ticketing_assignments',
            'ticketing_assignments_realm_member_active_index',
            'realm_id, project_member_id, unassigned_at, ticket_id'
        );
    }


    private function ensureRealmScopedTopologyUniqueIndexes(): void
    {
        $this->ensureUniqueIndex(
            'ticketing_support_layers',
            'ticketing_layers_project_realm_code_unique',
            'project_id, realm_id, code'
        );

        $this->ensureUniqueIndex(
            'ticketing_support_layers',
            'ticketing_layers_project_realm_rank_unique',
            'project_id, realm_id, rank_order'
        );

        $this->ensureUniqueIndex(
            'ticketing_support_nodes',
            'ticketing_nodes_project_realm_code_unique',
            'project_id, realm_id, code'
        );

        $this->ensureUniqueIndex(
            'ticketing_support_node_relations',
            'ticketing_node_rel_project_realm_unique',
            'project_id, realm_id, parent_node_id, child_node_id, relation_type_code'
        );

        $this->ensureUniqueIndex(
            'ticketing_support_teams',
            'ticketing_teams_project_realm_code_unique',
            'project_id, realm_id, code'
        );

        $this->ensureUniqueIndex(
            'ticketing_support_queues',
            'ticketing_queues_project_realm_code_unique',
            'project_id, realm_id, code'
        );
    }


    private function ensureTicketRealmConstraints(): void
    {
        $this->ensureConstraint(
            'ticketing_tickets',
            'ticketing_tickets_project_realm_fk',
            "
                FOREIGN KEY
                    (
                        support_project_id,
                        realm_id
                    )

                REFERENCES
                    ticketing_support_realms
                    (
                        project_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_tickets',
            'ticketing_tickets_realm_layer_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        current_support_layer_id
                    )

                REFERENCES
                    ticketing_support_layers
                    (
                        realm_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_tickets',
            'ticketing_tickets_realm_node_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        current_support_node_id
                    )

                REFERENCES
                    ticketing_support_nodes
                    (
                        realm_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_tickets',
            'ticketing_tickets_realm_queue_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        current_support_queue_id
                    )

                REFERENCES
                    ticketing_support_queues
                    (
                        realm_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_tickets',
            'ticketing_tickets_realm_team_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        current_support_team_id
                    )

                REFERENCES
                    ticketing_support_teams
                    (
                        realm_id,
                        id
                    )
            "
        );
    }


    private function ensureAssignmentRealmConstraints(): void
    {
        /*
         * TICKETING_ASSIGNMENT_REALM_HISTORICAL_PROVENANCE_V1
         *
         * Assignment.realm_id is historical operational provenance.
         * It must remain stable if Ticket.realm_id changes later by
         * an explicit Cross-Realm Handoff.
         */
        $this->ensureConstraint(
            'ticketing_assignments',
            'ticketing_assignments_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id
                    )

                REFERENCES
                    ticketing_support_realms
                    (
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_assignments',
            'ticketing_assignments_realm_node_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        support_node_id
                    )

                REFERENCES
                    ticketing_support_nodes
                    (
                        realm_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_assignments',
            'ticketing_assignments_realm_queue_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        support_queue_id
                    )

                REFERENCES
                    ticketing_support_queues
                    (
                        realm_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_assignments',
            'ticketing_assignments_realm_team_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        support_team_id
                    )

                REFERENCES
                    ticketing_support_teams
                    (
                        realm_id,
                        id
                    )
            "
        );
    }


    private function ensureUniqueIndex(
        string $table,
        string $name,
        string $columns
    ): void {

        if (
            $this->indexExists(
                $table,
                $name
            )
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE
                {$table}

            ADD UNIQUE INDEX
                {$name}
                ({$columns})
        ");
    }


    private function ensureIndex(
        string $table,
        string $name,
        string $columns
    ): void {

        if (
            $this->indexExists(
                $table,
                $name
            )
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE
                {$table}

            ADD INDEX
                {$name}
                ({$columns})
        ");
    }


    private function ensureConstraint(
        string $table,
        string $name,
        string $definition
    ): void {

        if (
            $this->constraintExists(
                $table,
                $name
            )
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE
                {$table}

            ADD CONSTRAINT
                {$name}

            {$definition}

            ON DELETE RESTRICT
            ON UPDATE RESTRICT
        ");
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


    private function constraintExists(
        string $table,
        string $constraint
    ): bool {

        $statement =
            $this->db->prepare("
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

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
