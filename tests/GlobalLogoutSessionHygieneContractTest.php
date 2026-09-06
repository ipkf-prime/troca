<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$auth =
    file_get_contents(
        $root
        . '/public_html/app/Services/AuthService.php'
    );

$session =
    file_get_contents(
        $root
        . '/public_html/system/Support/Session.php'
    );

$web =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

foreach ([
    'auth' => $auth,
    'session' => $session,
    'web' => $web,
] as $label => $value) {
    if (!is_string($value)) {
        throw new RuntimeException(
            $label
            . '_source_unreadable'
        );
    }
}

foreach ([
    "Session::forget('auth_user_id');",
    "Session::forget('active_role_assignment_id');",
    'Session::destroy();',
] as $marker) {
    if (
        strpos(
            $auth,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'auth_logout_marker_missing:'
            . $marker
        );
    }
}

foreach ([
    'public static function destroy(): void',
    'legacyCookieDomains()',
    'commonCookieDomain(',
    'expireCookie(',
    "Env::get(\n                    'AUTH_COOKIE_DOMAIN'",
    "Env::get(\n                    'CORE_APP_URL'",
    'session_destroy();',
] as $marker) {
    if (
        strpos(
            $session,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'session_hygiene_marker_missing:'
            . $marker
        );
    }
}

foreach ([
    'GLOBAL_LOGOUT_SESSION_HYGIENE',
    "'/admin/logout'",
    'logout_step',
    'return_module',
    '$runtime->allActive()',
    'applicationModuleHost(',
    "'Clear-Site-Data'",
    '\'"cache"\'',
    'no-store, no-cache, must-revalidate, max-age=0',
    '/auth/module-sso/start',
] as $marker) {
    if (
        strpos(
            $web,
            $marker
        ) === false
    ) {
        throw new RuntimeException(
            'global_logout_marker_missing:'
            . $marker
        );
    }
}

if (
    strpos(
        $session,
        '.troca.ir'
    ) !== false
) {
    throw new RuntimeException(
        'deployment_domain_must_not_be_hardcoded'
    );
}

echo
    "GLOBAL_LOGOUT_SESSION_HYGIENE_CONTRACT_PASS"
    . PHP_EOL;
