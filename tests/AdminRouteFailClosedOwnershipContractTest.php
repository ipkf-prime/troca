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
                . ltrim(
                    $relative,
                    '/'
                )
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'cannot_read:'
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

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$dynamic =
    $read(
        'public_html/app/Services/'
        . 'DynamicRouteAccessService.php'
    );

$web =
    $read(
        'public_html/routes/web.php'
    );

$communication =
    $read(
        'public_html/routes/'
        . 'communication-center.php'
    );

$notifications =
    $read(
        'public_html/routes/'
        . 'notifications.php'
    );

$profile =
    $read(
        'public_html/routes/'
        . 'user-profile-hotfix.php'
    );

$expect(
    str_contains(
        $rbac,
        'GENERIC_ADMIN_UNKNOWN_PATH_FAIL_CLOSED_V1'
    ),
    'fail_closed_marker_missing'
);

$expect(
    !str_contains(
        $rbac,
        '$permission === null'
        . "\n"
        . '            ||'
    ),
    'legacy_fail_open_return_remains'
);

$expect(
    str_contains(
        $rbac,
        'DYNAMIC_ADMIN_ROUTE_OWNERSHIP_V1'
    )
    &&
    str_contains(
        $rbac,
        'EXPLICIT_CANONICAL_COARSE_GUARD_V1'
    ),
    'explicit_ownership_chain_missing'
);

$expect(
    str_contains(
        $dynamic,
        'public function decision('
    )
    &&
    str_contains(
        $dynamic,
        'return null;'
    ),
    'dynamic_tristate_contract_missing'
);

$expect(
    str_contains(
        $rbac,
        "'/admin/profile/edit' => 'account.profile.view'"
    ),
    'profile_edit_owner_missing'
);

foreach (
    [
        '/metadata',
        '/remove',
    ]
    as $suffix
) {
    $expect(
        str_contains(
            $rbac,
            "'/admin/automation/correspondences/{public_reference}/attachments/{file_reference}"
            . $suffix
            . "' => 'automation.correspondence.edit_draft'"
        ),
        'automation_attachment_write_owner_missing:'
        . $suffix
    );
}

foreach (
    [
        '/admin/communications',
        '/admin/communications/settings',
        '/admin/messages/inbox',
        '/admin/messages/compose',
        '/admin/messages/sent',
        '/admin/messages/thread',
        '/admin/messages/monitor',
    ]
    as $path
) {
    $expect(
        str_contains(
            $rbac,
            "'" . $path . "' => ["
        ),
        'coarse_owner_missing:'
        . $path
    );
}

$expect(
    str_contains(
        $rbac,
        "\$path ===\n            '/admin/notifications'"
    ),
    'notification_self_service_owner_missing'
);

$expect(
    str_contains(
        $web,
        '->canAccessPath('
    )
    &&
    str_contains(
        $web,
        '$requestMethod'
    )
    &&
    str_contains(
        $web,
        '$requestPath'
    ),
    'admin_guard_request_context_missing'
);

$expect(
    str_contains(
        $communication,
        'new \\App\\Services\\DynamicRouteAccessService()'
    ),
    'communication_second_stage_dynamic_auth_missing'
);

$expect(
    str_contains(
        $notifications,
        "->markRead(\n"
    )
    &&
    str_contains(
        $notifications,
        "(int) \$context['user_id']"
    ),
    'notification_owner_filter_contract_missing'
);

$expect(
    str_contains(
        $profile,
        "'/admin/profile/edit'"
    )
    &&
    str_contains(
        $profile,
        'SelfProfileService'
    ),
    'profile_self_service_contract_missing'
);

require_once
    $root
    . '/public_html/vendor/autoload.php';

require_once
    $root
    . '/public_html/system/Support/helpers.php';

$service =
    new \App\Services\AdminNavigationRbacService();

$expect(
    $service->canAccessPath(
        null,
        '/admin/__definitely_unowned__',
        'GET',
        '/admin/__definitely_unowned__'
    ) === false,
    'anonymous_unknown_admin_path_not_denied'
);

$expect(
    $service->canAccessPath(
        null,
        '/admin/notifications',
        'GET',
        '/admin/notifications'
    ) === false,
    'anonymous_notification_self_service_not_denied'
);

echo
    "ADMIN_ROUTE_FAIL_CLOSED_OWNERSHIP_CONTRACT=PASS\n";

echo
    "UNKNOWN_ADMIN_PATH=DENY\n";

echo
    "DYNAMIC_ROUTE_DECISION=TRISTATE\n";

echo
    "AUTHENTICATED_SELF_SERVICE=EXPLICIT_ONLY\n";

echo
    "CANONICAL_COARSE_GUARDS=EXPLICIT_ONLY\n";
