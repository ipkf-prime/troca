<?php

declare(strict_types=1);

$templateAccess =
    static function (
        array $context,
        string $method,
        string $path
    ): bool {
        return (
            new \App\Services\DynamicRouteAccessService()
        )->can(
            (int) $context['user_id'],
            $method,
            $path
        );
    };

$templateRedirect =
    static function (
        $response,
        array $query = []
    ) {
        $url =
            '/admin/communications/templates';

        if ($query !== []) {
            $url .=
                '?'
                . http_build_query(
                    $query,
                    '',
                    '&',
                    PHP_QUERY_RFC3986
                );
        }

        return $response->redirect(
            $url
        );
    };

$templateInput =
    static function (
        $request
    ): array {
        return [
            'code' =>
                trim(
                    (string) $request->input(
                        'code',
                        ''
                    )
                ),

            'channel_code' =>
                trim(
                    (string) $request->input(
                        'channel_code',
                        ''
                    )
                ),

            'locale' =>
                trim(
                    (string) $request->input(
                        'locale',
                        'fa'
                    )
                ),

            'title_template' =>
                (string) $request->input(
                    'title_template',
                    ''
                ),

            'body_template' =>
                (string) $request->input(
                    'body_template',
                    ''
                ),

            'action_url_template' =>
                (string) $request->input(
                    'action_url_template',
                    ''
                ),

            'is_active' =>
                (string) $request->input(
                    'is_active',
                    ''
                ),

            'destination' =>
                trim(
                    (string) $request->input(
                        'destination',
                        ''
                    )
                ),
        ];
    };

$templateCsrfValid =
    static function (
        $request
    ): bool {
        return (
            new \IPKF\Security\Csrf()
        )->check(
            (string) $request->input(
                '_token',
                ''
            )
        );
    };

$router->get(
    '/admin/communications/templates',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $templateAccess
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/communications/templates'
            );

        if (!is_array($context)) {
            return $context;
        }

        if (!$templateAccess(
            $context,
            'GET',
            '/admin/communications/templates'
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $service =
            new \App\Services\NotificationTemplateManagementService();

        $page =
            $service->page([
                'q' =>
                    $request->input(
                        'q',
                        ''
                    ),

                'channel' =>
                    $request->input(
                        'channel',
                        ''
                    ),

                'status' =>
                    $request->input(
                        'template_status',
                        ''
                    ),

                'code' =>
                    $request->input(
                        'code',
                        ''
                    ),

                'selected_channel' =>
                    $request->input(
                        'selected_channel',
                        ''
                    ),

                'locale' =>
                    $request->input(
                        'locale',
                        'fa'
                    ),
            ]);

        $canTestSend =
            (
                new \App\Services\AuthorizationService()
            )->hasPermission(
                (int) $context['user_id'],
                'notifications.send.manage'
            );

        return $adminRender(
            $response,
            'message-templates',
            [
                'title' =>
                    'متن‌های پیام',

                'context' =>
                    $context,

                'page' =>
                    $page,

                'status' =>
                    trim(
                        (string) $request->input(
                            'status',
                            ''
                        )
                    ),

                'canTestSend' =>
                    $canTestSend,
            ]
        );
    }
);

$router->post(
    '/admin/communications/templates/save',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $templateAccess,
        $templateRedirect,
        $templateInput,
        $templateCsrfValid
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/communications/templates/save'
            );

        if (!is_array($context)) {
            return $context;
        }

        if (!$templateAccess(
            $context,
            'POST',
            '/admin/communications/templates/save'
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $input =
            $templateInput(
                $request
            );

        if (!$templateCsrfValid(
            $request
        )) {
            return $templateRedirect(
                $response,
                [
                    'status' =>
                        'invalid_form',

                    'code' =>
                        $input['code'],

                    'selected_channel' =>
                        $input[
                            'channel_code'
                        ],

                    'locale' =>
                        $input['locale'],
                ]
            );
        }

        try {
            $result =
                (
                    new \App\Services\NotificationTemplateManagementService()
                )->saveVersion(
                    (int) $context['user_id'],
                    $input
                );

            return $templateRedirect(
                $response,
                [
                    'status' =>
                        'saved',

                    'code' =>
                        $result['code'],

                    'selected_channel' =>
                        $result['channel'],

                    'locale' =>
                        $result['locale'],

                    'version' =>
                        $result['version'],
                ]
            );
        } catch (\Throwable) {
            return $templateRedirect(
                $response,
                [
                    'status' =>
                        'save_failed',

                    'code' =>
                        $input['code'],

                    'selected_channel' =>
                        $input[
                            'channel_code'
                        ],

                    'locale' =>
                        $input['locale'],
                ]
            );
        }
    }
);

$router->post(
    '/admin/communications/templates/preview',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $templateAccess,
        $templateInput,
        $templateCsrfValid
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/communications/templates/preview'
            );

        if (!is_array($context)) {
            return $context;
        }

        if (!$templateAccess(
            $context,
            'POST',
            '/admin/communications/templates/preview'
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $input =
            $templateInput(
                $request
            );

        $service =
            new \App\Services\NotificationTemplateManagementService();

        $page =
            $service->page([
                'code' =>
                    $input['code'],

                'selected_channel' =>
                    $input[
                        'channel_code'
                    ],

                'locale' =>
                    $input['locale'],
            ]);

        $page['draft'] =
            $input;

        $status =
            'preview_ready';

        if (!$templateCsrfValid(
            $request
        )) {
            $status =
                'invalid_form';
        } else {
            try {
                $page['preview'] =
                    $service
                        ->previewDraft(
                            $input
                        );
            } catch (\Throwable) {
                $status =
                    'preview_failed';
            }
        }

        $canTestSend =
            (
                new \App\Services\AuthorizationService()
            )->hasPermission(
                (int) $context['user_id'],
                'notifications.send.manage'
            );

        return $adminRender(
            $response,
            'message-templates',
            [
                'title' =>
                    'متن‌های پیام',

                'context' =>
                    $context,

                'page' =>
                    $page,

                'status' =>
                    $status,

                'canTestSend' =>
                    $canTestSend,
            ]
        );
    }
);

$router->post(
    '/admin/communications/templates/test-send',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $templateAccess,
        $templateRedirect,
        $templateInput,
        $templateCsrfValid
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/communications/templates/test-send'
            );

        if (!is_array($context)) {
            return $context;
        }

        if (!$templateAccess(
            $context,
            'POST',
            '/admin/communications/templates/test-send'
        )) {
            return $response->redirect(
                '/admin/dashboard?error=forbidden'
            );
        }

        $input =
            $templateInput(
                $request
            );

        $query = [
            'code' =>
                $input['code'],

            'selected_channel' =>
                $input[
                    'channel_code'
                ],

            'locale' =>
                $input['locale'],
        ];

        if (!$templateCsrfValid(
            $request
        )) {
            $query['status'] =
                'invalid_form';

            return $templateRedirect(
                $response,
                $query
            );
        }

        try {
            (
                new \App\Services\NotificationTemplateManagementService()
            )->testSend(
                (int) $context['user_id'],
                $input
            );

            $query['status'] =
                'test_sent';
        } catch (\Throwable) {
            $query['status'] =
                'test_failed';
        }

        return $templateRedirect(
            $response,
            $query
        );
    }
);
