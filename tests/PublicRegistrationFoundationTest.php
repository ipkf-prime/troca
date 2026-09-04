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
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Missing '
                . $relative
            );
        }

        return $content;
    };

$service =
    $read(
        'public_html/app/Services/'
        . 'PublicRegistrationService.php'
    );

$otpService =
    $read(
        'public_html/app/Services/'
        . 'PublicRegistrationOtpService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'public-registration.php'
    );

$view =
    $read(
        'public_html/resources/views/site/'
        . 'register.php'
    );

$loader =
    $read(
        'public_html/system/Routing/'
        . 'RouteLoader.php'
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
        $routes,
        "\$router->get(\n    '/register'"
    )
    && str_contains(
        $routes,
        "\$router->post(\n    '/register'"
    ),
    'Public registration GET/POST routes missing.'
);

$expect(
    str_contains(
        $routes,
        'new \App\Services\PublicRegistrationService()'
    ),
    'Registration service invocation is malformed.'
);

$expect(
    str_contains(
        $routes,
        "'public_registration_success'"
    )
    && str_contains(
        $routes,
        '\IPKF\Support\Session::put('
    )
    && str_contains(
        $routes,
        '\IPKF\Support\Session::get('
    )
    && str_contains(
        $routes,
        '\IPKF\Support\Session::forget('
    ),
    'Registration success flash lifecycle missing.'
);

$expect(
    !str_contains(
        $routes,
        '/register?status=success'
    ),
    'Registration success must not depend on query-string state.'
);

$expect(
    str_contains(
        $routes,
        'session_write_close()'
    )
    && str_contains(
        $routes,
        'PHP_SESSION_ACTIVE'
    ),
    'Registration success session state is not explicitly persisted before redirect.'
);

$expect(
    str_contains(
        $routes,
        'int $httpStatus = 200'
    )
    && str_contains(
        $routes,
        '->status($httpStatus)'
    )
    && !str_contains(
        $routes,
        'int $status = 200'
    ),
    'Registration view status must not be shadowed by HTTP status.'
);

$expect(
    str_contains(
        $routes,
        'new \IPKF\Security\Csrf()'
    )
    && str_contains(
        $routes,
        "'_token'"
    ),
    'Registration CSRF protection missing.'
);

$expect(
    str_contains(
        $service,
        "resolve('core.primary')"
    )
    && str_contains(
        $service,
        'GET_LOCK'
    )
    && str_contains(
        $service,
        'RELEASE_LOCK'
    ),
    'Registration concurrency lock missing.'
);

$expect(
    str_contains(
        $service,
        'INSERT INTO persons'
    )
    && str_contains(
        $service,
        'INSERT INTO users'
    )
    && str_contains(
        $service,
        'password_hash('
    ),
    'Person/user creation contract missing.'
);

$expect(
    str_contains(
        $service,
        "'pending_verification'"
    )
    && !str_contains(
        $service,
        'user_role_assignments'
    ),
    'Pending registration must not assign a role before mobile verification.'
);

$expect(
    str_contains(
        $otpService,
        'ensureExactlyBaseRole'
    )
    && str_contains(
        $otpService,
        "WHERE code = 'user'"
    )
    && str_contains(
        $otpService,
        'is_default'
    )
    && str_contains(
        $otpService,
        "'global'"
    ),
    'Verified registration must assign exactly the global default user role.'
);

$expect(
    !str_contains(
        $service,
        "'super_admin'"
    )
    && !str_contains(
        $service,
        "'system_admin'"
    )
    && !str_contains(
        $otpService,
        "'super_admin'"
    )
    && !str_contains(
        $otpService,
        "'system_admin'"
    ),
    'Public registration must never assign admin roles.'
);

$expect(
    str_contains(
        $service,
        'DELETE FROM users'
    )
    && str_contains(
        $service,
        'DELETE FROM persons'
    )
    && !str_contains(
        $service,
        'DELETE FROM user_role_assignments'
    ),
    'Pending-registration MyISAM compensation contract is invalid.'
);

$expect(
    str_contains(
        $otpService,
        'ensureExactlyBaseRole'
    )
    && str_contains(
        $otpService,
        "status = 'active'"
    )
    && str_contains(
        $otpService,
        'mobile_verified_at'
    ),
    'Post-OTP activation contract is missing.'
);

$expect(
    str_contains(
        $view,
        'نام و نام خانوادگی'
    )
    && str_contains(
        $view,
        'شماره موبایل'
    )
    && str_contains(
        $view,
        'تکرار کلمه عبور'
    )
    && str_contains(
        $view,
        'حساب شما با نقش پایه'
    ),
    'Persian registration UI missing.'
);

$expect(
    str_contains(
        $loader,
        'public-registration.php'
    ),
    'Registration route is not loaded.'
);

echo
    "PUBLIC_REGISTRATION_FOUNDATION=PASS\n";
