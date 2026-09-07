<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;


/**
 * TICKETING_REALM_AWARE_TOPOLOGY_BINDING_FOUNDATION_V1
 *
 * Realm binding foundation for operational support topology.
 *
 * R2A contract:
 *
 * - Existing topology is bound to Project.default_realm_id.
 * - Same-Realm topology relationships are enforced by composite FKs.
 * - realm_id remains nullable temporarily for deployment compatibility.
 * - Application topology writers are upgraded in the same release.
 * - Ticket/Assignment Realm state belongs to R2B.
 * - Project-wide unique codes/ranks are re-scoped in R2B.
 */
final class CreateTicketingRealmAwareTopologyBindingFoundation
    extends Migration
{
    public function up(): void
    {
        $this->extendTopologyTables();

        $this->backfillDirectProjectTopology();

        $this->backfillBindingTopology();

        $this->ensureRealmIdentityIndexes();

        $this->ensureRealmLookupIndexes();

        $this->ensureRealmConstraints();
    }


    public function down(): void
    {
        /*
         * Non-destructive by design.
         */
    }


    private function extendTopologyTables(): void
    {
        $definitions = [
            'ticketing_support_layers' =>
                'project_id',

            'ticketing_support_nodes' =>
                'project_id',

            'ticketing_support_node_relations' =>
                'project_id',

            'ticketing_support_teams' =>
                'project_id',

            'ticketing_support_team_nodes' =>
                'id',

            'ticketing_support_queues' =>
                'project_id',

            'ticketing_support_team_queues' =>
                'id',

            'ticketing_support_team_members' =>
                'id',
        ];


        foreach (
            $definitions
            as $table => $afterColumn
        ) {
            if (
                $this->columnExists(
                    $table,
                    'realm_id'
                )
            ) {
                continue;
            }

            $this->db->exec("
                ALTER TABLE
                    {$table}

                ADD COLUMN
                    realm_id
                    BIGINT UNSIGNED
                    NULL

                AFTER
                    {$afterColumn}
            ");
        }
    }


    private function backfillDirectProjectTopology(): void
    {
        foreach ([
            'ticketing_support_layers',
            'ticketing_support_nodes',
            'ticketing_support_node_relations',
            'ticketing_support_teams',
            'ticketing_support_queues',
        ] as $table) {

            $this->db->exec("
                UPDATE
                    {$table} x

                INNER JOIN
                    ticketing_support_projects p
                    ON p.id = x.project_id

                SET
                    x.realm_id =
                        p.default_realm_id,

                    x.updated_at =
                        x.updated_at

                WHERE
                    x.realm_id IS NULL
            ");
        }
    }


    private function backfillBindingTopology(): void
    {
        /*
         * Team ↔ Node binding.
         */
        $this->db->exec("
            UPDATE
                ticketing_support_team_nodes x

            INNER JOIN
                ticketing_support_teams t
                ON t.id = x.team_id

            INNER JOIN
                ticketing_support_nodes n
                ON n.id = x.node_id

            SET
                x.realm_id =
                    t.realm_id,

                x.updated_at =
                    x.updated_at

            WHERE
                x.realm_id IS NULL

              AND t.realm_id IS NOT NULL

              AND n.realm_id =
                    t.realm_id
        ");


        /*
         * Team ↔ Queue binding.
         */
        $this->db->exec("
            UPDATE
                ticketing_support_team_queues x

            INNER JOIN
                ticketing_support_teams t
                ON t.id = x.team_id

            INNER JOIN
                ticketing_support_queues q
                ON q.id = x.queue_id

            SET
                x.realm_id =
                    t.realm_id,

                x.updated_at =
                    x.updated_at

            WHERE
                x.realm_id IS NULL

              AND t.realm_id IS NOT NULL

              AND q.realm_id =
                    t.realm_id
        ");


        /*
         * Staff may participate in several Realms through different
         * Team memberships. The Team determines this row's Realm.
         */
        $this->db->exec("
            UPDATE
                ticketing_support_team_members x

            INNER JOIN
                ticketing_support_teams t
                ON t.id = x.team_id

            SET
                x.realm_id =
                    t.realm_id,

                x.updated_at =
                    x.updated_at

            WHERE
                x.realm_id IS NULL

              AND t.realm_id IS NOT NULL
        ");
    }


    private function ensureRealmIdentityIndexes(): void
    {
        $indexes = [
            [
                'ticketing_support_layers',
                'ticketing_layers_realm_identity_unique',
                'UNIQUE',
                'realm_id, id',
            ],

            [
                'ticketing_support_nodes',
                'ticketing_nodes_realm_identity_unique',
                'UNIQUE',
                'realm_id, id',
            ],

            [
                'ticketing_support_teams',
                'ticketing_teams_realm_identity_unique',
                'UNIQUE',
                'realm_id, id',
            ],

            [
                'ticketing_support_queues',
                'ticketing_queues_realm_identity_unique',
                'UNIQUE',
                'realm_id, id',
            ],
        ];


        foreach (
            $indexes
            as [$table, $name, $type, $columns]
        ) {
            if (
                $this->indexExists(
                    $table,
                    $name
                )
            ) {
                continue;
            }

            $this->db->exec("
                ALTER TABLE
                    {$table}

                ADD {$type} INDEX
                    {$name}
                    ({$columns})
            ");
        }
    }


    private function ensureRealmLookupIndexes(): void
    {
        $indexes = [
            [
                'ticketing_support_layers',
                'ticketing_layers_realm_status_index',
                'realm_id, status, rank_order',
            ],

            [
                'ticketing_support_nodes',
                'ticketing_nodes_realm_layer_index',
                'realm_id, layer_id, status, sort_order',
            ],

            [
                'ticketing_support_node_relations',
                'ticketing_node_rel_realm_parent_index',
                'realm_id, parent_node_id, status',
            ],

            [
                'ticketing_support_node_relations',
                'ticketing_node_rel_realm_child_index',
                'realm_id, child_node_id, status',
            ],

            [
                'ticketing_support_teams',
                'ticketing_teams_realm_status_index',
                'realm_id, status, sort_order',
            ],

            [
                'ticketing_support_team_nodes',
                'ticketing_team_nodes_realm_team_index',
                'realm_id, team_id, status',
            ],

            [
                'ticketing_support_team_nodes',
                'ticketing_team_nodes_realm_node_index',
                'realm_id, node_id, status',
            ],

            [
                'ticketing_support_queues',
                'ticketing_queues_realm_node_index',
                'realm_id, node_id, status, sort_order',
            ],

            [
                'ticketing_support_team_queues',
                'ticketing_team_queues_realm_team_index',
                'realm_id, team_id, status',
            ],

            [
                'ticketing_support_team_queues',
                'ticketing_team_queues_realm_queue_index',
                'realm_id, queue_id, status',
            ],

            [
                'ticketing_support_team_members',
                'ticketing_team_members_realm_team_index',
                'realm_id, team_id, status, left_at',
            ],
        ];


        foreach (
            $indexes
            as [$table, $name, $columns]
        ) {
            if (
                $this->indexExists(
                    $table,
                    $name
                )
            ) {
                continue;
            }

            $this->db->exec("
                ALTER TABLE
                    {$table}

                ADD INDEX
                    {$name}
                    ({$columns})
            ");
        }
    }


    private function ensureRealmConstraints(): void
    {
        /*
         * Direct Project-owned topology.
         */
        $this->ensureConstraint(
            'ticketing_support_layers',
            'ticketing_layers_project_realm_fk',
            "
                FOREIGN KEY
                    (
                        project_id,
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
            'ticketing_support_nodes',
            'ticketing_nodes_project_realm_fk',
            "
                FOREIGN KEY
                    (
                        project_id,
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
            'ticketing_support_nodes',
            'ticketing_nodes_realm_layer_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        layer_id
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
            'ticketing_support_node_relations',
            'ticketing_node_rel_project_realm_fk',
            "
                FOREIGN KEY
                    (
                        project_id,
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
            'ticketing_support_node_relations',
            'ticketing_node_rel_parent_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        parent_node_id
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
            'ticketing_support_node_relations',
            'ticketing_node_rel_child_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        child_node_id
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
            'ticketing_support_teams',
            'ticketing_teams_project_realm_fk',
            "
                FOREIGN KEY
                    (
                        project_id,
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
            'ticketing_support_team_nodes',
            'ticketing_team_nodes_team_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        team_id
                    )

                REFERENCES
                    ticketing_support_teams
                    (
                        realm_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_support_team_nodes',
            'ticketing_team_nodes_node_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        node_id
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
            'ticketing_support_queues',
            'ticketing_queues_project_realm_fk',
            "
                FOREIGN KEY
                    (
                        project_id,
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
            'ticketing_support_queues',
            'ticketing_queues_node_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        node_id
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
            'ticketing_support_team_queues',
            'ticketing_team_queues_team_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        team_id
                    )

                REFERENCES
                    ticketing_support_teams
                    (
                        realm_id,
                        id
                    )
            "
        );


        $this->ensureConstraint(
            'ticketing_support_team_queues',
            'ticketing_team_queues_queue_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        queue_id
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
            'ticketing_support_team_members',
            'ticketing_team_members_team_realm_fk',
            "
                FOREIGN KEY
                    (
                        realm_id,
                        team_id
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
