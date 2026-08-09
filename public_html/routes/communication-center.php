<?php

$communicationAccess = static function (
    array $context,
    string $method,
    string $path
): bool {
    return (new \App\Services\DynamicRouteAccessService())
        ->can(
            (int) $context['user_id'],
            $method,
            $path
        );
};

$communicationFilters = static function ($request): array {
    $dateFilter = static function (string $name) use ($request): string {
        $value = trim((string) $request->input($name, ''));
        if ($value === '') {
            return '';
        }

        try {
            return (string) (\IPKF\Support\PersianDate::toGregorianDate($value) ?? '');
        } catch (\Throwable) {
            return '';
        }
    };

    return [
        'q' => trim((string) $request->input('q', '')),
        'status' => trim((string) $request->input('status', '')),
        'unread' => trim((string) $request->input('unread', '')),
        'from' => $dateFilter('from'),
        'to' => $dateFilter('to'),
        'sort' => trim((string) $request->input('sort', 'date')),
        'direction' => trim((string) $request->input('direction', 'desc')),
        'page' => max(1, (int) $request->input('page', 1)),
        'per_page' => (int) $request->input('per_page', 20),
    ];
};

$router->get('/admin/communications', function (
    $request,
    $response
) use ($adminRender, $adminGuard, $communicationAccess) {
    $context = $adminGuard(
        $response,
        '/admin/communications'
    );

    if (!is_array($context)) {
        return $context;
    }

    if (!$communicationAccess(
        $context,
        'GET',
        '/admin/communications'
    )) {
        return $response->redirect(
            '/admin/dashboard?error=forbidden'
        );
    }

    $items = (
        new \App\Services\DynamicAdminNavigationService()
    )->children(
        (int) $context['user_id'],
        'core',
        'communications'
    );

    return $adminRender(
        $response,
        'communication-hub',
        [
            'title' => 'پیام‌ها و اعلان‌ها',
            'context' => $context,
            'items' => $items,
        ]
    );
});

$router->get('/admin/messages/inbox', function (
    $request,
    $response
) use ($adminRender, $adminGuard, $communicationAccess, $communicationFilters) {
    $context = $adminGuard(
        $response,
        '/admin/messages/inbox'
    );

    if (!is_array($context)) {
        return $context;
    }

    if (!$communicationAccess(
        $context,
        'GET',
        '/admin/messages/inbox'
    )) {
        return $response->redirect(
            '/admin/dashboard?error=forbidden'
        );
    }

    return $adminRender(
        $response,
        'messages-inbox',
        [
            'title' => 'کارتابل داخلی',
            'context' => $context,
            'page' => (
                new \App\Services\InternalMessageService()
            )->inbox((int) $context['user_id'], $communicationFilters($request)),
        ]
    );
});

$router->get('/admin/messages/compose', function (
    $request,
    $response
) use ($adminRender, $adminGuard, $communicationAccess) {
    $context = $adminGuard(
        $response,
        '/admin/messages/compose'
    );

    if (!is_array($context)) {
        return $context;
    }

    if (!$communicationAccess(
        $context,
        'GET',
        '/admin/messages/compose'
    )) {
        return $response->redirect(
            '/admin/dashboard?error=forbidden'
        );
    }

    return $adminRender(
        $response,
        'messages-compose',
        [
            'title' => 'ارسال پیام',
            'context' => $context,
            'page' => (
                new \App\Services\InternalMessageService()
            )->composePage((int) $context['user_id']),
            'status' => trim(
                (string) $request->input('status', '')
            ),
        ]
    );
});

