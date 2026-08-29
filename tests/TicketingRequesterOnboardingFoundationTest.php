<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$files = [
    'migration' =>
        'public_html/system/Database/Migrations/'
        . 'CreateTicketingRequesterOnboardingFoundation.php',

    'registry' =>
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php',

    'service' =>
        'public_html/app/Services/Ticketing/'
        . 'TicketRequesterOnboardingService.php',

    'rbac' =>
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php',

    'navigation' =>
        'public_html/app/Services/'
        . 'DynamicAdminNavigationService.php',

    'feature' =>
        'public_html/app/Services/'
        . 'CoreFeatureRegistryService.php',

    'panel' =>
        'public_html/app/Services/'
        . 'AdminPanelService.php',

    'web' =>
        'public_html/routes/web.php',

    'routes' =>
        'public_html/routes/ticketing-requester.php',

    'view' =>
        'public_html/resources/views/admin/'
        . 'ticketing-requester-onboarding.php',
];

$content = [];

foreach ($files as $key => $relative) {
    $value =
        file_get_contents(
            $root . '/' . $relative
        );

    if (!is_string($value)) {
        throw new RuntimeException(
            'Unreadable: ' . $relative
        );
    }

    $content[$key] = $value;
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
    'ticketing_support_project_requester_access',
    'ticketing_support_project_invites',
    'ticketing_support_project_invite_uses',
] as $marker) {
    $expect(
        str_contains(
            $content['migration'],
            $marker
        ),
        'Missing migration table: '
        . $marker
    );
}

foreach ([
    'joinOpen',
    'joinWithCode',
    'createInvite',
    'hasMembership',
    "'requester'",
] as $marker) {
    $expect(
        str_contains(
            $content['service'],
            $marker
        ),
        'Missing service contract: '
        . $marker
    );
}

$expect(
    str_contains(
        $content['rbac'],
        'isRequesterTicketingPath'
    ),
    'Requester RBAC missing.'
);

$expect(
    str_contains(
        $content['navigation'],
        'REQUESTER_TICKETING_NAVIGATION_RUNTIME'
    ),
    'Requester navigation missing.'
);

$expect(
    !str_contains(
        $content['feature'],
        'REQUESTER_TICKETING_SEPARATE_CARD_RUNTIME'
    )
    &&
    !str_contains(
        $content['feature'],
        "'ticketing-entry'"
    ),
    'Synthetic Ticketing dashboard card remains.'
);

foreach ([
    'UNIFIED_TICKETING_DASHBOARD_ENTRY_RUNTIME',
    "'support.view'",
    "'ticketing.ticket.view'",
    'hasStaffMembership',
    '/admin/support/ticketing',
] as $marker) {
    $expect(
        str_contains(
            $content['panel'],
            $marker
        ),
        'Unified Ticketing dashboard contract missing: '
        . $marker
    );
}

$expect(
    str_contains(
        $content['service'],
        'hasStaffMembership'
    ),
    'Staff project-membership detector missing.'
);

$expect(
    str_contains(
        $content['routes'],
        'T7A2_STAFF_AWARE_ENTRY'
    ),
    'Staff-aware Ticketing entry missing.'
);

$expect(
    str_contains(
        $content['web'],
        "require BASE_PATH . '/routes/ticketing-requester.php';"
    ),
    'Requester route loader missing.'
);

foreach ([
    'پروژه‌های من',
    'عضویت در پروژه',
    'کد دعوت',
    'کد عضویت',
    'تیکت‌های من',
    'تیکت جدید',
    'ticketing-project-row',
] as $marker) {
    $expect(
        str_contains(
            $content['view'],
            $marker
        ),
        'Requester UI missing: '
        . $marker
    );
}

echo "TICKETING_REQUESTER_ONBOARDING_FOUNDATION_PASS\n";
