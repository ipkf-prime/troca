<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $text =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($text)) {
            throw new RuntimeException(
                'Unreadable: '
                . $relative
            );
        }

        return $text;
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


$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketAutoClosePolicyRepository.php'
    );

$adminService =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketAutoClosePolicyAdminService.php'
    );

$projectService =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'SupportProjectAdminService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-auto-close-management.php'
    );

$ticketingRuntime =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-project-form.php'
    );


foreach ([
    'TICKETING_AUTO_CLOSE_POLICY_WRITE_V1',
    'public function savePolicy(',
    'FOR UPDATE',
    'ticketing_support_projects',
    'ticketing_auto_close_policies',
    'UTC_TIMESTAMP()',
    'auto_close_delay_required',
    'auto_close_delay_invalid',
    'auto_close_project_not_found',
    '$freshBoundary',
    'eligible_resolved_from =',
    'enabled_at =',
    'policyForProject(',
] as $marker) {
    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Policy writer marker missing: '
        . $marker
    );
}


foreach ([
    'TicketAutoClosePolicyRepository',
    'SupportProjectAdminRepository',
    'public function save(',
    'auto_close_delay_required',
    'auto_close_delay_invalid',
    'auto_close_enabled',
    'auto_close_disabled',
    "'user:' . \$userId",
] as $marker) {
    $expect(
        str_contains(
            $adminService,
            $marker
        ),
        'Policy admin service marker missing: '
        . $marker
    );
}


foreach ([
    'TicketAutoClosePolicyRepository',
    'TICKETING_PROJECT_AUTO_CLOSE_PROJECTION_V1',
    "'auto_close' =>",
    'policyForProject(',
] as $marker) {
    $expect(
        str_contains(
            $projectService,
            $marker
        ),
        'Project projection marker missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_AUTO_CLOSE_POLICY_ADMIN_ROUTE_V1',
    "/admin/ticketing/projects/{public_reference}/auto-close",
    "'/admin/ticketing/projects'",
    'new \\IPKF\\Security\\Csrf()',
    'TicketAutoClosePolicyAdminService',
    "'delay_hours'",
    "'is_enabled'",
    'auto_close_error',
] as $marker) {
    $expect(
        str_contains(
            $route,
            $marker
        ),
        'Policy route marker missing: '
        . $marker
    );
}


$expect(
    str_contains(
        $ticketingRuntime,
        'TICKETING_AUTO_CLOSE_POLICY_ROUTE_BOOTSTRAP_V1'
    )
    &&
    str_contains(
        $ticketingRuntime,
        "/routes/ticketing-auto-close-management.php"
    ),
    'Auto-close route file is not bootstrapped by Ticketing runtime.'
);


foreach ([
    "'auto-close'",
    'data-project-tab="auto-close"',
    'data-project-tab-panel="auto-close"',
    'data-ticketing-auto-close-policy-form',
    'data-ticketing-auto-close-enabled',
    'data-ticketing-auto-close-delay',
    'بستن خودکار',
    'مدت انتظار',
    'محافظت از تیکت‌های قدیمی',
    'مرز شروع',
] as $marker) {
    $expect(
        str_contains(
            $view,
            $marker
        ),
        'Auto-close UI marker missing: '
        . $marker
    );
}


/*
 * Critical governance contract:
 *
 * Browser operators may enable/disable and change
 * delay_hours, but may never backdate or manually
 * edit the safety eligibility boundary.
 */
$expect(
    !str_contains(
        $view,
        'name="eligible_resolved_from"'
    ),
    'Eligibility boundary must not be user-editable.'
);


$expect(
    str_contains(
        $repository,
        '$freshBoundary'
    )
    &&
    str_contains(
        $repository,
        'UTC_TIMESTAMP()'
    ),
    'Fresh enablement boundary contract is missing.'
);


echo
    "TICKETING_AUTO_CLOSE_POLICY_MANAGEMENT_PASS\n";
