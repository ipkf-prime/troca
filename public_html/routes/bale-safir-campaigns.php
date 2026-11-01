<?php
declare(strict_types=1);

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
    $redirect = '/admin/communications/settings?section=safir';
    if (!(new \App\Services\AuthorizationService())->hasPermission($actor, 'notifications.send.manage')) {
        return $response->status(403)->send('Forbidden');
    }
    if (!(new \IPKF\Security\Csrf())->check((string) $request->input('_token', ''))) {
        return $response->redirect($redirect . '&status=safir_invalid_csrf');
    }
    $action = (string) $request->input('action', '');
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
    return $response->redirect($redirect . '&status=' . rawurlencode($status));
});

$router->get('/admin/communications/settings/safir/export', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/communications/settings/safir/export');
    if (!is_array($context)) {
        return $context;
    }
    if (!(new \App\Services\AuthorizationService())->hasPermission(
        (int) $context['user_id'], 'notifications.send.manage'
    )) {
        return $response->status(403)->send('Forbidden');
    }
    try {
        $csv = (new \App\Services\BaleSafirCampaignService())->export((int) $context['user_id']);
        return $response->status(200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="bale-safir-report.csv"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->body($csv);
    } catch (\Throwable) {
        return $response->redirect('/admin/communications/settings?section=safir&status=safir_export_failed');
    }
});
