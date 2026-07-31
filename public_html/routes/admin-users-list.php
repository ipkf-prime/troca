<?php

$router->get('/admin/users', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/users');

    if (!is_array($context)) {
        return $context;
    }

    $management = new \App\Services\AdminUserManagementService();
    $list = (new \App\Services\AdminUserListService())
        ->index([
            'q' => $request->input('q', ''),
            'page' => $request->input('page', 1),
            'sort' => $request->input('sort', ''),
            'dir' => $request->input('dir', ''),
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
