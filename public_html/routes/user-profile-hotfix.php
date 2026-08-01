<?php

$router->get('/admin/profile/edit', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard(
        $response,
        '/admin/profile/edit'
    );

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\SelfProfileService();
    $page = $service->form(
        (int) $context['user_id']
    );

    if (($page['ok'] ?? false) !== true) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'پرونده کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    return $adminRender(
        $response,
        'self-profile-edit',
        [
            'title' => 'هویت و نشانی من',
            'context' => $context,
            'page' => $page,
            'errors' => [],
            'status' => trim((string) $request->input(
                'status',
                ''
            )),
        ]
    );
});

$router->post('/admin/profile/edit', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard(
        $response,
        '/admin/profile/edit'
    );

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\SelfProfileService();
    $result = $service->update(
        (int) $context['user_id'],
        $request->all()
    );

    if (($result['ok'] ?? false) === true) {
        return $response->redirect(
            '/admin/profile/edit?status=saved'
        );
    }

    $page = $service->form(
        (int) $context['user_id'],
        $result['form'] ?? $request->all()
    );

    return $adminRender(
        $response,
        'self-profile-edit',
        [
            'title' => 'هویت و نشانی من',
            'context' => $context,
            'page' => $page,
            'errors' => $result['errors'] ?? [
                'invalid' =>
                    'اطلاعات واردشده معتبر نیست.',
            ],
            'status' => '',
        ],
        422
    );
});

$router->get('/admin/account', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard(
        $response,
        '/admin/account'
    );

    if (!is_array($context)) {
        return $context;
    }

    $pending = $_SESSION[
        'self_identity_change_pending'
    ] ?? [];
    $devOtp = (string) (
        $_SESSION['self_identity_dev_otp'] ?? ''
    );
    unset($_SESSION['self_identity_dev_otp']);

    return $adminRender($response, 'account', [
        'title' => 'اطلاعات حساب',
        'context' => $context,
        'page' => (
            new \App\Services\IdentityVerificationService()
        )->page((int) $context['user_id']),
        'pending' => is_array($pending)
            ? $pending
            : [],
        'devOtp' => $devOtp,
        'status' => trim((string) $request->input(
            'status',
            ''
        )),
    ]);
});

$router->post(
    '/admin/account/identity/request',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/account'
        );

        if (!is_array($context)) {
            return $context;
        }

        $result = (
            new \App\Services\SelfIdentityChangeService()
        )->request(
            (int) $context['user_id'],
            (string) $request->input('field', ''),
            (string) $request->input('value', ''),
            (string) $request->input('password', '')
        );

        if (($result['ok'] ?? false) === true) {
            $_SESSION['self_identity_change_pending'] = [
                'request_id' => (int) (
                    $result['request_id'] ?? 0
                ),
                'field' => (string) (
                    $result['field'] ?? ''
                ),
                'masked_destination' => (string) (
                    $result['masked_destination'] ?? ''
                ),
                'expires_at' => (string) (
                    $result['expires_at'] ?? ''
                ),
            ];

            if (!empty($result['dev_token'])) {
                $_SESSION['self_identity_dev_otp'] =
                    (string) $result['dev_token'];
            }

            return $response->redirect(
                '/admin/account?status=change_otp_sent'
            );
        }

        return $response->redirect(
            '/admin/account?status='
            . rawurlencode((string) (
                $result['status']
                ?? 'delivery_failed'
            ))
        );
    }
);

$router->post(
    '/admin/account/identity/confirm',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/account'
        );

        if (!is_array($context)) {
            return $context;
        }

        $result = (
            new \App\Services\SelfIdentityChangeService()
        )->confirm(
            (int) $context['user_id'],
            (int) $request->input('request_id', 0),
            (string) $request->input('code', '')
        );

        if (($result['ok'] ?? false) === true) {
            unset(
                $_SESSION[
                    'self_identity_change_pending'
                ]
            );

            return $response->redirect(
                '/admin/account?status=identity_changed'
            );
        }

        return $response->redirect(
            '/admin/account?status='
            . rawurlencode((string) (
                $result['status']
                ?? 'invalid_or_expired_code'
            ))
        );
    }
);

$router->post(
    '/admin/account/verification/request',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/account'
        );

        if (!is_array($context)) {
            return $context;
        }

        $result = (
            new \App\Services\IdentityVerificationService()
        )->request(
            (int) $context['user_id'],
            (string) $request->input('field', '')
        );

        if (!empty($result['dev_token'])) {
            $_SESSION['self_identity_dev_otp'] =
                (string) $result['dev_token'];
        }

        return $response->redirect(
            '/admin/account?status='
            . rawurlencode(
                ($result['ok'] ?? false)
                    ? 'verification_otp_sent'
                    : (string) (
                        $result['status']
                        ?? 'delivery_failed'
                    )
            )
        );
    }
);

$router->post(
    '/admin/account/verification/confirm',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/account'
        );

        if (!is_array($context)) {
            return $context;
        }

        $result = (
            new \App\Services\IdentityVerificationService()
        )->confirm(
            (int) $context['user_id'],
            (string) $request->input('field', ''),
            (string) $request->input('code', '')
        );

        return $response->redirect(
            '/admin/account?status='
            . rawurlencode(
                ($result['ok'] ?? false)
                    ? 'identity_verified'
                    : (string) (
                        $result['status']
                        ?? 'invalid_or_expired_code'
                    )
            )
        );
    }
);
