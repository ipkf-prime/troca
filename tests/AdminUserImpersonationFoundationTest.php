<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $path =
            $root
            . '/'
            . ltrim(
                $relative,
                '/'
            );

        if (!is_file($path)) {
            fwrite(
                STDERR,
                "Missing file: {$relative}\n"
            );
            exit(1);
        }

        $content =
            file_get_contents(
                $path
            );

        if (!is_string($content)) {
            fwrite(
                STDERR,
                "Unreadable file: {$relative}\n"
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
        if ($condition) {
            return;
        }

        fwrite(
            STDERR,
            $message
            . PHP_EOL
        );

        exit(1);
    };

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'CreateAdminUserImpersonationFoundation.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'ImpersonationAuditRepository.php'
    );

$auditService =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationAuditService.php'
    );

$contextService =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationContextService.php'
    );


foreach ([
    'users.impersonate',
    "'core'",
    "'users'",
    "'impersonate'",
    'is_sensitive',
    'auth_impersonation_events',
    'actor_user_id',
    'effective_user_id',
    'event_code',
    'reason_code',
    'request_id',
    'correlation_id',
    'ip_address',
    'user_agent',
    'metadata_json',
    'occurred_at',
    'ENGINE=InnoDB',
] as $token) {
    $expect(
        str_contains(
            $migration,
            $token
        ),
        'Impersonation migration missing token: '
        . $token
    );
}

$expect(
    str_contains(
        $migration,
        'roles.can_manage_other_users = 1'
    ),
    'users.impersonate must bootstrap from dynamic manager capability.'
);

foreach ([
    "'super_admin'",
    "'system_admin'",
    "'central_admin'",
    "'province_admin'",
    "'county_admin'",
    "'company_admin'",
] as $roleLiteral) {
    $expect(
        !str_contains(
            $migration,
            $roleLiteral
        ),
        'Impersonation bootstrap must not hardcode role identities: '
        . $roleLiteral
    );
}

$expect(
    !preg_match(
        '/\bFOREIGN\s+KEY\b/i',
        $migration
    ),
    'Dedicated impersonation audit must not depend on user foreign keys.'
);

$expect(
    !preg_match(
        '/\bDROP\s+TABLE\b/i',
        $migration
    ),
    'Impersonation audit rollback must remain non-destructive.'
);

$expect(
    substr_count(
        $registry,
        'CreateAdminUserImpersonationFoundation::class'
    ) === 1,
    'Impersonation migration registration must be unique.'
);

echo
    "IMPERSONATION_PERMISSION_AND_MIGRATION=PASS\n";


foreach ([
    'impersonation_started',
    'impersonation_ended',
    'impersonation_expired',
    'impersonation_start_denied',
    'impersonation_restore_denied',
    'impersonation_mutation_blocked',
    'INSERT INTO auth_impersonation_events',
    'SecretMasker::sanitize',
    'RequestContext::requestId()',
    'RequestContext::correlationId()',
] as $token) {
    $expect(
        str_contains(
            $repository,
            $token
        ),
        'Dedicated impersonation audit repository missing: '
        . $token
    );
}

$expect(
    !preg_match(
        '/\bUPDATE\s+auth_impersonation_events\b/i',
        $repository
    )
    && !preg_match(
        '/\bDELETE\s+FROM\s+auth_impersonation_events\b/i',
        $repository
    ),
    'Dedicated impersonation audit repository must be insert-only.'
);

echo
    "IMPERSONATION_DEDICATED_AUDIT_STORE=PASS\n";


foreach ([
    'DatabaseAuditLogger',
    'Auth.Impersonation.Started',
    'Auth.Impersonation.Ended',
    'Auth.Impersonation.Expired',
    'Auth.Impersonation.StartDenied',
    'Auth.Impersonation.RestoreDenied',
    'Auth.Impersonation.MutationBlocked',
    'beginTransaction()',
    'commit()',
    'rollBack()',
    "'effective_user_id'",
    "'impersonation_event_reference'",
] as $token) {
    $expect(
        str_contains(
            $auditService,
            $token
        ),
        'Dual impersonation audit service missing: '
        . $token
    );
}

