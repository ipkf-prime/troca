<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$read =
    static function (
        string $relative
    ) use ($root): string {

        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'Cannot read '
                . $relative
            );
        }

        return $value;
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


$router =
    $read(
        'public_html/system/Routing/Router.php'
    );

$request =
    $read(
        'public_html/system/Http/Request.php'
    );

$routes =
    $read(
        'public_html/routes/ticketing-runtime.php'
    );


$expect(
    str_contains(
        $router,
        '$controller($request, $response)'
    ),
    'Router must invoke route callbacks with Request and Response only.'
);


$expect(
    str_contains(
        $router,
        '$request->setRouteParams($params)'
    ),
    'Router must inject matched parameters into Request.'
);


$expect(
    str_contains(
        $request,
        'public function route('
    ),
    'Request::route accessor is required.'
);


$marker =
    strpos(
        $routes,
        'ticketing_staff_operations_a7'
    );

$expect(
    $marker !== false,
    'A7 staff route marker missing.'
);


$a7 =
    substr(
        $routes,
        (int) $marker
    );


$expect(
    !preg_match(
        '/function\s*\(\s*'
        . '\$request\s*,\s*'
        . '\$response\s*,\s*'
        . '\$publicReference\s*\)/',
        $a7
    ),
    'Staff operation callbacks must not require a third route argument.'
);


$expect(
    substr_count(
        $a7,
        '$request->route('
    ) === 3,
    'Take Over, Transfer and Escalate must read public_reference from Request::route().'
);


foreach ([
    '/admin/ticketing/staff/{public_reference}/takeover',
    '/admin/ticketing/staff/{public_reference}/transfer',
    '/admin/ticketing/staff/{public_reference}/escalate',
] as $route) {

    $expect(
        str_contains(
            $a7,
            "'{$route}'"
        ),
        'Missing staff operation route: '
        . $route
    );
}


echo
    "TICKETING_STAFF_ROUTE_PARAMETER_CONTRACT_PASS\n";
