<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/app/Services/AdminNavigationRbacService.php';

if (!is_file($path)) {
    throw new RuntimeException(
        'admin_navigation_rbac_source_missing'
    );
}

$source =
    file_get_contents(
        $path
    );

if (!is_string($source)) {
    throw new RuntimeException(
        'admin_navigation_rbac_source_read_failed'
    );
}

$start =
    strpos(
        $source,
        'private function isRequesterTicketingPath('
    );

$end =
    strpos(
        $source,
        'public function can(',
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


foreach (
    [
        "'/admin/ticketing',",
        "'/admin/ticketing/tickets',",
        "'/admin/ticketing/tickets/create',",
    ]
    as $required
) {
    if (
        substr_count(
            $method,
            $required
        ) !== 1
    ) {
        throw new RuntimeException(
            'requester_exact_path_invalid:'
            . $required
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
        'requester_detail_regex_missing'
    );
}


foreach (
    [
        "str_starts_with(\$path, '/admin/ticketing')",
        "'/admin/ticketing/staff'",
        "'/admin/ticketing/projects'",
    ]
    as $forbidden
) {
    if (
        str_contains(
            $method,
            $forbidden
        )
    ) {
        throw new RuntimeException(
            'requester_rbac_too_broad:'
            . $forbidden
        );
    }
}


echo "REQUESTER_ROOT_LOCAL_RBAC=PASS\n";
echo "REQUESTER_TICKETS_LOCAL_RBAC=PASS\n";
echo "REQUESTER_CREATE_LOCAL_RBAC=PASS\n";
echo "REQUESTER_DETAIL_LOCAL_RBAC=PASS\n";
echo "STAFF_REQUESTER_WHITELIST=NO\n";
echo "PROJECT_ADMIN_REQUESTER_WHITELIST=NO\n";
echo "BROAD_REQUESTER_PREFIX=NO\n";
echo "ADMIN_NAVIGATION_REQUESTER_LANDING_PATH_CONTRACT=PASS\n";
