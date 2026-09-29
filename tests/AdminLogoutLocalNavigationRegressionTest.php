<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$file =
    $root
    . '/public_html/app/Services/'
    . 'DynamicAdminNavigationService.php';

$source =
    (string) file_get_contents(
        $file
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

$expect(
    str_contains(
        $source,
        'LOCAL_HOST_LOGOUT_NAVIGATION_V1'
    ),
    'Local logout navigation marker missing.'
);

$expect(
    preg_match(
        "#===\\s*'/admin/logout'#",
        $source
    ) === 1,
    'Logout route is not recognized locally.'
);

$expect(
    preg_match(
        "#return\\s+'/admin/logout';#",
        $source
    ) === 1,
    'Logout navigation is not host-relative.'
);

$markerPosition =
    strpos(
        $source,
        'LOCAL_HOST_LOGOUT_NAVIGATION_V1'
    );

$registryPosition =
    strpos(
        $source,
        'new ApplicationUrlRegistry()',
        $markerPosition
    );

$expect(
    $registryPosition !== false,
    'Application URL qualification missing.'
);

$localReturnPosition =
    strpos(
        $source,
        "return '/admin/logout';",
        $markerPosition
    );

$expect(
    $localReturnPosition !== false
    && $localReturnPosition
        < $registryPosition,
    'Logout is still qualified cross-origin before local handling.'
);

echo "ADMIN_LOGOUT_LOCAL_NAVIGATION_REGRESSION_PASS\n";