$router->post('/admin/messages/compose', function (
    $request,
    $response
) use ($adminGuard, $communicationAccess) {
    $context = $adminGuard(
        $response,
        '/admin/messages/compose'
    );

    if (!is_array($context)) {
        return $context;
    }

    if (!$communicationAccess(
        $context,
        'POST',
        '/admin/messages/compose'
    )) {
        return $response->redirect(
            '/admin/dashboard?error=forbidden'
        );
    }

    try {
        $result = (
            new \App\Services\InternalMessageService()
        )->send(
            (int) $context['user_id'],
            [
                'recipient_user_ids' =>
                    $request->input('recipient_user_ids', []),
                'subject' => $request->input('subject'),
                'body' => $request->input('body'),
            ],
            is_array($_FILES['attachments'] ?? null) ? $_FILES['attachments'] : []
        );

        return $response->redirect(
            '/admin/messages/thread/'
            . rawurlencode(
                $result['conversation_reference']
            )
            . '?status=sent'
        );
    } catch (\Throwable $exception) {
        return $response->redirect(
            '/admin/messages/compose?status='
            . rawurlencode($exception->getMessage())
        );
    }
});

$router->get('/admin/messages/sent', function (
    $request,
    $response
) use ($adminRender, $adminGuard, $communicationAccess, $communicationFilters) {
    $context = $adminGuard(
        $response,
        '/admin/messages/sent'
    );

    if (!is_array($context)) {
        return $context;
    }

    if (!$communicationAccess(
        $context,
        'GET',
        '/admin/messages/sent'
    )) {
        return $response->redirect(
            '/admin/dashboard?error=forbidden'
        );
    }

    return $adminRender(
        $response,
        'messages-sent',
        [
            'title' => 'پیام‌های ارسالی',
            'context' => $context,
            'page' => (
                new \App\Services\InternalMessageService()
            )->sent((int) $context['user_id'], $communicationFilters($request)),
        ]
    );
});

$router->get(
    '/admin/messages/thread/{reference}',
    function (
        $request,
        $response
    ) use ($adminRender, $adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/messages/thread'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );
        $path = '/admin/messages/thread/'
            . rawurlencode($reference);

        if (!$communicationAccess(
            $context,
            'GET',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $page = (
            new \App\Services\InternalMessageService()
        )->thread(
            (int) $context['user_id'],
            $reference
        );

        if ($page === null) {
            return $response->redirect(
                '/admin/messages/inbox?status=not_found'
            );
        }

        return $adminRender(
            $response,
            'messages-thread',
            [
                'title' => 'گفتگوی داخلی',
                'context' => $context,
                'page' => $page,
            ]
        );
    }
);

$router->post(
    '/admin/messages/thread/{reference}/reply',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/messages/thread'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );
        $path = '/admin/messages/thread/'
            . rawurlencode($reference)
            . '/reply';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        try {
            (
                new \App\Services\InternalMessageService()
            )->reply(
                (int) $context['user_id'],
                $reference,
                (string) $request->input('body', ''),
                is_array($_FILES['attachments'] ?? null) ? $_FILES['attachments'] : []
            );

            return $response->redirect(
                '/admin/messages/thread/'
                . rawurlencode($reference)
                . '?status=replied'
            );
        } catch (\Throwable $exception) {
            return $response->redirect(
                '/admin/messages/thread/'
                . rawurlencode($reference)
                . '?status='
                . rawurlencode($exception->getMessage())
            );
        }
    }
);

$router->post(
    '/admin/messages/thread/{reference}/{action}',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/messages/thread'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim((string) $request->route('reference'));
        $action = trim((string) $request->route('action'));
        $threadPath = '/admin/messages/thread/'
            . rawurlencode($reference);

        if (
            !in_array($action, ['close', 'reopen'], true)
            || !$communicationAccess(
                $context,
                'POST',
                $threadPath . '/reply'
            )
        ) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        try {
            $service = new \App\Services\InternalMessageService();
            $action === 'close'
                ? $service->close((int) $context['user_id'], $reference)
                : $service->reopen((int) $context['user_id'], $reference);

            return $response->redirect(
                $threadPath . '?status=' . (
                    $action === 'close' ? 'closed' : 'reopened'
                )
            );
        } catch (\Throwable $exception) {
            return $response->redirect(
                $threadPath . '?status='
                . rawurlencode($exception->getMessage())
            );
        }
    }
);

