<?php

$adminUserManagementForbidden = static function (
    $response,
    callable $adminRender,
    array $context
) {
    return $adminRender($response, 'forbidden', [
        'title' => 'دسترسی غیرمجاز',
        'context' => $context,
    ], 403);
};

$adminUserVerificationRedirect = static function (
    int $userId,
    array $verification,
    string $status
): string {
    $parts = [];

    foreach ($verification as $field => $result) {
        $state = (string) (
            $result['status'] ?? 'delivery_failed'
        );
        $parts[] = $field . ':' . $state;

        if (
            isset($result['dev_token'])
            && $result['dev_token'] !== null
        ) {
            $_SESSION['admin_identity_dev_otp'][
                $userId
            ][$field] = (string) $result['dev_token'];
        }
    }

    $query = ['status' => $status];

    if ($parts !== []) {
        $query['verification'] = implode(',', $parts);
    }

    return '/admin/users/'
        . $userId
        . '/edit?'
        . http_build_query($query);
};

$router->get('/admin/users', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $management = new \App\Services\AdminUserManagementService();
    $list = (new \App\Services\AdminUserService())->index([
        'q' => $request->input('q', ''),
        'page' => $request->input('page', 1),
    ]);

    return $adminRender($response, 'users', [
        'title' => 'کاربران',
        'context' => $context,
        'list' => $list,
        'canCreate' => $management->canCreate(
            (int) $context['user_id']
        ),
        'canUpdate' => $management->canUpdate(
            (int) $context['user_id']
        ),
        'status' => trim(
            (string) $request->input('status', '')
        ),
    ]);
});

$router->get('/admin/users/create', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canCreate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $page = $service->form((int) $context['user_id']);

    return $adminRender($response, 'admin-user-form', [
        'title' => 'افزودن دستی کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => [],
        'status' => '',
    ]);
});

$router->post('/admin/users', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canCreate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $result = $service->create(
        (int) $context['user_id'],
        $request->all()
    );

    if (($result['ok'] ?? false) === true) {
        return $response->redirect(
            $adminUserVerificationRedirect(
                (int) $result['user_id'],
                $result['verification'] ?? [],
                'created'
            )
        );
    }

    if (($result['forbidden'] ?? false) === true) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $page = $service->form(
        (int) $context['user_id'],
        null,
        $result['form'] ?? $request->all()
    );

    return $adminRender($response, 'admin-user-form', [
        'title' => 'افزودن دستی کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => $result['errors'] ?? [
            'invalid' => 'اطلاعات واردشده معتبر نیست.',
        ],
        'status' => '',
    ], 422);
});

$router->get('/admin/users/invite', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden
) {
    $context =
        $adminGuard(
            $response,
            '/admin/users'
        );

    if (!is_array($context)) {
        return $context;
    }

    $service =
        new \App\Services\UserInvitationService();

    if (!$service->canInvite(
        (int) $context['user_id']
    )) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $createdInvitation =
        \IPKF\Support\Session::get(
            'admin_user_invitation_created'
        );

    \IPKF\Support\Session::forget(
        'admin_user_invitation_created'
    );

    return $adminRender(
        $response,
        'user-invite',
        [
            'title' =>
                'دعوت کاربر',
            'context' =>
                $context,
            'errors' => [],
            'old' => [
                'full_name' => '',
                'mobile' => '',
                'email' => '',
                'expires_days' => 7,
            ],
            'createdInvitation' =>
                is_array(
                    $createdInvitation
                )
                    ? $createdInvitation
                    : null,
        ]
    );
});


$router->post('/admin/users/invite', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden
) {
    $context =
        $adminGuard(
            $response,
            '/admin/users'
        );

    if (!is_array($context)) {
        return $context;
    }

    $service =
        new \App\Services\UserInvitationService();

    if (!$service->canInvite(
        (int) $context['user_id']
    )) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    if (
        !(new \IPKF\Security\Csrf())
            ->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
    ) {
        return $adminRender(
            $response,
            'user-invite',
            [
                'title' =>
                    'دعوت کاربر',
                'context' =>
                    $context,
                'errors' => [
                    'general' =>
                        'اعتبار فرم منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.',
                ],
                'old' =>
                    $request->all(),
                'createdInvitation' =>
                    null,
            ],
            422
        );
    }

    $input =
        $request->all();

    $input['created_ip'] =
        $_SERVER['REMOTE_ADDR']
        ?? '';

    $input['created_user_agent'] =
        $_SERVER['HTTP_USER_AGENT']
        ?? '';

    $result =
        $service->create(
            (int) $context['user_id'],
            $input
        );

    if (($result['ok'] ?? false)
        === true
    ) {
        \IPKF\Support\Session::put(
            'admin_user_invitation_created',
            $result['invitation']
        );

        return $response->redirect(
            '/admin/users/invite'
            . '?status=created'
        );
    }

    if (($result['forbidden'] ?? false)
        === true
    ) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    return $adminRender(
        $response,
        'user-invite',
        [
            'title' =>
                'دعوت کاربر',
            'context' =>
                $context,
            'errors' =>
                $result['errors']
                ?? [
                    'general' =>
                        'ایجاد دعوت انجام نشد.',
                ],
            'old' =>
                $result['form']
                ?? $request->all(),
            'createdInvitation' =>
                null,
        ],
        422
    );
});


