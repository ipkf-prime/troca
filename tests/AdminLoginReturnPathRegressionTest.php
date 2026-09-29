<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read =
    static fn (string $file): string =>
        (string) file_get_contents(
            $root . '/' . $file
        );

$routes = $read(
    'public_html/routes/web.php'
);

$sso = $read(
    'public_html/app/Services/ModuleSsoService.php'
);

$auth = $read(
    'public_html/app/Services/AuthService.php'
);

$local = $read(
    'public_html/app/Services/AdminLoginReturnPathService.php'
);

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
    'AdminLoginReturnPathService',
    'INTENT_TTL_SECONDS',
    'rememberCurrentRequest',
    'rememberFromSameHostReferer',
    'HTTP_REFERER',
    'HTTP_HOST',
    "\$parsed['scheme']",
    "\$parsed['host']",
    "\$parsed['query']",
] as $needle) {
    $expect(
        str_contains(
            $local,
            $needle
        ),
        'Local return-path contract missing: '
        . $needle
    );
}


foreach ([
    'MODULE_SSO_INTENT_TTL_V2',
    'INTENT_TTL_SECONDS',
    "'created_at'",
    'pendingReturnPath()',
    "'intent_expired'",
] as $needle) {
    $expect(
        str_contains(
            $sso,
            $needle
        ),
        'Module stale-intent protection missing: '
        . $needle
    );
}


foreach ([
    'ADMIN_LOGIN_RETURN_PATH_V1',
    'DYNAMIC_MODULE_SSO_DEFAULT_V1',
    'CORE_LOGIN_REFERER_RETURN_V1',
    'rememberCurrentRequest(',
    'rememberFromSameHostReferer()',
    'forgetPendingIntent()',
    'applicationModuleKeyForHost(',
] as $needle) {
    $expect(
        str_contains(
            $routes,
            $needle
        ),
        'Route contract missing: '
        . $needle
    );
}


$expect(
    !str_contains(
        $routes,
        "input('return_path', '/admin/automation')"
    ),
    'Module-specific SSO default remains.'
);


$expect(
    str_contains(
        $auth,
        'ADMIN_LOGIN_RETURN_LOGOUT_V1'
    )
    && str_contains(
        $auth,
        'AdminLoginReturnPathService'
    ),
    'Logout does not clear local return intent.'
);


echo "ADMIN_LOGIN_RETURN_PATH_REGRESSION_PASS\n";
