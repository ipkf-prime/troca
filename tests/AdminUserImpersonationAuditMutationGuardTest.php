<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$lifecycle =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'ImpersonationSessionLifecycleService.php'
    );

$middleware =
    file_get_contents(
        $root
        . '/public_html/system/Http/Middleware/'
        . 'ImpersonationMutationGuardMiddleware.php'
    );

$kernel =
    file_get_contents(
        $root
        . '/public_html/system/Http/Kernel.php'
    );

$audit =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'ImpersonationAuditService.php'
    );

$routes =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

if (
    !is_string($lifecycle)
    || !is_string($middleware)
    || !is_string($kernel)
    || !is_string($audit)
    || !is_string($routes)
) {
    throw new RuntimeException(
        'Unable to read S4B sources.'
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
    'public function record(',
    'string $eventCode',
    'int $actorUserId',
    'int $effectiveUserId',
    '?string $reasonCode = null',
    'array $metadata = []',
] as $token) {
    $expect(
        str_contains(
            $audit,
            $token
        ),
        'Audit contract token missing: '
        . $token
    );
}

echo "S4B_AUDIT_API=PASS\n";


$currentUserIdPosition =
    strpos(
        $lifecycle,
        '$this->auth->currentUserId()'
    );

$currentUserPosition =
    strpos(
        $lifecycle,
        '$this->auth->currentUser()'
    );

$snapshotPosition =
    strpos(
        $lifecycle,
        '->impersonationAuthSnapshot()'
    );

$authorizationPosition =
    strpos(
        $lifecycle,
        '->decide('
    );

$expect(
    $currentUserIdPosition !== false
    && $currentUserPosition !== false
    && $snapshotPosition !== false
    && $authorizationPosition !== false,
    'Start validation positions missing.'
);

$expect(
    $currentUserIdPosition
        < $currentUserPosition
    && $currentUserPosition
        < $snapshotPosition
    && $snapshotPosition
        < $authorizationPosition,
    'Actor authentication is not revalidated before start authorization.'
);

echo "S4B_ACTOR_REVALIDATION_BEFORE_START=PASS\n";


foreach ([
    "'impersonation_started'",
    "'impersonation_ended'",
    "'impersonation_expired'",
    "'impersonation_start_denied'",
    "'impersonation_restore_denied'",
    'impersonation_audit_failed',
    'auditBestEffort(',
] as $token) {
    $expect(
        str_contains(
            $lifecycle,
            $token
        ),
        'Lifecycle audit token missing: '
        . $token
    );
}

echo "S4B_LIFECYCLE_AUDIT_WIRING=PASS\n";


foreach ([
    'final class ImpersonationMutationGuardMiddleware',
    "'POST'",
    "'PUT'",
    "'PATCH'",
    "'DELETE'",
    "'POST /auth/logout'",
    "'GET /admin/logout'",
    'FINAL_LOGOUT_ENDPOINTS',
    'MUTATION_ALLOWLIST',
    '->enforceExpiry()',
    'parse_url(',
    'PHP_URL_PATH',
    '$requestKey',
    '$isFinalLogout',
    '$lifecycle->restore()',
    "'impersonation_mutation_blocked'",
    "'read_only_impersonation'",
    "'http_method'",
    "'request_path'",
    '->status(403)',
] as $token) {
    $expect(
        str_contains(
            $middleware,
            $token
        ),
        'Mutation guard token missing: '
        . $token
    );
}

echo "S4B_MUTATION_GUARD_CONTRACT=PASS\n";


/*
 * Both existing session-destroying logout routes must
 * be represented as exact final-logout endpoints.
 */
$expect(
    substr_count(
        $routes,
        "\$router->get('/admin/logout'"
    ) === 1,
    'Legacy GET logout route count invalid.'
);

$expect(
    substr_count(
        $routes,
        "\$router->post('/auth/logout'"
    ) === 1,
    'POST auth logout route count invalid.'
);

$expect(
    str_contains(
        $middleware,
        "'GET /admin/logout'"
    )
    && str_contains(
        $middleware,
        "'POST /auth/logout'"
    ),
    'Final logout endpoint coverage incomplete.'
);

echo "S4B_ALL_LOGOUT_SURFACES_COVERED=PASS\n";


