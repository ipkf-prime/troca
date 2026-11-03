<?php
declare(strict_types=1);


$router->get('/admin/communications/safir', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/communications/safir');
    if (!is_array($context)) {
        return $context;
    }
    $actor = (int) $context['user_id'];
    $authorization = new \App\Services\AuthorizationService();
    $maySend = $authorization->hasPermission($actor, 'notifications.send.manage');
    $mayManageSafir = $authorization->hasPermission($actor, 'notifications.safir.manage');
    if (!$maySend && !$mayManageSafir) {
        return $response->status(403)->send('Forbidden');
    }
    $editing = trim((string) $request->input('edit', ''));
    if ($editing !== '' && !preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $editing)) {
        return $response->status(400)->send('Invalid template reference');
    }
    $testing = trim((string) $request->input('test', ''));
    if ($testing !== '' && !preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $testing)) {
        return $response->status(400)->send('Invalid test template reference');
    }
    $mode = (string) $request->input('mode', '');
    $safirCampaign = (new \App\Services\BaleSafirCampaignService())->page(
        $actor, $testing !== '' ? $testing : $editing
    );
    if (!$maySend) {
        // Scoped Safir managers may view and edit, but never initiate
        // provider transmission even if send flags are later enabled.
        $safirCampaign['send_enabled'] = false;
        $safirCampaign['manual_test_enabled'] = false;
    }
    $page = [
        'safir_campaign' => $safirCampaign,
        'safir_edit' => $editing,
        'safir_mode' => $testing !== '' ? 'test' : ($mode === 'create' ? 'create' : ($editing !== '' ? 'edit' : 'list')),
        'safir_test_code' => $testing,
    ];
    return $adminRender($response, 'safir-management', [
        'title' => 'مدیریت قالب‌های سفیر بله',
        'context' => $context,
        'page' => $page,
        'status' => (string) $request->input('status', ''),
    ]);
});

/*
 * Bale Safir: separate phone-number/template API.
 * Never route these requests through the existing chat_id bot adapter.
 */
$router->post('/admin/communications/settings/safir', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/communications/settings/safir');
    if (!is_array($context)) {
        return $context;
    }
    $actor = (int) $context['user_id'];
    $service = new \App\Services\BaleSafirCampaignService();
    $redirect = '/admin/communications/safir';
    $authorization = new \App\Services\AuthorizationService();
    $maySend = $authorization->hasPermission($actor, 'notifications.send.manage');
    if (!$maySend && !$authorization->hasPermission($actor, 'notifications.safir.manage')) {
        return $response->status(403)->send('Forbidden');
    }
    if (!(new \IPKF\Security\Csrf())->check((string) $request->input('_token', ''))) {
        return $response->redirect($redirect . '?status=safir_invalid_csrf');
    }
    $action = (string) $request->input('action', '');
    // Template administration never implies permission to send real messages.
    if (in_array($action, ['test', 'manual_test', 'dispatch'], true) && !$maySend) {
        return $response->status(403)->send('Forbidden');
    }
    if ($action === 'manual_test') {
        $testCode = trim((string) $request->input('template_code', ''));
        if (preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $testCode)) {
            $redirect = '/admin/communications/safir?test=' . rawurlencode($testCode);
        }
    }
    try {
        switch ($action) {
            case 'save_template':
                $service->saveTemplate($actor, [
                    'code' => $request->input('code', ''),
                    'title' => $request->input('title', ''),
                    'bot_id' => $request->input('bot_id', ''),
                    'template_id' => $request->input('template_id', ''),
                    'phone_column' => $request->input('phone_column', ''),
                    'body' => $request->input('body', ''),
                    'provider_approved' => $request->input('provider_approved', '0'),
                    'mappings' => $request->input('mappings', []),
                    'variable_items' => $request->input('variable_items', null),
                ]);
                $status = 'safir_template_saved';
                break;
            case 'toggle_template':
                $service->toggleTemplate(
                    $actor,
                    (string) $request->input('code', ''),
                    (string) $request->input('active', '0') === '1'
                );
                $status = 'safir_template_status_saved';
                break;
            case 'import':
                $service->import(
                    $actor,
                    is_array($_FILES['recipients'] ?? null) ? $_FILES['recipients'] : [],
                    (string) $request->input('template_code', '')
                );
                $status = 'safir_imported';
                break;
            case 'reset':
                $service->reset($actor);
                $status = 'safir_reset';
                break;
            case 'test':
                if ((string) $request->input('confirm_test', '') !== 'yes') {
                    throw new \RuntimeException('safir_confirmation_required');
                }
                $status = 'safir_test_' . $service->test($actor);
                break;
            case 'manual_test':
                if ((string) $request->input('confirm_manual_test', '') !== 'yes') {
                    throw new \RuntimeException('safir_confirmation_required');
                }
                $code = (string) $request->input('template_code', '');
                if (!preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $code)) {
                    throw new \RuntimeException('safir_template_invalid');
                }
                $fields = $request->input('test_fields', []);
                if (!is_array($fields)) {
                    throw new \RuntimeException('safir_test_fields_invalid');
                }
                $result = $service->manualTest(
                    $actor,
                    $code,
                    $fields,
                    (string) $request->input('test_nonce', '')
                );
                $status = 'safir_manual_test_' . (string) $result['status'];
                $redirect = '/admin/communications/safir?test=' . rawurlencode($code);
                break;
            case 'dispatch':
                if ((string) $request->input('dispatch_approved', '') !== 'yes') {
                    throw new \RuntimeException('safir_confirmation_required');
                }
                $before = $service->page($actor)['counts'];
                $after = $service->dispatch(
                    $actor,
                    (string) $request->input('confirmation', ''),
                    (string) $request->input('reference', '')
                );
                if ((string) $request->input('ajax', '') === '1') {
                    return $response->json([
                        'ok' => true,
                        'counts' => $after,
                        'halted' => ($after['failed'] > $before['failed']
                            || $after['unknown'] > $before['unknown']),
                    ]);
                }
                $status = 'safir_batch_completed';
                break;
            default:
                throw new \RuntimeException('safir_action_invalid');
        }
    } catch (\Throwable $exception) {
        $code = (string) $exception->getMessage();
        $status = str_starts_with($code, 'safir_') ? $code : 'safir_operation_failed';
        if ((string) $request->input('ajax', '') === '1') {
            return $response->status(422)->json(['ok' => false, 'code' => $status]);
        }
    }
    $separator = str_contains($redirect, '?') ? '&' : '?';
    return $response->redirect($redirect . $separator . 'status=' . rawurlencode($status));
});