$router->get('/admin/messages/attachments/{reference}', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/messages/inbox');
    if (!is_array($context)) return $context;
    $file = (new \App\Services\InternalMessageAttachmentService())->download(
        (int) $context['user_id'], trim((string) $request->route('reference'))
    );
    if ($file === null) return $response->redirect('/admin/messages/inbox?status=attachment_not_found');
    $content = file_get_contents((string) $file['storage_path']);
    if ($content === false) return $response->redirect('/admin/messages/inbox?status=attachment_not_found');
    $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $file['original_name']) ?: 'attachment';
    return $response->status(200)->header('Content-Type', (string) $file['mime_type'])
        ->header('X-Content-Type-Options', 'nosniff')->header('Cache-Control', 'private, no-store')
        ->header('Content-Disposition', 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode((string) $file['original_name']))
        ->body($content);
});

$router->get('/admin/messages/monitor', function ($request, $response) use ($adminRender, $adminGuard, $communicationAccess, $communicationFilters) {
    $context = $adminGuard($response, '/admin/messages/monitor');
    if (!is_array($context)) return $context;
    if (!$communicationAccess($context, 'GET', '/admin/messages/monitor')) return $response->redirect('/admin/dashboard?error=forbidden');
    try { $page = (new \App\Services\InternalMessageAdministrationService())->index((int) $context['user_id'], $communicationFilters($request)); }
    catch (\Throwable) { return $response->redirect('/admin/dashboard?error=forbidden'); }
    return $adminRender($response, 'messages-monitor', ['title' => 'نظارت بر پیام‌ها', 'context' => $context, 'page' => $page]);
});

$router->post('/admin/messages/monitor/{reference}', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/messages/monitor');
    if (!is_array($context)) return $context;
    $reference = trim((string) $request->route('reference'));
    try {
        $thread = (new \App\Services\InternalMessageAdministrationService())->thread((int) $context['user_id'], $reference, (string) $request->input('reason', ''));
        if ($thread === null) throw new \RuntimeException('not_found');
        \IPKF\Support\Session::put('message_monitor_' . $reference, $thread);
        return $response->redirect('/admin/messages/monitor/view/' . rawurlencode($reference));
    } catch (\Throwable $e) { return $response->redirect('/admin/messages/monitor?status=' . rawurlencode($e->getMessage())); }
});

$router->get('/admin/messages/monitor/view/{reference}', function ($request, $response) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/messages/monitor');
    if (!is_array($context)) return $context;
    $reference = trim((string) $request->route('reference'));
    $page = \IPKF\Support\Session::get('message_monitor_' . $reference);
    if (!is_array($page)) return $response->redirect('/admin/messages/monitor?status=reason_required');
    return $adminRender($response, 'messages-monitor-thread', ['title' => 'مشاهده نظارتی پیام', 'context' => $context, 'page' => $page]);
});

$router->post('/admin/communications/settings/internal-messages', function ($request, $response) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/communications/settings');
    if (!is_array($context)) return $context;
    try { (new \App\Services\InternalMessageAdministrationService())->saveSettings((int) $context['user_id'], $_POST); }
    catch (\Throwable) { return $response->redirect('/admin/dashboard?error=forbidden'); }
    return $response->redirect('/admin/communications/settings?section=internal&status=saved');
});

