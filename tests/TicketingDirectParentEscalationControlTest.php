<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/app/Repositories/'
    . 'TicketStaffOperationsRepository.php';

$text =
    file_get_contents(
        $path
    );

if (!is_string($text)) {
    throw new RuntimeException(
        'repository_unreadable'
    );
}


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


foreach ([
    'TICKETING_OPERATION_PERMISSION_SPLIT_V1',
    'TICKETING_SAME_NODE_TAKEOVER_V1',
    'TICKETING_DIRECT_PARENT_ESCALATION_V1',
    'private function takeoverMembership(',
    'private function nextEscalationRelation(',
    'r.child_node_id = ?',
    'r.allow_escalation = 1',
    'r.is_primary_path = 1',
] as $marker) {

    $expect(
        str_contains(
            $text,
            $marker
        ),
        'marker_missing:'
        . $marker
    );
}


/*
 * Ancestor-based Take Over is forbidden.
 */
foreach ([
    'private function ancestorDistances(',
    '$this->ancestorDistances(',
] as $marker) {

    $expect(
        !str_contains(
            $text,
            $marker
        ),
        'ancestor_takeover_leak:'
        . $marker
    );
}


/*
 * Inspect actionContext only.
 */
$actionStart =
    strpos(
        $text,
        'public function actionContext('
    );

$actionEnd =
    strpos(
        $text,
        'public function takeOver(',
        $actionStart
    );

$expect(
    is_int($actionStart)
    && is_int($actionEnd)
    && $actionStart < $actionEnd,
    'action_context_scope_invalid'
);

$action =
    substr(
        $text,
        $actionStart,
        $actionEnd - $actionStart
    );

$expect(
    str_contains(
        $action,
        "'can_assign'"
    ),
    'same_team_reassign_not_using_can_assign'
);

$expect(
    str_contains(
        $action,
        "'can_transfer'"
    ),
    'escalation_not_using_can_transfer'
);


/*
 * Inspect transfer() only.
 */
$transferStart =
    strpos(
        $text,
        'public function transfer('
    );

$transferEnd =
    strpos(
        $text,
        'public function escalate(',
        $transferStart
    );

$expect(
    is_int($transferStart)
    && is_int($transferEnd)
    && $transferStart < $transferEnd,
    'transfer_scope_invalid'
);

$transfer =
    substr(
        $text,
        $transferStart,
        $transferEnd - $transferStart
    );

$expect(
    str_contains(
        $transfer,
        "'can_assign'"
    ),
    'same_team_transfer_not_guarded_by_can_assign'
);

$expect(
    !str_contains(
        $transfer,
        "'can_transfer'"
    ),
    'same_team_transfer_still_uses_cross_layer_permission'
);


/*
 * Inspect executeEscalation only.
 */
$escalateStart =
    strpos(
        $text,
        'private function executeEscalation('
    );

$escalateEnd =
    strpos(
        $text,
        'public function displayNameForUserReference(',
        $escalateStart
    );

$expect(
    is_int($escalateStart)
    && is_int($escalateEnd)
    && $escalateStart < $escalateEnd,
    'execute_escalation_scope_invalid'
);

$escalation =
    substr(
        $text,
        $escalateStart,
        $escalateEnd - $escalateStart
    );

$expect(
    str_contains(
        $escalation,
        "'can_transfer'"
    ),
    'manual_escalation_missing_transfer_permission'
);

$expect(
    !str_contains(
        $escalation,
        "'can_takeover'"
    ),
    'takeover_permission_can_still_escalate'
);


/*
 * Take Over must be strictly same Team + same Node.
 */
$takeoverStart =
    strpos(
        $text,
        'private function takeoverMembership('
    );

$takeoverEnd =
    strpos(
        $text,
        'private function transferTargets(',
        $takeoverStart
    );

$expect(
    is_int($takeoverStart)
    && is_int($takeoverEnd)
    && $takeoverStart < $takeoverEnd,
    'takeover_scope_invalid'
);

$takeover =
    substr(
        $text,
        $takeoverStart,
        $takeoverEnd - $takeoverStart
    );

foreach ([
    "'current_support_node_id'",
    "'current_support_team_id'",
    "'node_id'",
    "'team_id'",
    "'can_takeover'",
] as $marker) {

    $expect(
        str_contains(
            $takeover,
            $marker
        ),
        'takeover_marker_missing:'
        . $marker
    );
}


echo
    "TICKETING_DIRECT_PARENT_ESCALATION_CONTROL_PASS"
    . PHP_EOL;
