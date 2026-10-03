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


/*
 * ============================================================================
 * TICKET_WORK_POLICY_ADMIN_UI_V1
 * Dynamic Ticket<->Work policy administration
 * ============================================================================
 *
 * Read:
 *   work.settings.view
 *
 * Mutation:
 *   work.settings.manage
 *
 * No policy row is created by installation. Writes occur only after an
 * authorized POST with a valid CSRF token.
 */

$ticketWorkPolicyRedirect =
    static function (
        $response,
        string $kind,
        string $status
    ) {
        $kind =
            in_array(
                $kind,
                ['destination', 'access'],
                true
            )
                ? $kind
                : 'destination';

        return $response->redirect(
            '/admin/work/settings/ticket-work-policy'
            . '?kind='
            . rawurlencode($kind)
            . '&status='
            . rawurlencode($status)
        );
    };


$router->get(
    '/admin/work/settings/ticket-work-policy',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $workSettingsContext
    ) {
        $context =
            $workSettingsContext(
                $response,
                'work.settings.view'
            );

        if (!is_array($context)) {
            return $context;
        }

        $kind =
            trim(
                (string) $request->input(
                    'kind',
                    'destination'
                )
            );

        if (
            !in_array(
                $kind,
                ['destination', 'access'],
                true
            )
        ) {
            $kind = 'destination';
        }

        try {
            $page =
                (
                    new \App\Services\Work\TicketWorkPolicyAdminService()
                )->page();
        } catch (\Throwable) {
            return $response->redirect(
                '/admin/work/settings'
                . '?status=ticket_work_policy_unavailable'
            );
        }

        return $adminRender(
            $response,
            'work-ticket-policy',
            [
                'context' => $context,
                'policyPage' => $page,
                'kind' => $kind,
                'status' =>
                    trim(
                        (string) $request->input(
                            'status',
                            ''
                        )
                    ),
            ]
        );
    }
);


$ticketWorkPolicySave =
    static function (
        $request,
        $response,
        $workSettingsContext,
        $ticketWorkPolicyRedirect,
        ?int $id
    ) {
        $context =
            $workSettingsContext(
                $response,
                'work.settings.manage'
            );

        if (!is_array($context)) {
            return $context;
        }

        $kind =
            trim(
                (string) $request->route(
                    'kind'
                )
            );

        if (
            !in_array(
                $kind,
                ['destination', 'access'],
                true
            )
        ) {
            return $ticketWorkPolicyRedirect(
                $response,
                'destination',
                'invalid'
            );
        }

        $csrf = new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $ticketWorkPolicyRedirect(
                $response,
                $kind,
                'invalid_csrf'
            );
        }

        $result =
            (
                new \App\Services\Work\TicketWorkPolicyAdminService()
            )->save(
                $kind,
                $id,
                [
                    'fields' =>
                        $request->input(
                            'fields',
                            []
                        ),
                ],
                (int) $context['user_id']
            );

        return $ticketWorkPolicyRedirect(
            $response,
            $kind,
            (string) (
                $result['status']
                ?? 'failed'
            )
        );
    };


$router->post(
    '/admin/work/settings/ticket-work-policy/{kind}',
    function (
        $request,
        $response
    ) use (
        $workSettingsContext,
        $ticketWorkPolicyRedirect,
        $ticketWorkPolicySave
    ) {
        return $ticketWorkPolicySave(
            $request,
            $response,
            $workSettingsContext,
            $ticketWorkPolicyRedirect,
            null
        );
    }
);


$router->post(
    '/admin/work/settings/ticket-work-policy/{kind}/{id}',
    function (
        $request,
        $response
    ) use (
        $workSettingsContext,
        $ticketWorkPolicyRedirect,
        $ticketWorkPolicySave
    ) {
        $id =
            (int) $request->route(
                'id'
            );

        if ($id < 1) {
            return $ticketWorkPolicyRedirect(
                $response,
                (string) (
                    $request->route('kind')
                    ?? 'destination'
                ),
                'invalid'
            );
        }

        return $ticketWorkPolicySave(
            $request,
            $response,
            $workSettingsContext,
            $ticketWorkPolicyRedirect,
            $id
        );
    }
);


$ticketWorkPolicyActiveState =
    static function (
        $request,
        $response,
        $workSettingsContext,
        $ticketWorkPolicyRedirect,
        bool $active
    ) {
        $context =
            $workSettingsContext(
                $response,
                'work.settings.manage'
            );

        if (!is_array($context)) {
            return $context;
        }

        $kind =
            trim(
                (string) $request->route(
                    'kind'
                )
            );

        $id =
            (int) $request->route(
                'id'
            );

        if (
            !in_array(
                $kind,
                ['destination', 'access'],
                true
            )
            || $id < 1
        ) {
            return $ticketWorkPolicyRedirect(
                $response,
                'destination',
                'invalid'
            );
        }

        $csrf = new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $ticketWorkPolicyRedirect(
                $response,
                $kind,
                'invalid_csrf'
            );
        }

        $result =
            (
                new \App\Services\Work\TicketWorkPolicyAdminService()
            )->setActive(
                $kind,
                $id,
                $active
            );

        return $ticketWorkPolicyRedirect(
            $response,
            $kind,
            (string) (
                $result['status']
                ?? 'failed'
            )
        );
    };


$router->post(
    '/admin/work/settings/ticket-work-policy/{kind}/{id}/deactivate',
    function (
        $request,
        $response
    ) use (
        $workSettingsContext,
        $ticketWorkPolicyRedirect,
        $ticketWorkPolicyActiveState
    ) {
        return $ticketWorkPolicyActiveState(
            $request,
            $response,
            $workSettingsContext,
            $ticketWorkPolicyRedirect,
            false
        );
    }
);


$router->post(
    '/admin/work/settings/ticket-work-policy/{kind}/{id}/restore',
    function (
        $request,
        $response
    ) use (
        $workSettingsContext,
        $ticketWorkPolicyRedirect,
        $ticketWorkPolicyActiveState
    ) {
        return $ticketWorkPolicyActiveState(
            $request,
            $response,
            $workSettingsContext,
            $ticketWorkPolicyRedirect,
            true
        );
    }
);