$router->get(
    '/admin/communications/settings',
    function (
        $request,
        $response
    ) use ($adminRender, $adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        if (!$communicationAccess(
            $context,
            'GET',
            '/admin/communications/settings'
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $section = trim(
            (string) $request->input(
                'section',
                'providers'
            )
        );

        $approvalReference = trim(
            (string) $request->input(
                'approval_reference',
                ''
            )
        );

        if (
            $section === 'approvals'
            && $approvalReference !== ''
        ) {
            (
                new \App\Services\NotificationApprovalAlertService()
            )->markViewed(
                (int) $context['user_id'],
                $approvalReference
            );
        }

        $targetsReference = trim(
            (string) $request->input(
                'targets_reference',
                ''
            )
        );

        if (
            $section === 'approvals'
            && $targetsReference !== ''
        ) {
            try {
                $targetsPage = (
                    new \App\Services\NotificationApprovalManagementService()
                )->targetPage(
                    (int) $context['user_id'],
                    $targetsReference,
                    [
                        'q' =>
                            $request->input(
                                'targets_q',
                                ''
                            ),

                        'channel' =>
                            $request->input(
                                'targets_channel',
                                ''
                            ),

                        'status' =>
                            $request->input(
                                'targets_status',
                                ''
                            ),

                        'page' =>
                            $request->input(
                                'targets_page',
                                1
                            ),

                        'per_page' =>
                            $request->input(
                                'targets_per_page',
                                20
                            ),
                    ]
                );

                return $response->json([
                    'status' => 'ok',
                    'data' => $targetsPage,
                ]);
            } catch (
                \InvalidArgumentException $exception
            ) {
                return $response
                    ->status(422)
                    ->json([
                        'status' => 'error',
                        'code' =>
                            $exception->getMessage(),
                    ]);
            } catch (
                \RuntimeException $exception
            ) {
                $code = trim(
                    $exception->getMessage()
                );

                $httpStatus = match ($code) {
                    'notification_approval_view_forbidden'
                        => 403,

                    'notification_approval_request_not_found'
                        => 404,

                    default => 422,
                };

                return $response
                    ->status($httpStatus)
                    ->json([
                        'status' => 'error',
                        'code' => $code,
                    ]);
            } catch (\Throwable) {
                return $response
                    ->status(500)
                    ->json([
                        'status' => 'error',
                        'code' =>
                            'notification_approval_targets_failed',
                    ]);
            }
        }

        $settings =
            new \App\Services\CommunicationSettingsService();
        $page = $settings->page(
            (int) $context['user_id'],
            $section,
            trim((string) $request->input('edit', '')),
            [
                'q' => $request->input('q', ''),
                'channel' =>
                    $request->input('channel', ''),
                'status' =>
                    $request->input('report_status', ''),
                'provider' =>
                    $request->input('provider', ''),
                'from' =>
                    $request->input('from', ''),
                'to' =>
                    $request->input('to', ''),
                'sort' =>
                    $request->input(
                        'sort',
                        'created_desc'
                    ),
                'page' =>
                    $request->input('page', 1),
                'per_page' =>
                    $request->input('per_page', 20),
            ]
        );

        if (($page['allowed'] ?? false) !== true) {
            return $response->redirect(
                '/admin/communications?error=forbidden'
            );
        }

        return $adminRender(
            $response,
            'communication-settings',
            [
                'title' => 'تنظیمات پیام و اعلان',
                'context' => $context,
                'page' => $page,
                'status' => trim(
                    (string) $request->input('status', '')
                ),
            ]
        );
    }
);

$router->post(
    '/admin/communications/settings/approvals/{reference}/approve',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );

        $path =
            '/admin/communications/settings/'
            . 'approvals/'
            . rawurlencode($reference)
            . '/approve';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        if (!(new \IPKF\Security\Csrf())->check(
            (string) $request->input('_token', '')
        )) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status=invalid_csrf'
            );
        }

        try {
            $result = (
                new \App\Services\NotificationApprovalManagementService()
            )->approve(
                (int) $context['user_id'],
                $reference,
                (string) $request->input(
                    'reason',
                    ''
                )
            );

            $workflowStatus = (string) (
                $result['status_code']
                ?? ''
            );

            $status = match ($workflowStatus) {
                'dispatched' =>
                    'notification_approval_approved_dispatched',

                'partially_dispatched' =>
                    'notification_approval_approved_partial',

                'failed' =>
                    'notification_approval_approved_failed',

                default =>
                    'notification_approval_approved',
            };

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status='
                . rawurlencode($status)
            );
        } catch (\Throwable $exception) {
            $status = trim(
                $exception->getMessage()
            );

            if (!str_starts_with(
                $status,
                'notification_approval_'
            )) {
                $status =
                    'notification_approval_operation_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status='
                . rawurlencode($status)
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/approvals/{reference}/reject',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );

        $path =
            '/admin/communications/settings/'
            . 'approvals/'
            . rawurlencode($reference)
            . '/reject';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        if (!(new \IPKF\Security\Csrf())->check(
            (string) $request->input('_token', '')
        )) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status=invalid_csrf'
            );
        }

        try {
            (
                new \App\Services\NotificationApprovalManagementService()
            )->reject(
                (int) $context['user_id'],
                $reference,
                (string) $request->input(
                    'reason',
                    ''
                )
            );

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status=notification_approval_rejected'
            );
        } catch (\Throwable $exception) {
            $status = trim(
                $exception->getMessage()
            );

            if (!str_starts_with(
                $status,
                'notification_approval_'
            )) {
                $status =
                    'notification_approval_operation_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status='
                . rawurlencode($status)
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/approvals/{reference}/retry',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );

        $path =
            '/admin/communications/settings/'
            . 'approvals/'
            . rawurlencode($reference)
            . '/retry';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        if (!(new \IPKF\Security\Csrf())->check(
            (string) $request->input('_token', '')
        )) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status=invalid_csrf'
                . '#approval-history'
            );
        }

        try {
            $result = (
                new \App\Services\NotificationApprovalManagementService()
            )->retry(
                (int) $context['user_id'],
                $reference
            );

            $workflowStatus = (string) (
                $result['status_code']
                ?? ''
            );

            $status = match ($workflowStatus) {
                'dispatched' =>
                    'notification_approval_retry_dispatched',

                'partially_dispatched' =>
                    'notification_approval_retry_partial',

                'failed' =>
                    'notification_approval_retry_failed',

                default =>
                    'notification_approval_operation_failed',
            };

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status='
                . rawurlencode($status)
                . '#approval-history'
            );
        } catch (\Throwable $exception) {
            $code = trim(
                $exception->getMessage()
            );

            $status = match ($code) {
                'notification_approval_retry_forbidden',
                'notification_approval_retry_not_available',
                'notification_approval_request_not_found'
                    => $code,

                default =>
                    'notification_approval_operation_failed',
            };

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=approvals'
                . '&status='
                . rawurlencode($status)
                . '#approval-history'
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/send',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $path =
            '/admin/communications/settings/send';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $token = (string) $request->input(
            '_token',
            ''
        );

        if (!(new \IPKF\Security\Csrf())->check($token)) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=send&status=invalid_csrf'
            );
        }

        try {
            $service =
                new \App\Services\NotificationSendCenterService();

            $result = $service->send(
                (int) $context['user_id'],
                [
                    'message_type_code' =>
                        $request->input(
                            'message_type_code',
                            'text'
                        ),
                    'channels' =>
                        $request->input(
                            'channels',
                            []
                        ),
                    'recipient_user_ids' =>
                        $request->input(
                            'recipient_user_ids',
                            []
                        ),
                    'manual_email' =>
                        $request->input(
                            'manual_email',
                            ''
                        ),
                    'manual_sms' =>
                        $request->input(
                            'manual_sms',
                            ''
                        ),
                    'manual_messenger' =>
                        $request->input(
                            'manual_messenger',
                            ''
                        ),
                    'subject' =>
                        $request->input(
                            'subject',
                            ''
                        ),
                    'body' =>
                        $request->input(
                            'body',
                            ''
                        ),
                    'request_reason' =>
                        $request->input(
                            'request_reason',
                            ''
                        ),
                    'confirm_dispatch' =>
                        $request->input(
                            'confirm_dispatch',
                            ''
                        ),
                ],
                is_array(
                    $_FILES['media_files'] ?? null
                ) ? $_FILES['media_files'] : []
            );

            $service->storeResult(
                (int) $context['user_id'],
                $result
            );

            $status =
                (($result['workflow_status'] ?? '')
                    === 'pending_approval')
                    ? 'notification_send_approval_submitted'
                    : 'notification_send_completed';

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=send'
                . '&status='
                . rawurlencode($status)
            );
        } catch (
            \InvalidArgumentException
            | \RuntimeException $exception
        ) {
            $status = trim(
                $exception->getMessage()
            );

            if (
                !str_starts_with(
                    $status,
                    'notification_send_'
                )
            ) {
                $status =
                    'notification_send_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=send&status='
                . rawurlencode($status)
            );
        } catch (\Throwable) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=send'
                . '&status=notification_send_failed'
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/send/bale-invitations',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $path =
            '/admin/communications/settings/send/bale-invitations';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        if (!(new \IPKF\Security\Csrf())->check(
            (string) $request->input('_token', '')
        )) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections&status=invalid_csrf'
            );
        }

        try {
            $result = (
                new \App\Services\NotificationBaleEnrollmentService()
            )->invite(
                (int) $context['user_id'],
                is_array(
                    $request->input(
                        'recipient_user_ids',
                        []
                    )
                )
                    ? $request->input(
                        'recipient_user_ids',
                        []
                    )
                    : []
            );

            $status =
                (int) ($result['sent'] ?? 0) > 0
                    ? 'notification_bale_invitation_sent'
                    : 'notification_bale_invitation_failed';

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections&status='
                . rawurlencode($status)
            );
        } catch (\Throwable $exception) {
            $status = trim(
                $exception->getMessage()
            );

            if (!str_starts_with(
                $status,
                'notification_bale_'
            )) {
                $status =
                    'notification_bale_invitation_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections&status='
                . rawurlencode($status)
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/bale-connections/disconnect',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        /*
         * Reuse the established Bale invitation
         * capability until route permissions become
         * independently configurable.
         */
        $permissionPath =
            '/admin/communications/settings/send/bale-invitations';

        if (!$communicationAccess(
            $context,
            'POST',
            $permissionPath
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        if (!(new \IPKF\Security\Csrf())->check(
            (string) $request->input('_token', '')
        )) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections'
                . '&status=invalid_csrf'
            );
        }

        try {
            (
                new \App\Services\NotificationBaleConnectionManagementService()
            )->disconnect(
                (int) $context['user_id'],
                (int) $request->input('user_id', 0)
            );

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections'
                . '&status=notification_bale_connection_disconnected'
            );
        } catch (\Throwable $exception) {
            $status = trim(
                $exception->getMessage()
            );

            if (!str_starts_with(
                $status,
                'notification_bale_'
            )) {
                $status =
                    'notification_bale_connection_disconnect_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=bale_connections&status='
                . rawurlencode($status)
            );
        }
    }
);

