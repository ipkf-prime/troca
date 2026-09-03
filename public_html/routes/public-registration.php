<?php

declare(strict_types=1);

/** @var \IPKF\Routing\Router $router */

$renderPublicRegistration =
    static function (
        $response,
        array $data = [],
        int $httpStatus = 200
    ) {
        $view =
            BASE_PATH
            . '/resources/views/site/'
            . 'register.php';

        if (!is_readable($view)) {
            return $response
                ->status(503)
                ->send(
                    'صفحه ثبت‌نام در دسترس نیست.'
                );
        }

        extract(
            $data,
            EXTR_SKIP
        );

        ob_start();
        require $view;
        $content =
            ob_get_clean()
            ?: '';

        return $response
            ->status($httpStatus)
            ->header(
                'Content-Type',
                'text/html; charset=UTF-8'
            )
            ->header(
                'Cache-Control',
                'no-store, private'
            )
            ->send($content);
    };


$router->get(
    '/register',
    function (
        $request,
        $response
    ) use (
        $renderPublicRegistration
    ) {
        if (
            (
                new \App\Services\AuthService()
            )->authenticated()
        ) {
            return $response->redirect(
                '/admin/dashboard'
            );
        }

        $registrationSuccess =
            (string) \IPKF\Support\Session::get(
                'public_registration_success'
            ) === '1';

        if ($registrationSuccess) {
            \IPKF\Support\Session::forget(
                'public_registration_success'
            );
        }

        return $renderPublicRegistration(
            $response,
            [
                'title' => 'ثبت‌نام',
                'status' =>
                    $registrationSuccess
                        ? 'success'
                        : '',
                'errors' => [],
                'old' => [],
            ]
        );
    }
);


$router->post(
    '/register',
    function (
        $request,
        $response
    ) use (
        $renderPublicRegistration
    ) {
        if (
            (
                new \App\Services\AuthService()
            )->authenticated()
        ) {
            return $response->redirect(
                '/admin/dashboard'
            );
        }

        /*
         * Honeypot. A bot receives a normal
         * success-like response without DB write.
         */
        if (
            trim(
                (string) $request->input(
                    'website',
                    ''
                )
            ) !== ''
        ) {
            return $response->redirect(
                '/register'
            );
        }

        $old = [
            'full_name' =>
                trim(
                    (string) $request->input(
                        'full_name',
                        ''
                    )
                ),
            'mobile' =>
                trim(
                    (string) $request->input(
                        'mobile',
                        ''
                    )
                ),
            'email' =>
                trim(
                    (string) $request->input(
                        'email',
                        ''
                    )
                ),
        ];

        if (
            !(
                new \IPKF\Security\Csrf()
            )->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $renderPublicRegistration(
                $response,
                [
                    'title' => 'ثبت‌نام',
                    'status' => '',
                    'errors' => [
                        'general' =>
                            'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.',
                    ],
                    'old' => $old,
                ],
                422
            );
        }

        $result =
            (
                new \App\Services\PublicRegistrationService()
            )->register([
                'full_name' =>
                    $old['full_name'],
                'mobile' =>
                    $old['mobile'],
                'email' =>
                    $old['email'],
                'password' =>
                    (string) $request->input(
                        'password',
                        ''
                    ),
                'password_confirmation' =>
                    (string) $request->input(
                        'password_confirmation',
                        ''
                    ),
            ]);

        if (
            ($result['ok'] ?? false)
            === true
        ) {
            \IPKF\Support\Session::put(
                'public_registration_success',
                '1'
            );

            /*
             * Persist the one-time success state
             * before emitting the PRG redirect.
             */
            if (
                session_status()
                === PHP_SESSION_ACTIVE
            ) {
                session_write_close();
            }

            return $response->redirect(
                '/register'
            );
        }

        return $renderPublicRegistration(
            $response,
            [
                'title' => 'ثبت‌نام',
                'status' => '',
                'errors' =>
                    is_array(
                        $result['errors']
                        ?? null
                    )
                        ? $result['errors']
                        : [
                            'general' =>
                                'ثبت‌نام تکمیل نشد.',
                        ],
                'old' => $old,
            ],
            422
        );
    }
);
