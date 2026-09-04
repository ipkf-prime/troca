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

$renderPublicRegistrationVerify =
    static function (
        $response,
        array $data = [],
        int $httpStatus = 200
    ) {
        $view =
            BASE_PATH
            . '/resources/views/site/'
            . 'register-verify.php';

        if (!is_readable($view)) {
            return $response
                ->status(503)
                ->send(
                    'صفحه تأیید شماره همراه در دسترس نیست.'
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

$registrationClearAttempt =
    static function (): void {
        foreach (
            [
                'public_registration_attempt_id',
                'public_registration_attempt_token',
                'public_registration_verify_status',
                'public_registration_dev_token',
            ] as $key
        ) {
            \IPKF\Support\Session::forget(
                $key
            );
        }
    };

$registrationCloseSession =
    static function (): void {
        if (
            session_status()
            === PHP_SESSION_ACTIVE
        ) {
            session_write_close();
        }
    };

$registrationAttemptSession =
    static function (): array {
        return [
            'id' =>
                (int) (
                    \IPKF\Support\Session::get(
                        'public_registration_attempt_id'
                    )
                    ?? 0
                ),

            'token' =>
                trim(
                    (string) (
                        \IPKF\Support\Session::get(
                            'public_registration_attempt_token'
                        )
                        ?? ''
                    )
                ),
        ];
    };

$registrationCsrfValid =
    static function (
        $request
    ): bool {
        return (
            new \IPKF\Security\Csrf()
        )->check(
            (string) $request->input(
                '_token',
                ''
            )
        );
    };

$router->get(
    '/register',
    function (
        $request,
        $response
    ) use (
        $renderPublicRegistration,
        $registrationClearAttempt,
        $registrationAttemptSession
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

        if (
            (string) $request->input(
                'restart',
                ''
            ) === '1'
        ) {
            $registrationClearAttempt();
        } else {
            $pending =
                $registrationAttemptSession();

            if (
                $pending['id'] > 0
                && $pending['token'] !== ''
            ) {
                return $response->redirect(
                    '/register/verify'
                );
            }
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
                'title' =>
                    'ثبت‌نام',

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
        $renderPublicRegistration,
        $registrationClearAttempt,
        $registrationCloseSession,
        $registrationCsrfValid
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
            !$registrationCsrfValid(
                $request
            )
        ) {
            return $renderPublicRegistration(
                $response,
                [
                    'title' =>
                        'ثبت‌نام',
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

        $registrationClearAttempt();

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

                'created_ip' =>
                    $_SERVER[
                        'REMOTE_ADDR'
                    ]
                    ?? '',

                'created_user_agent' =>
                    $_SERVER[
                        'HTTP_USER_AGENT'
                    ]
                    ?? '',
            ]);

        if (
            ($result['ok'] ?? false)
            === true
        ) {
            \IPKF\Support\Session::put(
                'public_registration_attempt_id',
                (int) $result[
                    'attempt_id'
                ]
            );

            \IPKF\Support\Session::put(
                'public_registration_attempt_token',
                (string) $result[
                    'attempt_token'
                ]
            );

            \IPKF\Support\Session::put(
                'public_registration_verify_status',
                (string) (
                    $result[
                        'delivery_status'
                    ]
                    ?? ''
                )
            );

            if (
                !empty(
                    $result[
                        'dev_token'
                    ]
                )
            ) {
                \IPKF\Support\Session::put(
                    'public_registration_dev_token',
                    (string) $result[
                        'dev_token'
                    ]
                );
            }

            $registrationCloseSession();

            return $response->redirect(
                '/register/verify'
            );
        }

        return $renderPublicRegistration(
            $response,
            [
                'title' =>
                    'ثبت‌نام',

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

$router->get(
    '/register/verify',
    function (
        $request,
        $response
    ) use (
        $renderPublicRegistrationVerify,
        $registrationClearAttempt,
        $registrationAttemptSession
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

        $session =
            $registrationAttemptSession();

        if (
            $session['id'] < 1
            || $session['token'] === ''
        ) {
            return $response->redirect(
                '/register'
            );
        }

        $state =
            (
                new \App\Services\PublicRegistrationOtpService()
            )->state(
                $session['id'],
                $session['token']
            );

        if (
            ($state['status'] ?? '')
            === 'attempt_invalid'
            || ($state['status'] ?? '')
            === 'already_completed'
        ) {
            $registrationClearAttempt();

            return $response->redirect(
                '/register'
            );
        }

        $status =
            trim(
                (string) (
                    \IPKF\Support\Session::get(
                        'public_registration_verify_status'
                    )
                    ?? ''
                )
            );

        \IPKF\Support\Session::forget(
            'public_registration_verify_status'
        );

        $devToken =
            trim(
                (string) (
                    \IPKF\Support\Session::get(
                        'public_registration_dev_token'
                    )
                    ?? ''
                )
            );

        if (
            ($state['status'] ?? '')
            === 'attempt_expired'
        ) {
            $status =
                'attempt_expired';
        }

        return $renderPublicRegistrationVerify(
            $response,
            [
                'title' =>
                    'تأیید شماره همراه',
                'state' =>
                    $state,
                'status' =>
                    $status,
                'devToken' =>
                    $devToken,
            ]
        );
    }
);

$router->post(
    '/register/verify',
    function (
        $request,
        $response
    ) use (
        $registrationClearAttempt,
        $registrationCloseSession,
        $registrationAttemptSession,
        $registrationCsrfValid
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

        $session =
            $registrationAttemptSession();

        if (
            $session['id'] < 1
            || $session['token'] === ''
        ) {
            return $response->redirect(
                '/register'
            );
        }

        if (
            !$registrationCsrfValid(
                $request
            )
        ) {
            \IPKF\Support\Session::put(
                'public_registration_verify_status',
                'invalid_form'
            );

            $registrationCloseSession();

            return $response->redirect(
                '/register/verify'
            );
        }

        $result =
            (
                new \App\Services\PublicRegistrationOtpService()
            )->confirm(
                $session['id'],
                $session['token'],
                (string) $request->input(
                    'code',
                    ''
                )
            );

        if (
            ($result['ok'] ?? false)
            === true
        ) {
            $registrationClearAttempt();

            \IPKF\Support\Session::put(
                'public_registration_success',
                '1'
            );

            $registrationCloseSession();

            return $response->redirect(
                '/register'
            );
        }

        \IPKF\Support\Session::put(
            'public_registration_verify_status',
            (string) (
                $result['status']
                ?? 'invalid_or_expired_code'
            )
        );

        $registrationCloseSession();

        return $response->redirect(
            '/register/verify'
        );
    }
);


/*
 * PUBLIC_REGISTRATION_BALE_MOBILE_ATTESTATION_A3_2B1_V2
 *
 * Enrollment generation and final attestation are POST-only.
 * The GET verification page remains read-only.
 */
$router->post(
    '/register/verify/bale',
    function (
        $request,
        $response
    ) use (
        $registrationCloseSession,
        $registrationAttemptSession,
        $registrationCsrfValid
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

        $session =
            $registrationAttemptSession();

        if (
            $session['id'] < 1
            || $session['token'] === ''
        ) {
            return $response->redirect(
                '/register'
            );
        }

        if (
            !$registrationCsrfValid(
                $request
            )
        ) {
            \IPKF\Support\Session::put(
                'public_registration_verify_status',
                'invalid_form'
            );

            $registrationCloseSession();

            return $response->redirect(
                '/register/verify'
            );
        }

        try {
            $result =
                (
                    new \App\Services\PublicRegistrationOtpService()
                )->baleEnrollment(
                    $session['id'],
                    $session['token']
                );

        } catch (\Throwable) {
            $result = [
                'ok' => false,
                'status' =>
                    'bale_unavailable',
            ];
        }

        $link =
            trim(
                (string) (
                    $result['link']
                    ?? ''
                )
            );

        if (
            ($result['ok'] ?? false)
                === true
            && $link !== ''
        ) {
            /*
             * Release only the PHP session lock.
             * Registration-attempt session values remain available
             * when the user returns from Bale.
             */
            $registrationCloseSession();

            return $response->redirect(
                $link
            );
        }

        \IPKF\Support\Session::put(
            'public_registration_verify_status',
            (string) (
                $result['status']
                ?? 'bale_unavailable'
            )
        );

        $registrationCloseSession();

        return $response->redirect(
            '/register/verify'
        );
    }
);

$router->post(
    '/register/verify/bale/confirm',
    function (
        $request,
        $response
    ) use (
        $registrationClearAttempt,
        $registrationCloseSession,
        $registrationAttemptSession,
        $registrationCsrfValid
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

        $session =
            $registrationAttemptSession();

        if (
            $session['id'] < 1
            || $session['token'] === ''
        ) {
            return $response->redirect(
                '/register'
            );
        }

        if (
            !$registrationCsrfValid(
                $request
            )
        ) {
            \IPKF\Support\Session::put(
                'public_registration_verify_status',
                'invalid_form'
            );

            $registrationCloseSession();

            return $response->redirect(
                '/register/verify'
            );
        }

        try {
            $result =
                (
                    new \App\Services\PublicRegistrationOtpService()
                )->confirmBaleAttestation(
                    $session['id'],
                    $session['token']
                );

        } catch (\Throwable) {
            $result = [
                'ok' => false,
                'status' =>
                    'bale_unavailable',
            ];
        }

        if (
            ($result['ok'] ?? false)
            === true
        ) {
            $registrationClearAttempt();

            \IPKF\Support\Session::put(
                'public_registration_success',
                '1'
            );

            $registrationCloseSession();

            return $response->redirect(
                '/register'
            );
        }

        \IPKF\Support\Session::put(
            'public_registration_verify_status',
            (string) (
                $result['status']
                ?? 'bale_pending'
            )
        );

        $registrationCloseSession();

        return $response->redirect(
            '/register/verify'
        );
    }
);

$router->post(
    '/register/verify/resend',
    function (
        $request,
        $response
    ) use (
        $registrationCloseSession,
        $registrationAttemptSession,
        $registrationCsrfValid
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

        $session =
            $registrationAttemptSession();

        if (
            $session['id'] < 1
            || $session['token'] === ''
        ) {
            return $response->redirect(
                '/register'
            );
        }

        if (
            !$registrationCsrfValid(
                $request
            )
        ) {
            \IPKF\Support\Session::put(
                'public_registration_verify_status',
                'invalid_form'
            );

            $registrationCloseSession();

            return $response->redirect(
                '/register/verify'
            );
        }

        $result =
            (
                new \App\Services\PublicRegistrationOtpService()
            )->resend(
                $session['id'],
                $session['token']
            );

        \IPKF\Support\Session::put(
            'public_registration_verify_status',
            (string) (
                $result['status']
                ?? 'delivery_failed'
            )
        );

        if (
            !empty(
                $result[
                    'dev_token'
                ]
            )
        ) {
            \IPKF\Support\Session::put(
                'public_registration_dev_token',
                (string) $result[
                    'dev_token'
                ]
            );
        }

        $registrationCloseSession();

        return $response->redirect(
            '/register/verify'
        );
    }
);