/*
 * Per-template, header-only XLSX scaffold. This GET route only reads the
 * existing canonical template catalog; it never imports or sends messages.
 */
$router->get('/admin/communications/safir/template.xlsx', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/communications/safir/template.xlsx');
    if (!is_array($context)) {
        return $context;
    }
    $actor = (int) $context['user_id'];
    $authorization = new \App\Services\AuthorizationService();
    if (!$authorization->hasPermission($actor, 'notifications.send.manage')
        && !$authorization->hasPermission($actor, 'notifications.safir.manage')) {
        return $response->status(403)->send('Forbidden');
    }
    $code = trim((string) $request->input('template_code', ''));
    if (!preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $code)) {
        return $response->status(400)->send('Invalid template reference');
    }
    try {
        $template = (new \App\Services\BaleSafirTemplateCatalogService())->get($code);
        if (!is_array($template) || empty($template['active'])) {
            return $response->status(404)->send('Template unavailable');
        }
        $xlsx = (new \App\Services\BaleSafirExcelSkeletonService())->build($template);
        return $response->status(200)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="safir-' . $code . '-recipients.xlsx"')
            ->header('Content-Length', (string) strlen($xlsx))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->send($xlsx);
    } catch (\Throwable) {
        return $response->status(422)->send('Excel template unavailable');
    }
});

$router->get('/admin/communications/safir/test-report.csv', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/communications/safir/test-report.csv');
    if (!is_array($context)) {
        return $context;
    }
    $actor = (int) $context['user_id'];
    $authorization = new \App\Services\AuthorizationService();
    if (!$authorization->hasPermission($actor, 'notifications.send.manage')
        && !$authorization->hasPermission($actor, 'notifications.safir.manage')) {
        return $response->status(403)->send('Forbidden');
    }
    try {
        $csv = (new \App\Services\BaleSafirTestReportService())->csv();
        return $response->status(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="safir-manual-test-report.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->send($csv);
    } catch (\Throwable) {
        return $response->redirect('/admin/communications/safir?status=safir_export_failed');
    }
});

$router->get('/admin/communications/settings/safir/export', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/communications/settings/safir/export');
    if (!is_array($context)) {
        return $context;
    }
    $authorization = new \App\Services\AuthorizationService();
    $actor = (int) $context['user_id'];
    if (!$authorization->hasPermission($actor, 'notifications.send.manage')
        && !$authorization->hasPermission($actor, 'notifications.safir.manage')) {
        return $response->status(403)->send('Forbidden');
    }
    try {
        $csv = (new \App\Services\BaleSafirCampaignService())->export((int) $context['user_id']);
        return $response->status(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="bale-safir-report.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->send($csv);
    } catch (\Throwable) {
        return $response->redirect('/admin/communications/safir?status=safir_export_failed');
    }
});
