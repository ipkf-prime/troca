<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$read =
    static function (
        string $relative
    ) use ($root): string {

        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'file_unreadable:'
                . $relative
            );
        }

        return $content;
    };


$expect =
    static function (
        bool $condition,
        string $message
    ): void {

        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


$method =
    static function (
        string $source,
        string $visibility,
        string $name
    ): string {

        $pattern =
            '/\n    '
            . preg_quote(
                $visibility,
                '/'
            )
            . ' function '
            . preg_quote(
                $name,
                '/'
            )
            . '\s*\(/';

        if (
            preg_match(
                $pattern,
                $source,
                $match,
                PREG_OFFSET_CAPTURE
            ) !== 1
        ) {
            throw new RuntimeException(
                'method_missing:'
                . $name
            );
        }

        $start =
            (int) $match[0][1];

        $afterStart =
            $start
            + strlen(
                (string) $match[0][0]
            );

        $remaining =
            substr(
                $source,
                $afterStart
            );

        if (!is_string($remaining)) {
            throw new RuntimeException(
                'method_slice_failed:'
                . $name
            );
        }

        $end =
            strlen($source);

        if (
            preg_match(
                '/\n    (?:public|private|protected) function \w+\s*\(/',
                $remaining,
                $next,
                PREG_OFFSET_CAPTURE
            ) === 1
        ) {
            $end =
                $afterStart
                + (int) $next[0][1];
        }

        return
            substr(
                $source,
                $start,
                $end - $start
            );
    };


$create =
    $read(
        'public_html/app/Repositories/'
        . 'TicketCreateRoutingRepository.php'
    );

$legacy =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$staff =
    $read(
        'public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );

$lifecycle =
    $read(
        'public_html/app/Repositories/'
        . 'TicketLifecycleRepository.php'
    );

$transition =
    $read(
        'public_html/app/Repositories/'
        . 'TicketLifecycleTransitionRepository.php'
    );

$priority =
    $read(
        'public_html/app/Repositories/'
        . 'TicketPriorityManagementRepository.php'
    );

$onboarding =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketRequesterOnboardingService.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$m1 =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingOperationalRealmBindingFoundation.php'
    );

$m2 =
    $read(
        'public_html/system/Database/Migrations/'
        . 'FinalizeTicketingStrictOperationalRealmEnforcement.php'
    );


/*
 * --------------------------------------------------------------------------
 * Registry order
 * --------------------------------------------------------------------------
 */

$r2a =
    strpos(
        $registry,
        'CreateTicketingRealmAwareTopologyBindingFoundation::class'
    );

$r2b1 =
    strpos(
        $registry,
        'CreateTicketingOperationalRealmBindingFoundation::class'
    );

$r2b2 =
    strpos(
        $registry,
        'FinalizeTicketingStrictOperationalRealmEnforcement::class'
    );

$expect(
    is_int($r2a)
    && is_int($r2b1)
    && is_int($r2b2)
    && $r2a < $r2b1
    && $r2b1 < $r2b2,
    'r2b_migration_registry_order_invalid'
);

$expect(
    substr_count(
        $registry,
        'CreateTicketingOperationalRealmBindingFoundation::class'
    ) === 1,
    'm1_registry_count_invalid'
);

$expect(
    substr_count(
        $registry,
        'FinalizeTicketingStrictOperationalRealmEnforcement::class'
    ) === 1,
    'm2_registry_count_invalid'
);


/*
 * --------------------------------------------------------------------------
 * Creation + recovery Realm contract
 * --------------------------------------------------------------------------
 */

foreach ([
    'TICKETING_STRICT_OPERATIONAL_REALM_CREATION_V1',
    'p.default_realm_id AS realm_id',
    'Resolved support route belongs to another Realm.',
    'routing_recovery_cross_realm_route',
] as $marker) {

    $expect(
        str_contains(
            $create,
            $marker
        ),
        'creation_marker_missing:'
        . $marker
    );
}

$expect(
    substr_count(
        $create,
        '$this->resolveRoute('
    ) === 2,
    'resolve_route_call_count_invalid'
);

$expect(
    substr_count(
        $create,
        '$this->intakeRoute('
    ) === 1,
    'intake_route_call_count_invalid'
);


/*
 * --------------------------------------------------------------------------
 * Legacy unscoped create is permanently fail-closed
 * --------------------------------------------------------------------------
 */

$legacyCreate =
    $method(
        $legacy,
        'public',
        'create'
    );

$legacyThrow =
    strpos(
        $legacyCreate,
        'legacy_unscoped_ticket_create_disabled'
    );

$legacyTransaction =
    strpos(
        $legacyCreate,
        '$this->db->beginTransaction();'
    );

$expect(
    str_contains(
        $legacyCreate,
        'TICKETING_LEGACY_UNSCOPED_CREATE_DISABLED_V1'
    ),
    'legacy_create_marker_missing'
);

$expect(
    is_int($legacyThrow)
    && is_int($legacyTransaction)
    && $legacyThrow < $legacyTransaction,
    'legacy_create_not_fail_closed'
);


/*
 * --------------------------------------------------------------------------
 * Operational staff loaders carry realm_id after M1.
 *
 * SELECT * is intentionally valid because PDO will return the new column.
 * --------------------------------------------------------------------------
 */

foreach ([
    'ticketById',
    'lockTicket',
] as $loader) {

    $body =
        $method(
            $staff,
            'private',
            $loader
        );

    $queriesTicket =
        preg_match(
            '/\bFROM\s+ticketing_tickets\b/i',
            $body
        ) === 1;

    $selectStar =
        preg_match(
            '/\bSELECT\s+\*\s+FROM\s+ticketing_tickets\b/is',
            $body
        ) === 1;

    $selectAliasStar =
        preg_match(
            '/\bSELECT\s+t\.\*\s+FROM\s+ticketing_tickets\s+t\b/is',
            $body
        ) === 1;

    $explicitRealm =
        str_contains(
            $body,
            'realm_id'
        );

    $expect(
        $queriesTicket
        &&
        (
            $selectStar
            || $selectAliasStar
            || $explicitRealm
        ),
        'staff_loader_realm_not_available:'
        . $loader
    );
}


/*
 * --------------------------------------------------------------------------
 * Staff operational mutations are same-Realm only
 * --------------------------------------------------------------------------
 */

foreach ([
    'TICKETING_STRICT_OPERATIONAL_REALM_STAFF_V1',
    'routeRealmForAssignmentTarget',
    'cross_realm_handoff_required',
    'ticket_realm_missing',
] as $marker) {

    $expect(
        str_contains(
            $staff,
            $marker
        ),
        'staff_marker_missing:'
        . $marker
    );
}


$nextRelation =
    $method(
        $staff,
        'private',
        'nextEscalationRelation'
    );

foreach ([
    'int $realmId',
    'r.realm_id = ?',
    'parent.realm_id',
] as $marker) {

    $expect(
        str_contains(
            $nextRelation,
            $marker
        ),
        'next_relation_realm_marker_missing:'
        . $marker
    );
}


$route =
    $method(
        $staff,
        'private',
        'routeForNode'
    );

foreach ([
    'int $realmId',
    'n.realm_id = ?',
    'q.realm_id',
    't.realm_id',
] as $marker) {

    $expect(
        str_contains(
            $route,
            $marker
        ),
        'route_realm_marker_missing:'
        . $marker
    );
}


$replace =
    $method(
        $staff,
        'private',
        'replaceAssignment'
    );

foreach ([
    'cross_realm_handoff_required',
    '$ticketRealmId',
    'realm_id = ?',
] as $marker) {

    $expect(
        str_contains(
            $replace,
            $marker
        ),
        'replace_assignment_realm_marker_missing:'
        . $marker
    );
}


/*
 * --------------------------------------------------------------------------
 * Canonical Data Scope includes Realm
 * --------------------------------------------------------------------------
 */

$dataScope =
    $method(
        $staff,
        'private',
        'dataScopeClause'
    );

$expect(
    str_contains(
        $dataScope,
        "'realm'"
    ),
    'realm_resource_type_missing'
);

$expect(
    str_contains(
        $dataScope,
        't.realm_id'
    ),
    'realm_resource_value_missing'
);


/*
 * --------------------------------------------------------------------------
 * Runtime ticket/assignment SQL writer set
 * --------------------------------------------------------------------------
 */

$appRoot =
    $root
    . '/public_html/app';

$pattern =
    '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)'
    . '\s+`?(?:ticketing_tickets|ticketing_assignments)`?\b/is';

$writerFiles = [];

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $appRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );

