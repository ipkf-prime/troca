<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$routes = (string) file_get_contents(
    $root . '/public_html/routes/web.php'
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

foreach (
    [
        'FEDERATED_LOGOUT_ORIGIN_GUARD_V1',
        'EXACT_LOGOUT_RETURN_PATH_V1',
        'EXPLICIT_LOGIN_RETURN_PATH_V1',
        "'return_path'",
        "'federated'",
        'HTTP_REFERER',
        'hash_equals(',
        'rawurlencode(',
    ]
    as $needle
) {
    $expect(
        str_contains(
            $routes,
            $needle
        ),
        'Logout return contract missing: '
        . $needle
    );
}

$expect(
    !str_contains(
        $routes,
        "input('return_path', '/admin/automation')"
    ),
    'Legacy automation SSO default remains.'
);

echo "ADMIN_LOGOUT_EXACT_RETURN_REGRESSION_PASS\n";
