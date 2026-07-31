<?php

$workSettingsContext = static function (
    $response,
    string $permission
) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/work');
    if (!is_array($context)) {
        return $context;
    }

    if (!(new \App\Services\AdminNavigationRbacService())->can(
        (int) $context['user_id'],
        $permission
    )) {
        return $adminRender($response, 'forbidden', [
            'title' => 'دسترسی غیرمجاز',
            'context' => $context,
        ], 403);
    }

    return $context;
};

$workSettingsRedirect = static function (
    $response,
    string $groupCode,
    array $result,
    \App\Services\Work\WorkSettingsService $service
) {
    $base = '/admin/work/settings?group=' . rawurlencode($groupCode);

    if (($result['ok'] ?? false) === true) {
        return $response->redirect($base . '&saved=1');
    }

    return $response->redirect(
        $base . '&error=' . rawurlencode($service->errorText($result['errors'] ?? []))
    );
};

$router->get('/admin/work/settings', function ($request, $response) use ($adminRender, $workSettingsContext) {
    $context = $workSettingsContext($response, 'work.settings.view');
    if (!is_array($context)) {
        return $context;
    }

    try {
        $page = (new \App\Services\Work\WorkSettingsService())->view(
            trim((string) $request->input('group', 'item_status'))
        );
    } catch (\Throwable) {
        return $adminRender($response, 'placeholder', [
            'title' => 'تنظیمات مدیریت کار',
            'context' => $context,
            'message' => 'ابتدا Migration و Seed تنظیمات Work را اجرا کنید.',
        ], 503);
    }

    return $adminRender($response, 'work-settings', [
        'title' => 'تنظیمات مدیریت کار',
        'context' => $context,
        'page' => $page,
    ]);
});

$router->post('/admin/work/settings/statuses', function ($request, $response) use ($workSettingsContext, $workSettingsRedirect) {
    $context = $workSettingsContext($response, 'work.settings.manage');
    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\Work\WorkSettingsService();
    try {
        $result = $service->saveWorkStatus(
            null,
            $request->all(),
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $result = ['ok' => false, 'errors' => ['save' => 'ثبت وضعیت انجام نشد.']];
    }

    return $workSettingsRedirect($response, 'item_status', $result, $service);
});

$router->post('/admin/work/settings/statuses/{status_id}', function ($request, $response) use ($workSettingsContext, $workSettingsRedirect) {
    $context = $workSettingsContext($response, 'work.settings.manage');
    if (!is_array($context)) {
        return $context;
    }

    $service = new \App\Services\Work\WorkSettingsService();
    try {
        $result = $service->saveWorkStatus(
            (int) $request->route('status_id'),
            $request->all(),
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $result = ['ok' => false, 'errors' => ['save' => 'ذخیره وضعیت انجام نشد.']];
    }

    return $workSettingsRedirect($response, 'item_status', $result, $service);
});

$router->post('/admin/work/settings/reference/{group_code}', function ($request, $response) use ($workSettingsContext, $workSettingsRedirect) {
    $context = $workSettingsContext($response, 'work.settings.manage');
    if (!is_array($context)) {
        return $context;
    }

    $groupCode = trim((string) $request->route('group_code'));
    $service = new \App\Services\Work\WorkSettingsService();

    try {
        $result = $service->saveReferenceItem(
            $groupCode,
            null,
            $request->all(),
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $result = ['ok' => false, 'errors' => ['save' => 'ثبت گزینه انجام نشد.']];
    }

    return $workSettingsRedirect($response, $groupCode, $result, $service);
});

$router->post('/admin/work/settings/reference/{group_code}/{item_id}', function ($request, $response) use ($workSettingsContext, $workSettingsRedirect) {
    $context = $workSettingsContext($response, 'work.settings.manage');
    if (!is_array($context)) {
        return $context;
    }

    $groupCode = trim((string) $request->route('group_code'));
    $service = new \App\Services\Work\WorkSettingsService();

    try {
        $result = $service->saveReferenceItem(
            $groupCode,
            (int) $request->route('item_id'),
            $request->all(),
            (int) $context['user_id'],
            $context
        );
    } catch (\Throwable) {
        $result = ['ok' => false, 'errors' => ['save' => 'ذخیره گزینه انجام نشد.']];
    }

    return $workSettingsRedirect($response, $groupCode, $result, $service);
});