echo
    "IMPERSONATION_DUAL_AUDIT_FOUNDATION=PASS\n";


foreach ([
    "'auth_impersonation'",
    'DEFAULT_TTL_MINUTES',
    'IMPERSONATION_TTL_MINUTES',
    "'auth_user_id'",
    "'auth_password_fingerprint'",
    "'auth_login_at'",
    "'auth_mfa_verified'",
    "'active_role_assignment_id'",
    "'actor_user_id'",
    "'effective_user_id'",
    "'started_at'",
    "'expires_at'",
    "'nonce'",
    "'actor_auth_snapshot'",
    "'return_path'",
] as $token) {
    $expect(
        str_contains(
            $contextService,
            $token
        ),
        'Impersonation context foundation missing: '
        . $token
    );
}

foreach ([
    'Session::put',
    'Session::forget',
    'Session::regenerate',
    'Session::destroy',
] as $forbidden) {
    $expect(
        !str_contains(
            $contextService,
            $forbidden
        ),
        'S3 must not mutate authentication session: '
        . $forbidden
    );
}

echo
    "IMPERSONATION_CONTEXT_SOURCE_CONTRACT=PASS\n";


require_once
    $root
    . '/public_html/app/Services/'
    . 'ImpersonationContextService.php';

$service =
    new \App\Services\ImpersonationContextService();

$startedAt =
    new DateTimeImmutable(
        '2026-10-05 12:00:00',
        new DateTimeZone(
            'UTC'
        )
    );

$context =
    $service->create(
        100,
        200,
        [
            'auth_user_id' => 100,
            'auth_password_fingerprint' =>
                'fingerprint-test-value',
            'auth_login_at' =>
                '2026-10-05 11:00:00',
            'auth_mfa_verified' =>
                true,
            'active_role_assignment_id' =>
                55,
            'password' =>
                'must-not-survive',
            'otp' =>
                'must-not-survive',
            'token' =>
                'must-not-survive',
        ],
        'abcdefghijklmnop1234567890',
        '/admin/users/200',
        $startedAt,
        30
    );

$expect(
    !array_key_exists(
        'password',
        $context[
            'actor_auth_snapshot'
        ]
    )
    && !array_key_exists(
        'otp',
        $context[
            'actor_auth_snapshot'
        ]
    )
    && !array_key_exists(
        'token',
        $context[
            'actor_auth_snapshot'
        ]
    ),
    'Secret fields escaped actor snapshot whitelist.'
);

$active =
    $service->inspect(
        $context,
        new DateTimeImmutable(
            '2026-10-05 12:29:59',
            new DateTimeZone(
                'UTC'
            )
        )
    );

$expired =
    $service->inspect(
        $context,
        new DateTimeImmutable(
            '2026-10-05 12:30:00',
            new DateTimeZone(
                'UTC'
            )
        )
    );

$expect(
    ($active['valid'] ?? false) === true
    && ($active['active'] ?? false) === true
    && ($active['expired'] ?? true) === false,
    'Active impersonation context validation failed.'
);

$expect(
    ($expired['valid'] ?? false) === true
    && ($expired['active'] ?? true) === false
    && ($expired['expired'] ?? false) === true,
    'Expired impersonation context validation failed.'
);

$expect(
    ($context['return_path'] ?? '')
        === '/admin/users/200',
    'Safe local return path was not preserved.'
);

$unsafe =
    $service->create(
        100,
        201,
        [
            'auth_user_id' =>
                100,
        ],
        'abcdefghijklmnop0987654321',
        'https://evil.example.test/path',
        $startedAt,
        30
    );

$expect(
    ($unsafe['return_path'] ?? '')
        === '/admin/users',
    'External return path must fail closed.'
);

echo
    "IMPERSONATION_CONTEXT_RUNTIME_PROOF=PASS\n";

echo
    "ADMIN_USER_IMPERSONATION_S3_FOUNDATION=PASS\n";

echo
    "DATABASE_MUTATION=NO\n";

echo
    "SESSION_MUTATION=NO\n";

echo
    "RUNTIME_ACTIVATION=NO\n";