foreach ($iterator as $file) {

    if (
        !$file->isFile()
        || strtolower(
            $file->getExtension()
        ) !== 'php'
    ) {
        continue;
    }

    $content =
        file_get_contents(
            $file->getPathname()
        );

    if (
        !is_string($content)
        || preg_match(
            $pattern,
            $content
        ) !== 1
    ) {
        continue;
    }

    $relative =
        str_replace(
            '\\',
            '/',
            $file->getPathname()
        );

    $rootNormalized =
        rtrim(
            str_replace(
                '\\',
                '/',
                $root
            ),
            '/'
        )
        . '/';

    if (
        str_starts_with(
            $relative,
            $rootNormalized
        )
    ) {
        $relative =
            substr(
                $relative,
                strlen(
                    $rootNormalized
                )
            );
    }

    $writerFiles[] =
        $relative;
}

$writerFiles =
    array_values(
        array_unique(
            $writerFiles
        )
    );

sort($writerFiles);


$expectedWriterFiles = [
    'public_html/app/Repositories/TicketCreateRoutingRepository.php',
    'public_html/app/Repositories/TicketLifecycleRepository.php',
    'public_html/app/Repositories/TicketLifecycleTransitionRepository.php',
    'public_html/app/Repositories/TicketPriorityManagementRepository.php',
    'public_html/app/Repositories/TicketRepository.php',
    'public_html/app/Repositories/TicketStaffOperationsRepository.php',
    'public_html/app/Services/Ticketing/TicketRequesterOnboardingService.php',
];

