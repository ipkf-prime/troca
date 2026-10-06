<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $source =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($source)) {
            throw new RuntimeException(
                'Unable to read '
                . $relative
            );
        }

        return $source;
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

$lifecycle =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationSessionLifecycleService.php'
    );

$middleware =
    $read(
        'public_html/system/Http/Middleware/'
        . 'ImpersonationMutationGuardMiddleware.php'
    );

$kernel =
    $read(
        'public_html/system/Http/Kernel.php'
    );

$audit =
    $read(
        'public_html/app/Services/'
        . 'ImpersonationAuditService.php'
    );


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


$currentUser =
    strpos(
        $lifecycle,
        '$this->auth->currentUser()'
    );

$snapshot =
    strpos(
        $lifecycle,
        '->impersonationAuthSnapshot()'
    );

$decide =
    strpos(
        $lifecycle,
        '->decide('
    );

$expect(
    $currentUser !== false
    && $snapshot !== false
    && $decide !== false
    && $currentUser < $snapshot
    && $snapshot < $decide,
    'Actor revalidation order invalid.'
);

echo "S4B_ACTOR_REVALIDATION_BEFORE_START=PASS\n";


foreach ([
    "'impersonation_started'",
    "'impersonation_ended'",
    "'impersonation_expired'",
    "'impersonation_start_denied'",
    "'impersonation_restore_denied'",
] as $token) {
    $expect(
        str_contains(
            $lifecycle,
            $token
        ),
        'Lifecycle audit event missing: '
        . $token
    );
}

echo "S4B_LIFECYCLE_AUDIT_WIRING=PASS\n";


foreach ([
    'public function terminateForLogout()',
    "'terminal_logout'",
    "'final_logout'",
    "'ttl_expired_final_logout'",
    '->logout()',
] as $token) {
    $expect(
        str_contains(
            $lifecycle,
            $token
        ),
        'Terminal logout token missing: '
        . $token
    );
}

$terminateStart =
    strpos(
        $lifecycle,
        'public function terminateForLogout()'
    );

$enforceStart =
    strpos(
        $lifecycle,
        'public function enforceExpiry()'
    );

$terminateBlock =
    substr(
        $lifecycle,
        $terminateStart,
        $enforceStart - $terminateStart
    );

$expect(
    !str_contains(
        $terminateBlock,
        'restoreImpersonationAuthSnapshot'
    )
    && !str_contains(
        $terminateBlock,
        'restoreState('
    ),
    'Terminal logout must not restore Actor.'
);

echo "S4B_TERMINAL_LOGOUT_ACTOR_RESTORE=NO\n";


foreach ([
    "'POST'",
    "'PUT'",
    "'PATCH'",
    "'DELETE'",
    "'GET /admin/logout'",
    "'POST /auth/logout'",
    "'POST /admin/impersonation/stop'",
    'EXPLICIT_STOP_ENDPOINT',
    '->terminateForLogout()',
    '->enforceExpiry()',
    "'impersonation_mutation_blocked'",
    "'read_only_impersonation'",
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


$methodPosition =
    strpos(
        $middleware,
        '$request->method()'
    );

$terminalPosition =
    strpos(
        $middleware,
        '->terminateForLogout()'
    );

$expiryPosition =
    strpos(
        $middleware,
        '->enforceExpiry()'
    );

$blockedAudit =
    strpos(
        $middleware,
        "'impersonation_mutation_blocked'"
    );

$deny =
    strpos(
        $middleware,
        '->status(403)'
    );

$expect(
    $methodPosition !== false
    && $terminalPosition !== false
    && $expiryPosition !== false
    && $methodPosition < $terminalPosition
    && $terminalPosition < $expiryPosition,
    'Final logout must be terminal before expiry enforcement.'
);

$expect(
    $blockedAudit !== false
    && $deny !== false
    && $blockedAudit < $deny,
    'Mutation block audit must precede 403.'
);

$expect(
    !str_contains(
        $middleware,
        '$lifecycle->restore()'
    ),
    'Middleware must not restore Actor on final logout.'
);

echo "S4B_TERMINAL_LOGOUT_BEFORE_EXPIRY=PASS\n";
echo "S4B_MUTATION_AUDIT_BEFORE_DENY=PASS\n";


$csrf =
    strpos(
        $kernel,
        'CsrfMiddleware::class'
    );

$guard =
    strpos(
        $kernel,
        'ImpersonationMutationGuardMiddleware::class'
    );

$expect(
    $csrf !== false
    && $guard !== false
    && $csrf < $guard,
    'Mutation guard must execute after CSRF.'
);

echo "S4B_KERNEL_ORDER=PASS\n";


$expect(
    !str_contains(
        $middleware,
        'Session::'
    ),
    'Direct Session access detected in guard.'
);

echo "S4B_MIDDLEWARE_DIRECT_SESSION_ACCESS=NO\n";


$expect(
    !str_contains(
        $middleware,
        "'message'"
    )
    && !str_contains(
        $middleware,
        '"message"'
    ),
    'Hardcoded UI message detected in guard.'
);

echo "S4B_HARDCODED_UI_COPY=NO\n";

echo "ADMIN_USER_IMPERSONATION_S4B_AUDIT_MUTATION_GUARD=PASS\n";
