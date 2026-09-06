<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$file =
    $root
    . '/public_html/app/Repositories/'
    . 'TicketStaffOperationsRepository.php';

$text =
    file_get_contents(
        $file
    );

if (!is_string($text)) {
    throw new RuntimeException(
        'repository_unreadable'
    );
}


foreach ([
    'TICKETING_HIERARCHICAL_VISIBILITY_V1',
    'pm.role_code AS project_role_code',
    'l.can_observe_descendants',
    'private function visibleNodesByProject(',
    'private function directChildNodes(',
    'private function visibleNodeClause(',
    "'all' => true",
    "'nodes' => []",
    "=== 'manager'",
    'parent_node_id = ?',
    "relation_type_code =",
    "'hierarchy'",
    'is_primary_path = 1',
] as $marker) {

    if (
        strpos(
            $text,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'visibility_marker_missing:'
            . $marker
        );
    }
}


/*
 * Recursive descendant visibility must not return.
 */
foreach ([
    'private function descendantNodes(',
    '$this->descendantNodes(',
] as $forbidden) {

    if (
        strpos(
            $text,
            $forbidden
        ) !== false
    ) {
        throw new RuntimeException(
            'recursive_visibility_present:'
            . $forbidden
        );
    }
}


/*
 * Project role, not team role, is the full-project bypass.
 */
$visibilityStart =
    strpos(
        $text,
        'private function visibleNodesByProject('
    );

$visibilityEnd =
    strpos(
        $text,
        'private function directChildNodes(',
        $visibilityStart
    );

if (
    $visibilityStart === false
    ||
    $visibilityEnd === false
    ||
    $visibilityStart >= $visibilityEnd
) {
    throw new RuntimeException(
        'visibility_scope_invalid'
    );
}

$visibilityBlock =
    substr(
        $text,
        $visibilityStart,
        $visibilityEnd - $visibilityStart
    );


foreach ([
    "'project_role_code'",
    "'can_observe_descendants'",
    'directChildNodes(',
    "'all' => true",
] as $marker) {

    if (
        strpos(
            $visibilityBlock,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'visibility_block_marker_missing:'
            . $marker
        );
    }
}


if (
    strpos(
        $visibilityBlock,
        "'staff_role_code'"
    ) !== false
) {
    throw new RuntimeException(
        'team_role_used_as_global_manager_bypass'
    );
}


echo
    "TICKETING_HIERARCHICAL_VISIBILITY_POLICY_PASS"
    . PHP_EOL;
