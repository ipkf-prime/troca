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
    $adminUserManagementForbidden
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
        'title' => 'ایجاد کاربر',
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
    $adminUserManagementForbidden
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
            '/admin/users/' . (int) $result['user_id']
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
        'title' => 'ایجاد کاربر',
        'context' => $context,
        'page' => $page,
        'errors' => $result['errors'] ?? [
            'invalid' => 'اطلاعات واردشده معتبر نیست.',
        ],
        'status' => '',
    ], 422);
});

$router->get('/admin/users/{id}/edit', function (
    $request,
    $response
) use (
    $adminRender,
    $adminGuard,
    $adminUserManagementForbidden
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
    $adminUserManagementForbidden
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
            '/admin/users/'
            . (int) $userId
            . '/edit?status=saved'
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
