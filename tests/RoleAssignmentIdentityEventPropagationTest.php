<?php

declare(strict_types=1);

$root = dirname(__DIR__);

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
            fwrite(
                STDERR,
                "FAIL: {$relative} unavailable.\n"
            );

            exit(1);
        }

        return $content;
    };

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            fwrite(
                STDERR,
                "FAIL: {$message}\n"
            );

            exit(1);
        }
    };

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'EnsureTransactionalIdentityAccessGovernanceWriteSet.php'
    );

$transactionTest =
    $read(
        'tests/'
        . 'TransactionalIdentityAccessGovernanceWriteSetTest.php'
    );

$managementRepository =
    $read(
        'public_html/app/Repositories/'
        . 'AdminUserManagementRepository.php'
    );

$userRepository =
    $read(
        'public_html/app/Repositories/'
        . 'UserRepository.php'
    );

$selfProfile =
    $read(
        'public_html/app/Services/'
        . 'SelfProfileService.php'
    );

$identityChange =
    $read(
        'public_html/app/Services/'
        . 'IdentityChangeService.php'
    );

$selfIdentityChange =
    $read(
        'public_html/app/Services/'
        . 'SelfIdentityChangeService.php'
    );

$organization =
    $read(
        'public_html/app/Services/Organization/'
        . 'OrganizationOperationsService.php'
    );

$web =
    $read(
        'public_html/routes/web.php'
    );

$appointmentView =
    $read(
        'public_html/resources/views/admin/'
        . 'appointments.php'
    );

foreach (
    [
        'identity_change_requests',
        'user_org_assignments',
        'organization_appointments',
    ]
    as $table
) {
    $expect(
        str_contains(
            $migration,
            "'{$table}'"
        ),
        "Transactional migration missing {$table}."
    );

    $expect(
        str_contains(
            $transactionTest,
            "'{$table}'"
        ),
        "Transactional contract missing {$table}."
    );
}

$expect(
    str_contains(
        $managementRepository,
        '?callable $identityRefresher = null'
    )
    && str_contains(
        $managementRepository,
        "'role_assignment_lifecycle_refresher_required'"
    )
    && str_contains(
        $managementRepository,
        '$ownsTransaction'
    ),
    'Self-profile repository lifecycle transaction bridge incomplete.'
);

$expect(
    str_contains(
        $selfProfile,
        'RoleAssignmentLifecycleService'
    )
    && str_contains(
        $selfProfile,
        '->refreshUser('
    ),
    'Self-profile lifecycle refresh missing.'
);

$expect(
    str_contains(
        $userRepository,
        'users.mobile_verified_at = NULL'
    ),
    'Mobile identity change does not reset verification.'
);

foreach (
    [
        $identityChange,
        $selfIdentityChange,
    ]
    as $source
) {
    $expect(
        str_contains(
            $source,
            'RoleAssignmentLifecycleService'
        )
        && str_contains(
            $source,
            '->refreshUser('
        )
        && str_contains(
            $source,
            '$ownsTransaction'
        )
        && str_contains(
            $source,
            "'lifecycle_status_code'"
        ),
        'Identity-change lifecycle transaction wiring incomplete.'
    );
}

$expect(
    str_contains(
        $organization,
        'refreshLifecycleForPerson('
    )
    && str_contains(
        $organization,
        'RoleAssignmentLifecycleService'
    )
    && str_contains(
        $organization,
        '->refreshUser('
    )
    && str_contains(
        $organization,
        '$ownsTransaction'
    ),
    'Organization appointment lifecycle propagation incomplete.'
);

$postStart =
    strpos(
        $web,
        "\$router->post('/admin/appointments'"
    );

$postEnd =
    strpos(
        $web,
        "\$router->get('/admin/profile/organizational-context'",
        $postStart === false
            ? 0
            : $postStart
    );

$expect(
    $postStart !== false
    && $postEnd !== false
    && $postEnd > $postStart,
    'Appointment POST route not found.'
);

$postBlock =
    substr(
        $web,
        $postStart,
        $postEnd - $postStart
    );

$expect(
    str_contains(
        $postBlock,
        'new \\IPKF\\Security\\Csrf()'
    )
    && str_contains(
        $postBlock,
        "'_token'"
    )
    && str_contains(
        $postBlock,
        "status=invalid_csrf"
    ),
    'Appointment POST CSRF enforcement missing.'
);

$expect(
    str_contains(
        $postBlock,
        "(int) \$context['user_id']"
    ),
    'Appointment actor propagation missing.'
);

$expect(
    str_contains(
        $appointmentView,
        'name="_token"'
    )
    && str_contains(
        $appointmentView,
        'new \\IPKF\\Security\\Csrf()'
    ),
    'Appointment form CSRF token missing.'
);

echo "ORGANIZATION_APPOINTMENT_EVENT_REFRESH=PASS\n";
echo "SELF_PROFILE_EVENT_REFRESH=PASS\n";
echo "IDENTITY_CHANGE_EVENT_REFRESH=PASS\n";
echo "SELF_IDENTITY_CHANGE_EVENT_REFRESH=PASS\n";
echo "MOBILE_CHANGE_VERIFICATION_RESET=PASS\n";
echo "ORGANIZATION_EVENT_TABLES_TRANSACTIONAL_CONTRACT=PASS\n";
echo "APPOINTMENT_POST_CSRF_CONTRACT=PASS\n";
echo "ROLE_ASSIGNMENT_IDENTITY_EVENT_PROPAGATION=PASS\n";