$router->get('/admin/users/{id}/edit', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canUpdate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $userId = filter_var(
        $request->route('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    $page = $service->form(
        (int) $context['user_id'],
        (int) $userId
    );

    if (($page['ok'] ?? false) !== true) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    return $adminRender($response, 'admin-user-form', [
        'title' => 'ویرایش کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => [],
        'status' => trim(
            (string) $request->input('status', '')
        ),
    ]);
});

$router->post('/admin/users/{id}', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden,
    $adminUserVerificationRedirect
) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\AdminUserManagementService();

    if (!$service->canUpdate((int) $context['user_id'])) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $userId = filter_var(
        $request->route('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    $result = $service->update(
        (int) $context['user_id'],
        (int) $userId,
        $request->all()
    );

    if (($result['ok'] ?? false) === true) {
        return $response->redirect(
            $adminUserVerificationRedirect(
                (int) $userId,
                $result['verification'] ?? [],
                'saved'
            )
        );
    }

    if (($result['not_found'] ?? false) === true) {
        return $adminRender($response, 'user-not-found', [
            'title' => 'کاربر پیدا نشد',
            'context' => $context,
        ], 404);
    }

    if (($result['forbidden'] ?? false) === true) {
        return $adminUserManagementForbidden(
            $response,
            $adminRender,
            $context
        );
    }

    $page = $service->form(
        (int) $context['user_id'],
        (int) $userId,
        $result['form'] ?? $request->all()
    );

    return $adminRender($response, 'admin-user-form', [
        'title' => 'ویرایش کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => $result['errors'] ?? [
            'invalid' => 'اطلاعات واردشده معتبر نیست.',
        ],
        'status' => '',
    ], 422);
});

$router->post('/admin/users/{id}/roles', function (
    $request,
    $response
) use (
    $adminGuard
) {
    $context =
        $adminGuard(
            $response,
            '/admin/users'
        );

    if (!is_array($context)) {
        return $context;
    }

    $userId =
        max(
            0,
            (int) $request->route(
                'id'
            )
        );

    return $response->redirect(
        '/admin/access-control'
        . '?tab=users'
        . '&user_id='
        . $userId
        . '&status=access_management_moved'
    );
});


$adminManagedUserDetailRoute = function (
    string $pattern,
    string $tab
) use ($router, $adminRender, $adminGuard) {
    $router->get($pattern, function (
        $request,
        $response
    ) use ($tab, $adminRender, $adminGuard) {
        $context = $adminGuard($response, '/admin/users');
        if (!is_array($context)) {
            return $context;
        }

        $userId = filter_var(
            $request->route('id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($userId === false) {
            return $adminRender($response, 'user-not-found', [
                'title' => 'کاربر پیدا نشد',
                'context' => $context,
            ], 404);
        }

        $detail = (new \App\Services\AdminUserDetailCompletionService())
            ->workspace(
                (int) $userId,
                $tab,
                (int) $context['user_id']
            );

        if ($detail === null) {
            return $adminRender($response, 'user-not-found', [
                'title' => 'کاربر پیدا نشد',
                'context' => $context,
            ], 404);
        }

        return $adminRender($response, 'user-detail', [
            'title' => 'جزئیات کاربر',
            'context' => $context,
            'detail' => $detail,
        ]);
    });
};

$adminManagedUserDetailRoute('/admin/users/{id}', 'overview');
$adminManagedUserDetailRoute('/admin/users/{id}/identity', 'identity');
$adminManagedUserDetailRoute('/admin/users/{id}/contacts', 'contacts');
$adminManagedUserDetailRoute('/admin/users/{id}/account', 'account');
$adminManagedUserDetailRoute('/admin/users/{id}/access', 'access');
$adminManagedUserDetailRoute('/admin/users/{id}/appointments', 'appointments');
