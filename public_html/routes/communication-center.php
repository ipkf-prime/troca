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
) use ($adminRender, $adminGuard, $communicationAccess) {
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
            )->inbox((int) $context['user_id']),
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
                'recipient_user_id' =>
                    $request->input('recipient_user_id'),
                'subject' => $request->input('subject'),
                'body' => $request->input('body'),
            ]
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
) use ($adminRender, $adminGuard, $communicationAccess) {
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
            )->sent((int) $context['user_id']),
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
                (string) $request->input('body', '')
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

        $allowedItems = (
            new \App\Services\DynamicAdminNavigationService()
        )->children(
            (int) $context['user_id'],
            'core',
            'communications'
        );
        $requestedPath =
            '/admin/communications/settings';
        $sectionAllowed = false;

        foreach ($allowedItems as $item) {
            $itemPath = (string) (
                parse_url(
                    (string) $item['url'],
                    PHP_URL_PATH
                ) ?: ''
            );
            parse_str(
                (string) (
                    parse_url(
                        (string) $item['url'],
                        PHP_URL_QUERY
                    ) ?: ''
                ),
                $itemQuery
            );

            if (
                $itemPath === $requestedPath
                && (string) (
                    $itemQuery['section'] ?? ''
                ) === $section
            ) {
                $sectionAllowed = true;
                break;
            }
        }

        if (!$sectionAllowed) {
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
                'page' => (
                    new \App\Services\CommunicationSettingsService()
                )->page(
                    (int) $context['user_id'],
                    $section
                ),
            ]
        );
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
