<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/app/Services/ModuleSsoService.php';

if (!is_file($path)) {
    throw new RuntimeException(
        'module_sso_source_missing'
    );
}

$source =
    file_get_contents(
        $path
    );

if (!is_string($source)) {
    throw new RuntimeException(
        'module_sso_source_read_failed'
    );
}

$start =
    strpos(
        $source,
        'private function isRequesterTicketingReturnPath('
    );

$end =
    strpos(
        $source,
        'public function resumeFor(',
        $start === false
            ? 0
            : $start
    );

if (
    $start === false
    || $end === false
    || $end <= $start
) {
    throw new RuntimeException(
        'requester_method_boundary_missing'
    );
}

$method =
    substr(
        $source,
        $start,
        $end - $start
    );

$exactPaths = [
    "'/admin/ticketing',",
    "'/admin/ticketing/tickets',",
    "'/admin/ticketing/tickets/create',",
];

foreach ($exactPaths as $needle) {
    if (
        substr_count(
            $method,
            $needle
        ) !== 1
    ) {
        throw new RuntimeException(
            'requester_exact_path_contract_invalid:'
            . $needle
        );
    }
}

if (
    !str_contains(
        $method,
        '#^/admin/ticketing/tickets/[A-Za-z0-9_-]+$#'
    )
) {
    throw new RuntimeException(
        'requester_ticket_detail_contract_missing'
    );
}

/*
 * Guard against turning the requester exception
 * into broad /admin/ticketing/* authorization.
 */
$forbiddenPatterns = [
    "str_starts_with(\$path, '/admin/ticketing')",
    "str_starts_with(\n                \$path,\n                '/admin/ticketing'",
    "'/admin/ticketing/'",
];

foreach ($forbiddenPatterns as $pattern) {
    if (
        str_contains(
            $method,
            $pattern
        )
    ) {
        throw new RuntimeException(
            'broad_requester_ticketing_access_detected'
        );
    }
}

/*
 * ticketing.ticket.view must remain outside this
 * requester path classifier.
 */
if (
    str_contains(
        $method,
        'ticketing.ticket.view'
    )
) {
    throw new RuntimeException(
        'requester_classifier_permission_leak'
    );
}

echo "REQUESTER_ROOT_LANDING_PATH=PASS\n";
echo "REQUESTER_TICKETS_PATH=PASS\n";
echo "REQUESTER_CREATE_PATH=PASS\n";
echo "REQUESTER_DETAIL_REGEX=PASS\n";
echo "BROAD_TICKETING_ACCESS=NO\n";
echo "MODULE_SSO_REQUESTER_LANDING_PATH_CONTRACT=PASS\n";
