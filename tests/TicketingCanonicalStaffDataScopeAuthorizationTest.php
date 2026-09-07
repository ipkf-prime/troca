<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$read =
    static function (
        string $relative
    ) use ($root): string {

        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'unreadable:'
                . $relative
            );
        }

        return $value;
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


$staffRepository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );

$ticketRepository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$staffService =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffOperationsService.php'
    );

$replyService =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffReplyAccessService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$topbar =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketTopbarTargetService.php'
    );

$evaluator =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketingAccessGrantEvaluator.php'
    );


/*
 * Canonical SQL scope.
 */
foreach ([
    'TICKETING_CANONICAL_STAFF_DATA_SCOPE_SQL_V1',
    'ticketing_access_grants',
    'ticketing_access_grant_dimension_rules',
    'ticketing_access_grant_dimension_values',
    'ticketing_access_grant_resource_rules',
    'ticketing_access_grant_resource_values',
    'ticketing_ticket_scope_states',
    'ticketing_ticket_scope_snapshot_values',
    'ticketing_scope_dimension_value_paths',
    'is_unrestricted',
    'include_descendants',
    "'any'",
    "'all'",
    'UTC_TIMESTAMP()',
] as $marker) {

    $expect(
        str_contains(
            $staffRepository,
            $marker
        ),
        'canonical_sql_marker_missing:'
        . $marker
    );
}


$expect(
    substr_count(
        $staffRepository,
        '$this->dataScopeClause('
    ) >= 2,
    'cartable_dashboard_scope_not_shared'
);


/*
 * Exact detail authorization must not depend on cartable LIMIT 200.
 */
foreach ([
    'public function canViewTicket(',
    '->cartable(',
    "'all'",
    '$reference',
] as $marker) {

    $expect(
        str_contains(
            $staffService,
            $marker
        ),
        'exact_detail_marker_missing:'
        . $marker
    );
}

$expect(
    str_contains(
        $staffRepository,
        '?string $publicReference = null'
    ),
    'exact_cartable_reference_missing'
);

$expect(
    str_contains(
        $staffRepository,
        'LIMIT {$limit}'
    ),
    'exact_cartable_limit_missing'
);


/*
 * Requester path must no longer contain an active-assignee bypass.
 */
$expect(
    str_contains(
        $ticketRepository,
        'TICKETING_REQUESTER_ONLY_VIEWER_BOUNDARY_V1'
    ),
    'requester_only_boundary_missing'
);


$methodSlice =
    static function (
        string $source,
        string $start,
        string $end
    ): string {

        $a = strpos(
            $source,
            $start
        );

        $b = strpos(
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
                'method_slice_failed:'
                . $start
            );
        }

        return substr(
            $source,
            $a,
            $b - $a
        );
    };


foreach ([
    [
        '    public function index(',
        '    public function viewerProjectTabs(',
    ],
    [
        '    public function viewerProjectTabs(',
        '    public function viewerLayers(',
    ],
    [
        '    public function viewerLayers(',
        '    public function viewerAssignees(',
    ],
    [
        '    public function viewerAssignees(',
        '    public function findByReference(',
    ],
    [
        '    public function findByReference(',
        '    public function messages(',
    ],
] as [$start, $end]) {

    $slice =
        $methodSlice(
            $ticketRepository,
            $start,
            $end
        );

    $expect(
        !str_contains(
            $slice,
            'ticketing_assignments'
        ),
        'active_assignee_bypass_remains:'
        . $start
    );
}


/*
 * Detail / attachment routes retain requester-first + canonical staff fallback.
 */
foreach ([
    'TICKETING_STAFF_DETAIL_CONTEXT_V1',
    'TICKETING_STAFF_ATTACHMENT_VISIBILITY_V1',
    ')->canViewTicket(',
] as $marker) {

    $expect(
        str_contains(
            $route,
            $marker
        ),
        'route_authorization_marker_missing:'
        . $marker
    );
}


/*
 * Reply ownership and Dynamic Data Scope must both hold.
 */
foreach ([
    'TICKETING_STAFF_REPLY_OWNERSHIP_GUARD',
    'TICKETING_STAFF_REPLY_DATA_SCOPE_GUARD_V1',
    'reply_scope_forbidden',
    ')->canViewTicket(',
] as $marker) {

    $expect(
        str_contains(
            $replyService,
            $marker
        ),
        'reply_scope_marker_missing:'
        . $marker
    );
}


/*
 * Mutation authorization is repository-side.
 */
foreach ([
    'TICKETING_STAFF_DATA_SCOPE_MUTATION_GUARD_V1',
    'assertStaffDataScopeVisible(',
    'TICKETING_STAFF_DATA_SCOPE_ACTION_CONTEXT_GUARD_V1',
] as $marker) {

    $expect(
        str_contains(
            $staffRepository,
            $marker
        ),
        'mutation_scope_marker_missing:'
        . $marker
    );
}


/*
 * Automatic SLA escalation remains a separate system contract.
 */
$systemEscalation =
    $methodSlice(
        $staffRepository,
        '    public function escalateSystem(',
        '    private function executeEscalation('
    );

$executeEscalation =
    $methodSlice(
        $staffRepository,
        '    private function executeEscalation(',
        '    public function displayNameForUserReference('
    );

$expect(
    str_contains(
        $systemEscalation,
        'false'
    ),
    'system_escalation_authorize_false_missing'
);

$expect(
    str_contains(
        $executeEscalation,
        'if ($authorizeActor)'
    )
    && str_contains(
        $executeEscalation,
        'assertStaffDataScopeVisible('
    ),
    'manual_escalation_scope_boundary_missing'
);


/*
 * Topbar already resolves staff target through canonical canViewTicket().
 */
foreach ([
    'TICKETING_CONTEXT_AWARE_TOPBAR_TARGET_V1',
    '->canViewTicket(',
] as $marker) {

    $expect(
        str_contains(
            $topbar,
            $marker
        ),
        'topbar_scope_marker_missing:'
        . $marker
    );
}


/*
 * Preserve the pure evaluator as the semantic reference implementation.
 */
foreach ([
    'TICKETING_ACCESS_GRANT_EVALUATOR_V1',
    'is_unrestricted',
    'include_descendants',
    "'any'",
    "'all'",
] as $marker) {

    $expect(
        str_contains(
            $evaluator,
            $marker
        ),
        'pure_evaluator_marker_missing:'
        . $marker
    );
}


echo
    "TICKETING_CANONICAL_STAFF_DATA_SCOPE_AUTHORIZATION_PASS"
    . PHP_EOL;
