<?php

$router->get('/admin/notifications', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard(
        $response,
        '/admin/notifications'
    );

    if (!is_array($context)) {
        return $context;
    }

    $page = (new \App\Services\NotificationInboxService())
        ->page(
            (int) $context['user_id'],
            [
                'filter' => $request->input(
                    'filter',
                    'all'
                ),
                'page' => $request->input('page', 1),
            ]
        );

    return $adminRender($response, 'notifications', [
        'title' => 'اعلان‌های من',
        'context' => $context,
        'page' => $page,
        'status' => trim(
            (string) $request->input('status', '')
        ),
    ]);
});

$router->post(
    '/admin/notifications/{reference}/read',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/notifications'
        );

        if (!is_array($context)) {
            return $context;
        }

        $reference = trim(
            (string) $request->route('reference')
        );

        (new \App\Services\NotificationInboxService())
            ->markRead(
                (int) $context['user_id'],
                $reference
            );

        $returnTo = trim(
            (string) $request->input(
                'return_to',
                '/admin/notifications'
            )
        );

        if (
            $returnTo === ''
            || !str_starts_with(
                $returnTo,
                '/admin/notifications'
            )
        ) {
            $returnTo = '/admin/notifications';
        }

        return $response->redirect(
            $returnTo
            . (str_contains($returnTo, '?') ? '&' : '?')
            . 'status=read'
        );
    }
);

$router->post(
    '/admin/notifications/read-all',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/notifications'
        );

        if (!is_array($context)) {
            return $context;
        }

        (new \App\Services\NotificationInboxService())
            ->markAllRead((int) $context['user_id']);

        return $response->redirect(
            '/admin/notifications?status=all_read'
        );
    }
);