sort($expectedWriterFiles);

$expect(
    $writerFiles ===
        $expectedWriterFiles,
    'runtime_writer_file_set_changed:'
    . json_encode(
        $writerFiles,
        JSON_UNESCAPED_SLASHES
    )
);


/*
 * --------------------------------------------------------------------------
 * Four secondary writers preserve Realm / Route / Assignment
 * --------------------------------------------------------------------------
 */

$preserving = [
    'TicketLifecycleRepository' =>
        [
            $lifecycle,
            3,
        ],

    'TicketLifecycleTransitionRepository' =>
        [
            $transition,
            3,
        ],

    'TicketPriorityManagementRepository' =>
        [
            $priority,
            1,
        ],

    'TicketRequesterOnboardingService' =>
        [
            $onboarding,
            1,
        ],
];


$forbiddenColumns = [
    'realm_id',
    'support_project_id',
    'current_support_layer_id',
    'current_support_node_id',
    'current_support_queue_id',
    'current_support_team_id',
    'current_assignee_project_member_id',
];


foreach (
    $preserving
    as $name => [$source, $expectedUpdates]
) {

    $expect(
        preg_match(
            '/\bINSERT\s+INTO\s+`?ticketing_tickets`?\b/i',
            $source
        ) !== 1,
        'preserving_writer_inserts_ticket:'
        . $name
    );

    $expect(
        preg_match(
            '/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)'
            . '\s+`?ticketing_assignments`?\b/i',
            $source
        ) !== 1,
        'preserving_writer_mutates_assignment:'
        . $name
    );


    preg_match_all(
        '/UPDATE\s+`?ticketing_tickets`?\s+SET(?P<set>.*?)\bWHERE\b/is',
        $source,
        $matches
    );

    $setClauses =
        $matches['set']
        ?? [];

    $expect(
        count($setClauses)
        === $expectedUpdates,
        'preserving_writer_update_count_changed:'
        . $name
    );


    foreach ($setClauses as $setClause) {

        foreach ($forbiddenColumns as $column) {

            $expect(
                preg_match(
                    '/\b'
                    . preg_quote(
                        $column,
                        '/'
                    )
                    . '\s*=/i',
                    $setClause
                ) !== 1,
                'preserving_writer_mutates_operational_column:'
                . $name
                . ':'
                . $column
            );
        }
    }
}


/*
 * --------------------------------------------------------------------------
 * M1 historical Realm bridge
 * --------------------------------------------------------------------------
 */

foreach ([
    'TICKETING_OPERATIONAL_REALM_BINDING_FOUNDATION_V1',
    'ticketing_tickets',
    'ticketing_assignments',
    'realm_id',
    'TICKETING_ASSIGNMENT_REALM_HISTORICAL_PROVENANCE_V1',
    'ticketing_assignments_realm_fk',
] as $marker) {

    $expect(
        str_contains(
            $m1,
            $marker
        ),
        'm1_marker_missing:'
        . $marker
    );
}

$expect(
    !str_contains(
        $m1,
        'ticketing_assignments_ticket_realm_fk'
    ),
    'assignment_history_coupled_to_current_ticket_realm'
);


/*
 * --------------------------------------------------------------------------
 * M2 strict finalizer
 * --------------------------------------------------------------------------
 */

foreach ([
    'TICKETING_STRICT_OPERATIONAL_REALM_ENFORCEMENT_V1',
    'TICKETING_ACTIVE_ASSIGNMENT_CURRENT_REALM_V1',
    'a.unassigned_at IS NULL',
    'ar.project_id',
    'NOT NULL',
] as $marker) {

    $expect(
        str_contains(
            $m2,
            $marker
        ),
        'm2_marker_missing:'
        . $marker
    );
}


/*
 * Cross-Realm Handoff remains explicitly deferred.
 */
foreach ([
    'ticketing_cross_realm_handoffs',
    'ticketing_realm_handoff_events',
] as $marker) {

    $expect(
        !str_contains(
            $m1 . $m2,
            $marker
        ),
        'cross_realm_handoff_implemented_prematurely:'
        . $marker
    );
}


echo
    "TICKETING_STRICT_OPERATIONAL_REALM_ENFORCEMENT_PASS"
    . PHP_EOL;