$router->post(
    '/webhooks/notifications/bale/{reference}/{signature}',
    function ($request, $response) {
        try {
            return $response->json(
                (
                    new \App\Services\NotificationBaleEnrollmentService()
                )->handleWebhook(
                    trim((string) $request->route(
                        'reference',
                        ''
                    )),
                    trim((string) $request->route(
                        'signature',
                        ''
                    )),
                    $request->all()
                )
            );
        } catch (\Throwable) {
            return $response
                ->status(200)
                ->json(['ok' => false]);
        }
    }
);

$router->post(
    '/admin/communications/settings/preferences',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        if (!$communicationAccess(
            $context,
            'POST',
            '/admin/communications/settings/preferences'
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        (
            new \App\Services\CommunicationSettingsService()
        )->savePreferences(
            (int) $context['user_id'],
            $request->input('channels', [])
        );

        return $response->redirect(
            '/admin/communications/settings'
            . '?section=preferences&status=saved'
        );
    }
);

$router->post(
    '/admin/communications/settings/defaults/save',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $path =
            '/admin/communications/settings/defaults/save';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $token = (string) $request->input(
            '_token',
            ''
        );

        if (!(new \IPKF\Security\Csrf())->check($token)) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=defaults&status=invalid_csrf'
            );
        }

        try {
            (
                new \App\Services\CommunicationSettingsService()
            )->saveProviderDefaults(
                (int) $context['user_id'],
                $request->input('defaults', [])
            );

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=defaults'
                . '&status=provider_defaults_saved'
            );
        } catch (
            \InvalidArgumentException
            | \RuntimeException $exception
        ) {
            $status = trim($exception->getMessage());

            if (
                $status === ''
                || (
                    !str_starts_with(
                        $status,
                        'provider_defaults_'
                    )
                    && $status !==
                        'provider_management_forbidden'
                )
            ) {
                $status =
                    'provider_defaults_save_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=defaults&status='
                . rawurlencode($status)
            );
        } catch (\Throwable) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=defaults'
                . '&status=provider_defaults_save_failed'
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/providers/save',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $path =
            '/admin/communications/settings/providers/save';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $token = (string) $request->input(
            '_token',
            ''
        );

        if (!(new \IPKF\Security\Csrf())->check($token)) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status=invalid_csrf'
            );
        }

        $formMode = strtolower(trim(
            (string) $request->input(
                'form_mode',
                'create'
            )
        ));

        $reference = $formMode === 'edit'
            ? trim(
                (string) $request->input(
                    'public_reference',
                    ''
                )
            )
            : '';

        try {
            $result = (
                new \App\Services\NotificationProviderManagementService()
            )->save(
                (int) $context['user_id'],
                [
                    'public_reference' => $reference,
                    'channel_code' =>
                        $request->input(
                            'channel_code',
                            ''
                        ),
                    'provider_type_id' =>
                        $request->input(
                            'provider_type_id',
                            0
                        ),
                    'code' =>
                        $request->input('code', ''),
                    'title' =>
                        $request->input('title', ''),
                    'description' =>
                        $request->input(
                            'description',
                            ''
                        ),
                    'priority' =>
                        $request->input(
                            'priority',
                            0
                        ),
                    'daily_limit' =>
                        $request->input(
                            'daily_limit',
                            ''
                        ),
                    'monthly_limit' =>
                        $request->input(
                            'monthly_limit',
                            ''
                        ),
                    'is_enabled' =>
                        $request->input(
                            'is_enabled',
                            ''
                        ),
                    'configuration' =>
                        $request->input(
                            'configuration',
                            []
                        ),
                    'secrets' =>
                        $request->input(
                            'secrets',
                            []
                        ),
                ]
            );

            $status = !empty($result['created'])
                ? 'provider_created'
                : 'provider_updated';

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers'
                . '&status=' . $status
                . '&edit=' . rawurlencode(
                    (string) $result[
                        'public_reference'
                    ]
                )
            );
        } catch (
            \InvalidArgumentException
            | \RuntimeException $exception
        ) {
            $query = '?section=providers&status='
                . rawurlencode(
                    $exception->getMessage()
                );

            if ($reference !== '') {
                $query .= '&edit='
                    . rawurlencode($reference);
            }

            return $response->redirect(
                '/admin/communications/settings'
                . $query
            );
        } catch (\Throwable) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers'
                . '&status=provider_save_failed'
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/providers/{reference}/test-send',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );
        $path =
            '/admin/communications/settings/providers/'
            . rawurlencode($reference)
            . '/test-send';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $token = (string) $request->input(
            '_token',
            ''
        );

        if (!(new \IPKF\Security\Csrf())->check($token)) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status=invalid_csrf'
            );
        }

        try {
            $result = (
                new \App\Services\NotificationProviderTestService()
            )->send(
                (int) $context['user_id'],
                $reference,
                [
                    'recipient' => $request->input(
                        'recipient',
                        ''
                    ),
                    'subject' => $request->input(
                        'subject',
                        ''
                    ),
                    'body' => $request->input(
                        'body',
                        ''
                    ),
                ]
            );

            $status = trim(
                (string) (
                    $result['status_code']
                    ?? 'provider_test_failed'
                )
            );

            if (
                !in_array(
                    $status,
                    [
                        'provider_test_email_sent',
                        'provider_test_sms_sent',
                        'provider_test_bale_sent',
                    ],
                    true
                )
            ) {
                $status = 'provider_test_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status='
                . rawurlencode($status)
            );
        } catch (
            \InvalidArgumentException
            | \RuntimeException $exception
        ) {
            $status = trim($exception->getMessage());

            if (
                $status === ''
                || (
                    !str_starts_with(
                        $status,
                        'provider_test_'
                    )
                    && !in_array(
                        $status,
                        [
                            'provider_reference_invalid',
                            'provider_instance_not_found',
                            'provider_management_forbidden',
                        ],
                        true
                    )
                )
            ) {
                $status = 'provider_test_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status='
                . rawurlencode($status)
            );
        } catch (\Throwable) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers'
                . '&status=provider_test_failed'
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/providers/{reference}/test-email',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );
        $path =
            '/admin/communications/settings/providers/'
            . rawurlencode($reference)
            . '/test-email';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $token = (string) $request->input(
            '_token',
            ''
        );

        if (!(new \IPKF\Security\Csrf())->check($token)) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status=invalid_csrf'
            );
        }

        try {
            (
                new \App\Services\NotificationProviderTestService()
            )->sendEmail(
                (int) $context['user_id'],
                $reference,
                [
                    'recipient' => $request->input(
                        'recipient',
                        ''
                    ),
                    'subject' => $request->input(
                        'subject',
                        ''
                    ),
                    'body' => $request->input(
                        'body',
                        ''
                    ),
                ]
            );

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers'
                . '&status=provider_test_sent'
            );
        } catch (
            \InvalidArgumentException
            | \RuntimeException $exception
        ) {
            $status = trim($exception->getMessage());

            if (
                $status === ''
                || (
                    !str_starts_with(
                        $status,
                        'provider_test_'
                    )
                    && !in_array(
                        $status,
                        [
                            'provider_reference_invalid',
                            'provider_instance_not_found',
                            'provider_management_forbidden',
                        ],
                        true
                    )
                )
            ) {
                $status = 'provider_test_failed';
            }

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status='
                . rawurlencode($status)
            );
        } catch (\Throwable) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers'
                . '&status=provider_test_failed'
            );
        }
    }
);

$router->post(
    '/admin/communications/settings/providers/{reference}/status',
    function (
        $request,
        $response
    ) use ($adminGuard, $communicationAccess) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );
        $path =
            '/admin/communications/settings/providers/'
            . rawurlencode($reference)
            . '/status';

        if (!$communicationAccess(
            $context,
            'POST',
            $path
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $token = (string) $request->input(
            '_token',
            ''
        );

        if (!(new \IPKF\Security\Csrf())->check($token)) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status=invalid_csrf'
            );
        }

        $enabled = (string) $request->input(
            'enabled',
            '0'
        ) === '1';

        try {
            (
                new \App\Services\NotificationProviderManagementService()
            )->setEnabled(
                (int) $context['user_id'],
                $reference,
                $enabled
            );

            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers&status='
                . (
                    $enabled
                        ? 'provider_enabled'
                        : 'provider_disabled'
                )
            );
        } catch (\Throwable) {
            return $response->redirect(
                '/admin/communications/settings'
                . '?section=providers'
                . '&status=provider_status_failed'
            );
        }
    }
);
