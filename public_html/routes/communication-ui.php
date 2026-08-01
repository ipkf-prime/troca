<?php

$router->get(
    '/admin/communications/settings',
    function (
        $request,
        $response
    ) use ($adminRender, $adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $service =
            new \App\Services\CommunicationSettingsService();
        $section = trim((string) $request->input(
            'section',
            'providers'
        ));
        $page = $service->page(
            (int) $context['user_id'],
            $section
        );

        if (($page['allowed'] ?? false) !== true) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        return $adminRender(
            $response,
            'communication-settings',
            [
                'title' => 'تنظیمات پیام و اعلان',
                'context' => $context,
                'page' => $page,
                'status' => trim((string) $request->input(
                    'status',
                    ''
                )),
            ]
        );
    }
);

$router->post(
    '/admin/communications/settings/preferences',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/communications/settings'
        );

        if (!is_array($context)) {
            return $context;
        }

        $service =
            new \App\Services\CommunicationSettingsService();
        $sections = $service->allowedSections(
            (int) $context['user_id']
        );

        if (!isset($sections['preferences'])) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $service->savePreferences(
            (int) $context['user_id'],
            $request->input('channels', [])
        );

        return $response->redirect(
            '/admin/communications/settings'
            . '?section=preferences&status=saved'
        );
    }
);
