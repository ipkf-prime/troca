<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateTicketingRealmAwareTopologyBindingFoundation.php'
    );

$repository =
    file_get_contents(
        $root
        . '/public_html/app/Repositories/'
        . 'SupportTopologyAdminRepository.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

if (
    !is_string($migration)
    || !is_string($repository)
    || !is_string($registry)
) {
    throw new RuntimeException(
        'R2A source read failed.'
    );
}


$requiredMigrationMarkers = [
    'TICKETING_REALM_AWARE_TOPOLOGY_BINDING_FOUNDATION_V1',

    'ticketing_support_layers',
    'ticketing_support_nodes',
    'ticketing_support_node_relations',
    'ticketing_support_teams',
    'ticketing_support_team_nodes',
    'ticketing_support_queues',
    'ticketing_support_team_queues',
    'ticketing_support_team_members',

    'realm_id',

    'ticketing_layers_project_realm_fk',
    'ticketing_nodes_project_realm_fk',
    'ticketing_nodes_realm_layer_fk',

    'ticketing_node_rel_project_realm_fk',
    'ticketing_node_rel_parent_realm_fk',
    'ticketing_node_rel_child_realm_fk',

    'ticketing_teams_project_realm_fk',

    'ticketing_team_nodes_team_realm_fk',
    'ticketing_team_nodes_node_realm_fk',

    'ticketing_queues_project_realm_fk',
    'ticketing_queues_node_realm_fk',

    'ticketing_team_queues_team_realm_fk',
    'ticketing_team_queues_queue_realm_fk',

    'ticketing_team_members_team_realm_fk',
];


foreach (
    $requiredMigrationMarkers
    as $marker
) {
    if (
        !str_contains(
            $migration,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing R2A migration marker: '
            . $marker
        );
    }
}


/*
 * R2A deliberately keeps realm_id nullable.
 */
if (
    !str_contains(
        $migration,
        "BIGINT UNSIGNED\n                    NULL"
    )
) {
    throw new RuntimeException(
        'R2A nullable compatibility contract missing.'
    );
}


/*
 * Ticket + Assignment become Realm-aware in R2B, not R2A.
 */
foreach ([
    'ticketing_tickets',
    'ticketing_assignments',
] as $table) {

    if (
        preg_match(
            '/ALTER\s+TABLE\s+'
            . preg_quote(
                $table,
                '/'
            )
            . '\b/i',
            $migration
        ) === 1
    ) {
        throw new RuntimeException(
            'Premature R2B table mutation: '
            . $table
        );
    }
}


/*
 * Old Project-wide uniqueness remains until Realm-aware writer/context
 * is fully activated in R2B.
 */
foreach ([
    'ticketing_layers_project_code_unique',
    'ticketing_layers_project_rank_unique',
    'ticketing_nodes_project_code_unique',
    'ticketing_node_rel_unique',
    'ticketing_teams_project_code_unique',
    'ticketing_queues_project_code_unique',
] as $index) {

    if (
        preg_match(
            '/DROP\s+(?:INDEX|KEY).*'
            . preg_quote(
                $index,
                '/'
            )
            . '/is',
            $migration
        ) === 1
    ) {
        throw new RuntimeException(
            'Premature Realm uniqueness re-scope: '
            . $index
        );
    }
}


foreach ([
    'TICKETING_REALM_AWARE_TOPOLOGY_ADMIN_V1',
    'defaultRealmIdForProject',
    'topologyIdentity',
    'topologyRealmForProject',
    'assertSameTopologyRealm',
    'cross_realm_topology_binding_not_allowed',
] as $marker) {

    if (
        !str_contains(
            $repository,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Missing Realm-aware writer marker: '
            . $marker
        );
    }
}


$methodSlice =
    static function (
        string $source,
        string $start,
        string $end
    ): string {

        $a =
            strpos(
                $source,
                $start
            );

        $b =
            strpos(
                $source,
                $end,
                is_int($a)
                    ? $a
                    : 0
            );

        if (
            !is_int($a)
            || !is_int($b)
            || $a >= $b
        ) {
            throw new RuntimeException(
                'Method slice failed: '
                . $start
            );
        }

        return
            substr(
                $source,
                $a,
                $b - $a
            );
    };


foreach ([
    [
        '    public function createLayer(',
        '    public function createNode(',
    ],
    [
        '    public function createNode(',
        '    public function createRelation(',
    ],
    [
        '    public function createRelation(',
        '    public function createTeam(',
    ],
    [
        '    public function createTeam(',
        '    public function createQueue(',
    ],
    [
        '    public function createQueue(',
        '    public function bindTeamNode(',
    ],
    [
        '    public function bindTeamNode(',
        '    public function bindTeamQueue(',
    ],
    [
        '    public function bindTeamQueue(',
        '    public function addTeamMember(',
    ],
    [
        '    public function addTeamMember(',
        '    private function defaultRealmIdForProject(',
    ],
] as [$start, $end]) {

    $section =
        $methodSlice(
            $repository,
            $start,
            $end
        );

    if (
        !str_contains(
            $section,
            'realm_id'
        )
    ) {
        throw new RuntimeException(
            'Writer does not persist Realm: '
            . $start
        );
    }
}


if (
    substr_count(
        $registry,
        '\\IPKF\\Database\\Migrations\\'
        . 'CreateTicketingRealmAwareTopologyBindingFoundation::class,'
    ) !== 1
) {
    throw new RuntimeException(
        'R2A registry contract invalid.'
    );
}


foreach ([
    'ticketing_support_portals',
    'ticketing_storage_bindings',
] as $premature) {

    if (
        str_contains(
            $migration,
            $premature
        )
    ) {
        throw new RuntimeException(
            'Premature Realm concern: '
            . $premature
        );
    }
}


echo
    "TICKETING_REALM_AWARE_TOPOLOGY_BINDING_FOUNDATION_PASS"
    . PHP_EOL;