/*
 * URI query material must not influence exact endpoint
 * classification.
 */
$uriPosition =
    strpos(
        $middleware,
        '$request->uri()'
    );

$parsePosition =
    strpos(
        $middleware,
        'parse_url('
    );

$requestKeyPosition =
    strpos(
        $middleware,
        '$requestKey'
    );

$expect(
    $uriPosition !== false
    && $parsePosition !== false
    && $requestKeyPosition !== false
    && $uriPosition < $parsePosition
    && $parsePosition < $requestKeyPosition,
    'Request URI path normalization order invalid.'
);

echo "S4B_LOGOUT_QUERY_STRING_NORMALIZATION=PASS\n";


$expiryPosition =
    strpos(
        $middleware,
        '->enforceExpiry()'
    );

$finalLogoutBranch =
    strpos(
        $middleware,
        'if ($isFinalLogout)'
    );

$mutationClassification =
    strpos(
        $middleware,
        'self::MUTATING_METHODS'
    );

$restorePosition =
    strpos(
        $middleware,
        '$lifecycle->restore()'
    );

$blockedAudit =
    strpos(
        $middleware,
        "'impersonation_mutation_blocked'"
    );

$expect(
    $expiryPosition !== false
    && $finalLogoutBranch !== false
    && $mutationClassification !== false
    && $restorePosition !== false
    && $blockedAudit !== false,
    'Middleware control-flow positions missing.'
);

$expect(
    $expiryPosition < $finalLogoutBranch,
    'Expiry must run before final logout handling.'
);

$expect(
    $restorePosition < $blockedAudit,
    'Final logout restore ordering invalid.'
);

echo "S4B_FINAL_LOGOUT_LIFECYCLE_ORDER=PASS\n";


/*
 * The allow-list is exact method+path, not path-only.
 */
$expect(
    str_contains(
        $middleware,
        "'POST /auth/logout'"
    )
    && !str_contains(
        $middleware,
        "private const MUTATION_ALLOWLIST = [\n        '/auth/logout'"
    ),
    'Mutation allow-list is not exact method+path.'
);

echo "S4B_EXACT_METHOD_PATH_ALLOWLIST=PASS\n";


/*
 * Expired/invalid final logout can proceed only to an
 * exact logout endpoint. Arbitrary requests remain
 * blocked after identity transition/termination.
 */
foreach ([
    "'impersonation_expired_restored'",
    "'impersonation_session_terminated'",
    "'impersonation_logout_restore_failed'",
] as $token) {
    $expect(
        str_contains(
            $middleware,
            $token
        ),
        'Final logout failure token missing: '
        . $token
    );
}

echo "S4B_FINAL_LOGOUT_FAIL_CLOSED=PASS\n";


$expect(
    !str_contains(
        $middleware,
        'Session::'
    ),
    'Direct Session access detected in mutation guard.'
);

echo "S4B_MIDDLEWARE_DIRECT_SESSION_ACCESS=NO\n";


$csrfPosition =
    strpos(
        $kernel,
        '\\IPKF\\Http\\Middleware\\CsrfMiddleware::class'
    );

$guardPosition =
    strpos(
        $kernel,
        '\\IPKF\\Http\\Middleware\\ImpersonationMutationGuardMiddleware::class'
    );

$expect(
    $csrfPosition !== false
    && $guardPosition !== false
    && $csrfPosition < $guardPosition,
    'Mutation guard must execute after CSRF.'
);

echo "S4B_KERNEL_ORDER=PASS\n";


$expect(
    !str_contains(
        $middleware,
        "'message'"
    )
    && !str_contains(
        $middleware,
        '"message"'
    ),
    'Hardcoded middleware UI message detected.'
);

echo "S4B_HARDCODED_UI_COPY=NO\n";


foreach ([
    "'admin'",
    '"admin"',
    "'manager'",
    '"manager"',
    "'staff'",
    '"staff"',
] as $literal) {
    $expect(
        !str_contains(
            strtolower($middleware),
            strtolower($literal)
        ),
        'Role-name hardcode in mutation guard: '
        . $literal
    );
}

echo "S4B_ROLE_NAME_AUTHORIZATION=NO\n";
echo "ADMIN_USER_IMPERSONATION_S4B_AUDIT_MUTATION_GUARD=PASS\n";
