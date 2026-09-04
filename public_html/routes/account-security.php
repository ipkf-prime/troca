<?php

$accountSecurityRedirect = static function (
    $response,
    string $status
) {
    return $response->redirect(
        '/admin/security?status=' . rawurlencode($status)
    );
};

$router->get('/admin/security', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AccountSecurityService();
    $page = $service->page(
        (int) $context['user_id'],
        $context['user'] ?? []
    );
    $status = trim((string) $request->input('status', ''));

    return $adminRender($response, 'security', [
        'title' => 'امنیت و ورود',
        'context' => $context,
        'page' => $page,
        'message' => $service->statusMessage($status),
    ]);
});

$router->post('/admin/security/mfa/totp/start', function (
    $request,
    $response
) use ($adminGuard, $accountSecurityRedirect) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    $result = (new \App\Services\AccountSecurityService())
        ->beginTotp(
            (int) $context['user_id'],
            $context['user'] ?? [],
            (string) $request->input('password', ''),
            (string) $request->input('current_totp', '')
        );

    return $accountSecurityRedirect(
        $response,
        ($result['ok'] ?? false)
            ? 'mfa_setup_started'
            : (string) ($result['error'] ?? 'mfa_unavailable')
    );
});

$router->post('/admin/security/mfa/totp/confirm', function (
    $request,
    $response
) use ($adminGuard, $accountSecurityRedirect) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    $result = (new \App\Services\AccountSecurityService())
        ->confirmTotp(
            (int) $context['user_id'],
            (string) $request->input('code', '')
        );

    return $accountSecurityRedirect(
        $response,
        ($result['ok'] ?? false)
            ? 'mfa_enabled'
            : (string) ($result['error']
                ?? 'invalid_setup_code')
    );
});

$router->post('/admin/security/mfa/totp/cancel', function (
    $request,
    $response
) use ($adminGuard, $accountSecurityRedirect) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    (new \App\Services\AccountSecurityService())
        ->cancelTotp();

    return $accountSecurityRedirect(
        $response,
        'mfa_cancelled'
    );
});

$router->post('/admin/security/mfa/totp/disable', function (
    $request,
    $response
) use ($adminGuard, $accountSecurityRedirect) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    $result = (new \App\Services\AccountSecurityService())
        ->disableTotp(
            (int) $context['user_id'],
            (string) $request->input('password', ''),
            (string) $request->input('totp_code', '')
        );

    return $accountSecurityRedirect(
        $response,
        ($result['ok'] ?? false)
            ? 'mfa_disabled'
            : (string) ($result['error'] ?? 'invalid_totp')
    );
});

$router->post('/admin/security/recovery/regenerate', function (
    $request,
    $response
) use ($adminGuard, $accountSecurityRedirect) {
    $context = $adminGuard($response, '/admin/security');

    if (!is_array($context)) {
        return $context;
    }

    $result = (new \App\Services\AccountSecurityService())
        ->regenerateRecoveryCodes(
            (int) $context['user_id'],
            (string) $request->input('password', ''),
            (string) $request->input('totp_code', '')
        );

    return $accountSecurityRedirect(
        $response,
        ($result['ok'] ?? false)
            ? 'recovery_regenerated'
            : (string) ($result['error'] ?? 'invalid_totp')
    );
});

$router->get('/admin/password', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/password');

    if (!is_array($context)) {
        return $context;
    }

    return $adminRender($response, 'password', [
        'title' => 'تغییر رمز عبور',
        'context' => $context,
        'status' => trim(
            (string) $request->input('status', '')
        ),
        'errors' => [],
    ]);
});

$router->post('/admin/password', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/password');

    if (!is_array($context)) {
        return $context;
    }

    $result = (new \App\Services\AccountSecurityService())
        ->changePassword(
            (int) $context['user_id'],
            $context['user'] ?? [],
            (string) $request->input(
                'current_password',
                ''
            ),
            (string) $request->input('password', ''),
            (string) $request->input(
                'password_confirmation',
                ''
            )
        );

    if (($result['ok'] ?? false) === true) {
        return $response->redirect(
            '/admin/password?status=updated'
        );
    }

    return $adminRender($response, 'password', [
        'title' => 'تغییر رمز عبور',
        'context' => $context,
        'status' => '',
        'errors' => $result['errors'] ?? [
            'invalid' => 'تغییر رمز عبور انجام نشد.',
        ],
    ], 422);
});


/*
 * IDENTITY_EMAIL_VERIFICATION_A3_3A
 *
 * Email verification is independent from mobile registration
 * activation. Both operations require an authenticated account
 * and CSRF protection.
 */
$router->post(
    '/admin/security/identity/email/request',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $accountSecurityRedirect
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/security'
            );

        if (!is_array($context)) {
            return $context;
        }

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
            return $accountSecurityRedirect(
                $response,
                'email_delivery_failed'
            );
        }

        $result =
            (
                new \App\Services\IdentityVerificationService()
            )->request(
                (int) $context['user_id'],
                'email'
            );

        if (
            ($result['ok'] ?? false)
            === true
        ) {
            return $accountSecurityRedirect(
                $response,
                'email_code_sent'
            );
        }

        $status =
            (string) (
                $result['status']
                ?? ''
            );

        return $accountSecurityRedirect(
            $response,
            match ($status) {
                'already_verified' =>
                    'email_already_verified',

                'rate_limited' =>
                    'email_rate_limited',

                'email_unavailable' =>
                    'email_unavailable',

                default =>
                    'email_delivery_failed',
            }
        );
    }
);


$router->post(
    '/admin/security/identity/email/confirm',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $accountSecurityRedirect
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/security'
            );

        if (!is_array($context)) {
            return $context;
        }

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
            return $accountSecurityRedirect(
                $response,
                'email_invalid_code'
            );
        }

        $result =
            (
                new \App\Services\IdentityVerificationService()
            )->confirm(
                (int) $context['user_id'],
                'email',
                (string) $request->input(
                    'code',
                    ''
                )
            );

        if (
            ($result['ok'] ?? false)
            === true
        ) {
            return $accountSecurityRedirect(
                $response,
                'email_verified'
            );
        }

        $status =
            (string) (
                $result['status']
                ?? ''
            );

        return $accountSecurityRedirect(
            $response,
            match ($status) {
                'email_unavailable' =>
                    'email_unavailable',

                'already_verified' =>
                    'email_already_verified',

                default =>
                    'email_invalid_code',
            }
        );
    }
);
