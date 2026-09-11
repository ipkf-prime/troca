<?php

declare(strict_types=1);


$uiContentRedirect =
    static function (
        $response,
        array $query = []
    ) {
        $url =
            '/admin/system/help-texts';

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

        return
            $response->redirect(
                $url
            );
    };


$uiContentCsrfValid =
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
    '/admin/system/help-texts',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/system/help-texts'
            );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $page =
                (
                    new \App\Services\UiContent\UiContentManagementService()
                )->page([
                    'q' =>
                        $request->input(
                            'q',
                            ''
                        ),

                    'type' =>
                        $request->input(
                            'content_type',
                            ''
                        ),

                    'status' =>
                        $request->input(
                            'content_status',
                            ''
                        ),

                    'browse_mode' =>
                        $request->input(
                            'browse',
                            'content'
                        ),

                    'module' =>
                        $request->input(
                            'module',
                            ''
                        ),

                    'placement' =>
                        $request->input(
                            'placement',
                            ''
                        ),

                    'page' =>
                        $request->input(
                            'page',
                            '1'
                        ),

                    'tab' =>
                        $request->input(
                            'tab',
                            ''
                        ),

                    'key' =>
                        $request->input(
                            'key',
                            ''
                        ),

                    'override' =>
                        $request->input(
                            'override',
                            ''
                        ),

                    'new_definition' =>
                        (string) $request->input(
                            'new',
                            ''
                        )
                        === '1',

                    'new_override' =>
                        (string) $request->input(
                            'new_override',
                            ''
                        )
                        === '1',
                ]);
        } catch (\Throwable) {
            return
                $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'مدیریت راهنما، اعلان و خطا',

                        'context' =>
                            $context,

                        'message' =>
                            'مخزن محتوای پویا در حال حاضر قابل دسترسی نیست.',
                    ],
                    503
                );
        }

        return
            $adminRender(
                $response,
                'help-texts',
                [
                    'title' =>
                        'مدیریت راهنما، اعلان و خطا',

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
                ]
            );
    }
);


$router->post(
    '/admin/system/help-texts/definition/save',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $uiContentRedirect,
        $uiContentCsrfValid
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/system/help-texts/definition/save'
            );

        if (!is_array($context)) {
            return $context;
        }

        $contentKey =
            trim(
                (string) $request->input(
                    'content_key',
                    ''
                )
            );

        if (!$uiContentCsrfValid(
            $request
        )) {
            return
                $uiContentRedirect(
                    $response,
                    [
                        'status' =>
                            'invalid_form',

                        'key' =>
                            $contentKey,
                    ]
                );
        }

        try {
            $result =
                (
                    new \App\Services\UiContent\UiContentManagementService()
                )->saveDefinition(
                    (int) $context['user_id'],
                    $request->all()
                );

            return
                $uiContentRedirect(
                    $response,
                    [
                        'status' =>
                            'definition_saved',

                        'key' =>
                            $result[
                                'content_key'
                            ],
                    ]
                );
        } catch (\Throwable) {
            return
                $uiContentRedirect(
                    $response,
                    [
                        'status' =>
                            'save_failed',

                        'key' =>
                            $contentKey,
                    ]
                );
        }
    }
);


$router->post(
    '/admin/system/help-texts/override/save',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $uiContentRedirect,
        $uiContentCsrfValid
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/system/help-texts/override/save'
            );

        if (!is_array($context)) {
            return $context;
        }

        $contentKey =
            trim(
                (string) $request->input(
                    'definition_key',
                    ''
                )
            );

        if (!$uiContentCsrfValid(
            $request
        )) {
            return
                $uiContentRedirect(
                    $response,
                    [
                        'status' =>
                            'invalid_form',

                        'key' =>
                            $contentKey,
                    ]
                );
        }

        try {
            $result =
                (
                    new \App\Services\UiContent\UiContentManagementService()
                )->saveOverride(
                    (int) $context['user_id'],
                    $request->all()
                );

            return
                $uiContentRedirect(
                    $response,
                    [
                        'status' =>
                            'override_saved',

                        'key' =>
                            $result[
                                'content_key'
                            ],

                        'override' =>
                            $result[
                                'override_reference'
                            ],
                    ]
                );
        } catch (\Throwable) {
            return
                $uiContentRedirect(
                    $response,
                    [
                        'status' =>
                            'save_failed',

                        'key' =>
                            $contentKey,
                    ]
                );
        }
    }
);
