<?php

declare(strict_types=1);


$ticketingRuntimeReport = static function (
    \Throwable $exception,
    string $operation,
    array $context = []
): string {
    $incident = strtoupper(
        substr(
            hash(
                'sha256',
                uniqid('', true)
                . '|'
                . (string) getmypid()
                . '|'
                . $operation
            ),
            0,
            12
        )
    );

    $payload = [
        'incident' => $incident,
        'occurred_at' => gmdate('c'),
        'operation' => $operation,
        'exception' =>
            get_class($exception),
        'message' =>
            $exception->getMessage(),
        'file' =>
            $exception->getFile(),
        'line' =>
            $exception->getLine(),
        'context' => $context,
        'trace' =>
            $exception->getTraceAsString(),
    ];

    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    if (is_string($json)) {
        error_log(
            'IPKF_TICKETING_RUNTIME '
            . $json
        );

        $directory =
            BASE_PATH
            . '/storage/logs';

        if (
            is_dir($directory)
            || @mkdir(
                $directory,
                0770,
                true
            )
        ) {
            @file_put_contents(
                $directory
                . '/ticketing-runtime.log',
                $json . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        }
    }

    return $incident;
};


/*
 * ---------------------------------------------------------
 * Dashboard
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context = $adminGuard(
            $response,
            '/admin/ticketing'
        );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $dashboard = (
                new \App\Services\Ticketing\TicketService()
            )->dashboardForUser(
                (int) $context['user_id']
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'dashboard',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'host' =>
                            (string) $request->host(),

                        'uri' =>
                            (string) $request->uri(),
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'پشتیبانی و تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'داشبورد تیکتینگ در حال حاضر '
                        . 'در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'ticketing-dashboard',
            [
                'title' =>
                    'پشتیبانی و تیکتینگ',

                'context' =>
                    $context,

                'dashboard' =>
                    $dashboard,

                /*
                 * Keep the module permission contract
                 * explicit for the shell regression.
                 */
                'foundation' => [
                    'application' =>
                        'ticketing',

                    'permission' =>
                        'ticketing.ticket.view',

                    'runtime' =>
                        'operational',
                ],
            ]
        );
    }
);


/*
 * ---------------------------------------------------------
 * Support Project Administration
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/projects',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $filters = [
            'q' =>
                trim(
                    (string) $request->input(
                        'q',
                        ''
                    )
                ),

            'status' =>
                trim(
                    (string) $request->input(
                        'status',
                        ''
                    )
                ),
        ];

        try {
            $list =
                (
                    new \App\Services\Ticketing\SupportProjectAdminService()
                )->index($filters);

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_project_index',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'filters' =>
                            $filters,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'پروژه‌های پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'فهرست پروژه‌های پشتیبانی در دسترس نیست. '
                        . 'کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'ticketing-projects',
            [
                'title' =>
                    'پروژه‌های پشتیبانی',

                'context' =>
                    $context,

                'list' =>
                    $list,
            ]
        );
    }
);


$router->get(
    '/admin/ticketing/projects/create',
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
                '/admin/ticketing/projects/create'
            );

        if (!is_array($context)) {
            return $context;
        }

        $page =
            (
                new \App\Services\Ticketing\SupportProjectAdminService()
            )->createForm();

        return $adminRender(
            $response,
            'ticketing-project-form',
            [
                'title' =>
                    'پروژه پشتیبانی جدید',

                'context' =>
                    $context,

                'mode' =>
                    $page['mode'],

                'project' =>
                    $page['project'],

                'form' =>
                    $page['form'],

                'icon_options' =>
                    $page['icon_options'],

                'errors' =>
                    [],
            ]
        );
    }
);


$router->post(
    '/admin/ticketing/projects',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $csrf =
            new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ثبت پروژه پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
                ],
                419
            );
        }

        $service =
            new \App\Services\Ticketing\SupportProjectAdminService();

        $input = [
            'code' =>
                $request->input(
                    'code',
                    ''
                ),

            'title' =>
                $request->input(
                    'title',
                    ''
                ),

            'description' =>
                $request->input(
                    'description',
                    ''
                ),

            'icon_code' =>
                $request->input(
                    'icon_code',
                    'sitemap'
                ),

            'color_code' =>
                $request->input(
                    'color_code',
                    '#258843'
                ),

            'sort_order' =>
                $request->input(
                    'sort_order',
                    10
                ),

            'is_active' =>
                $request->input(
                    'is_active',
                    0
                ),
        ];

        try {
            $result =
                $service->create(
                    $input,
                    (int) $context['user_id']
                );

            if (empty($result['ok'])) {

                $page =
                    $service->createForm(
                        $result['form']
                        ?? []
                    );

                return $adminRender(
                    $response,
                    'ticketing-project-form',
                    [
                        'title' =>
                            'پروژه پشتیبانی جدید',

                        'context' =>
                            $context,

                        'mode' =>
                            $page['mode'],

                        'project' =>
                            $page['project'],

                        'form' =>
                            $page['form'],

                        'icon_options' =>
                            $page['icon_options'],

                        'errors' =>
                            $result['errors']
                            ?? [],
                    ],
                    422
                );
            }

            return $response->redirect(
                '/admin/ticketing/projects/'
                . rawurlencode(
                    (string) $result[
                        'public_reference'
                    ]
                )
                . '/edit?status=created'
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_project_create',
                    [
                        'user_id' =>
                            (int) $context['user_id'],
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ثبت پروژه پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'ثبت پروژه انجام نشد. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);


$router->get(
    '/admin/ticketing/projects/{public_reference}/edit',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard
    ) {
        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $path =
            '/admin/ticketing/projects/'
            . $reference
            . '/edit';

        $context =
            $adminGuard(
                $response,
                $path
            );

        if (!is_array($context)) {
            return $context;
        }

        $page =
            (
                new \App\Services\Ticketing\SupportProjectAdminService()
            )->editForm(
                $reference
            );

        if ($page === null) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'پروژه پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'پروژه مورد نظر پیدا نشد.',
                ],
                404
            );
        }

        return $adminRender(
            $response,
            'ticketing-project-form',
            [
                'title' =>
                    'ویرایش پروژه پشتیبانی',

                'context' =>
                    $context,

                'mode' =>
                    $page['mode'],

                'project' =>
                    $page['project'],

                'form' =>
                    $page['form'],

                'icon_options' =>
                    $page['icon_options'],

                'errors' =>
                    [],
            ]
        );
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $path =
            '/admin/ticketing/projects/'
            . $reference;

        $context =
            $adminGuard(
                $response,
                $path
            );

        if (!is_array($context)) {
            return $context;
        }

        $csrf =
            new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ویرایش پروژه پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
                ],
                419
            );
        }

        $service =
            new \App\Services\Ticketing\SupportProjectAdminService();

        $input = [
            'title' =>
                $request->input(
                    'title',
                    ''
                ),

            'description' =>
                $request->input(
                    'description',
                    ''
                ),

            'icon_code' =>
                $request->input(
                    'icon_code',
                    'sitemap'
                ),

            'color_code' =>
                $request->input(
                    'color_code',
                    '#258843'
                ),

            'sort_order' =>
                $request->input(
                    'sort_order',
                    10
                ),

            'is_active' =>
                $request->input(
                    'is_active',
                    0
                ),
        ];

        try {
            $result =
                $service->update(
                    $reference,
                    $input
                );

            if (!empty($result['not_found'])) {
                return $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'پروژه پشتیبانی',

                        'context' =>
                            $context,

                        'message' =>
                            'پروژه مورد نظر پیدا نشد.',
                    ],
                    404
                );
            }

            if (empty($result['ok'])) {

                $page =
                    $service->editForm(
                        $reference,
                        $result['form']
                        ?? []
                    );

                if ($page === null) {
                    return $adminRender(
                        $response,
                        'placeholder',
                        [
                            'title' =>
                                'پروژه پشتیبانی',

                            'context' =>
                                $context,

                            'message' =>
                                'پروژه مورد نظر پیدا نشد.',
                        ],
                        404
                    );
                }

                return $adminRender(
                    $response,
                    'ticketing-project-form',
                    [
                        'title' =>
                            'ویرایش پروژه پشتیبانی',

                        'context' =>
                            $context,

                        'mode' =>
                            $page['mode'],

                        'project' =>
                            $page['project'],

                        'form' =>
                            $page['form'],

                        'icon_options' =>
                            $page['icon_options'],

                        'errors' =>
                            $result['errors']
                            ?? [],
                    ],
                    422
                );
            }

            return $response->redirect(
                '/admin/ticketing/projects/'
                . rawurlencode($reference)
                . '/edit?status=updated'
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_project_update',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'project_reference' =>
                            $reference,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ویرایش پروژه پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'ویرایش پروژه انجام نشد. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);


/*
 * ---------------------------------------------------------
 * Participant Directory
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/participants',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/participants'
            );

        if (!is_array($context)) {
            return $context;
        }

        $filters = [
            'q' =>
                trim(
                    (string) $request->input(
                        'q',
                        ''
                    )
                ),

            'origin' =>
                trim(
                    (string) $request->input(
                        'origin',
                        ''
                    )
                ),

            'state' =>
                trim(
                    (string) $request->input(
                        'state',
                        ''
                    )
                ),

            'core_q' =>
                trim(
                    (string) $request->input(
                        'core_q',
                        ''
                    )
                ),
        ];

        try {

            $directory =
                (
                    new \App\Services\Ticketing\ParticipantDirectoryService()
                )->page(
                    $filters
                );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'participant_directory_index',
                    [
                        'user_id' =>
                            (int) $context['user_id'],
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'مخاطبان تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'فهرست مخاطبان در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        $status =
            trim(
                (string) $request->input(
                    'status',
                    ''
                )
            );

        $noticeMap = [
            'core-added' =>
                'کاربر سامانه به فهرست مخاطبان تیکتینگ اضافه شد.',

            'manual-added' =>
                'مخاطب جدید با موفقیت ثبت شد.',
        ];

        return $adminRender(
            $response,
            'ticketing-participants',
            [
                'title' =>
                    'مخاطبان تیکتینگ',

                'context' =>
                    $context,

                'directory' =>
                    $directory,

                'errors' =>
                    [],

                'manual_form' =>
                    [],

                'notice' =>
                    $noticeMap[$status]
                    ?? '',
            ]
        );
    }
);


$router->post(
    '/admin/ticketing/participants/core',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/participants/core'
            );

        if (!is_array($context)) {
            return $context;
        }

        $csrf =
            new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'مخاطبان تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
                ],
                419
            );
        }

        $service =
            new \App\Services\Ticketing\ParticipantDirectoryService();

        try {

            $result =
                $service->addCoreUser(
                    (int) $request->input(
                        'user_id',
                        0
                    ),
                    (int) $context['user_id']
                );

            if (empty($result['ok'])) {

                $directory =
                    $service->page();

                return $adminRender(
                    $response,
                    'ticketing-participants',
                    [
                        'title' =>
                            'مخاطبان تیکتینگ',

                        'context' =>
                            $context,

                        'directory' =>
                            $directory,

                        'errors' => [
                            (string) (
                                $result['error']
                                ?? 'افزودن کاربر انجام نشد.'
                            ),
                        ],

                        'manual_form' =>
                            [],

                        'notice' =>
                            '',
                    ],
                    422
                );
            }

            return $response->redirect(
                '/admin/ticketing/participants'
                . '?status=core-added'
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'participant_core_add',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'target_user_id' =>
                            (int) $request->input(
                                'user_id',
                                0
                            ),
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'افزودن کاربر تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'افزودن کاربر انجام نشد. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);


$router->post(
    '/admin/ticketing/participants/manual',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/participants/manual'
            );

        if (!is_array($context)) {
            return $context;
        }

        $csrf =
            new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'مخاطبان تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
                ],
                419
            );
        }

        $input = [
            'full_name' =>
                $request->input(
                    'full_name',
                    ''
                ),

            'email' =>
                $request->input(
                    'email',
                    ''
                ),

            'mobile' =>
                $request->input(
                    'mobile',
                    ''
                ),

            'organization_name' =>
                $request->input(
                    'organization_name',
                    ''
                ),

            'external_reference' =>
                $request->input(
                    'external_reference',
                    ''
                ),
        ];

        $service =
            new \App\Services\Ticketing\ParticipantDirectoryService();

        try {

            $result =
                $service->addManual(
                    $input,
                    (int) $context['user_id']
                );

            if (empty($result['ok'])) {

                $directory =
                    $service->page();

                return $adminRender(
                    $response,
                    'ticketing-participants',
                    [
                        'title' =>
                            'مخاطبان تیکتینگ',

                        'context' =>
                            $context,

                        'directory' =>
                            $directory,

                        'errors' =>
                            array_values(
                                $result['errors']
                                ?? [
                                    'ثبت مخاطب انجام نشد.',
                                ]
                            ),

                        'manual_form' =>
                            $result['form']
                            ?? $input,

                        'notice' =>
                            '',
                    ],
                    422
                );
            }

            return $response->redirect(
                '/admin/ticketing/participants'
                . '?status=manual-added'
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'participant_manual_add',
                    [
                        'user_id' =>
                            (int) $context['user_id'],
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ثبت مخاطب تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'ثبت مخاطب انجام نشد. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);



/*
 * ---------------------------------------------------------
 * Support Topology Administration
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/projects/{public_reference}/topology',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $path =
            '/admin/ticketing/projects/'
            . $reference
            . '/topology';

        $context =
            $adminGuard(
                $response,
                $path
            );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $page =
                (
                    new \App\Services\Ticketing\SupportTopologyAdminService()
                )->page(
                    $reference
                );

            if ($page === null) {
                return $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'ساختار پشتیبانی',

                        'context' =>
                            $context,

                        'message' =>
                            'پروژه مورد نظر پیدا نشد.',
                    ],
                    404
                );
            }

            return $adminRender(
                $response,
                'ticketing-topology',
                [
                    'title' =>
                        'ساختار پشتیبانی',

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

                    'errors' =>
                        [],
                ]
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_topology_index',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'project_reference' =>
                            $reference,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ساختار پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'ساختار پشتیبانی در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/topology',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $path =
            '/admin/ticketing/projects/'
            . $reference
            . '/topology';

        $context =
            $adminGuard(
                $response,
                $path
            );

        if (!is_array($context)) {
            return $context;
        }

        $csrf =
            new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ساختار پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'اعتبار فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.',
                ],
                419
            );
        }

        $action =
            trim(
                (string) $request->input(
                    'action',
                    ''
                )
            );

        $input = [
            'code' =>
                $request->input('code', ''),

            'title' =>
                $request->input('title', ''),

            'description' =>
                $request->input('description', ''),

            'rank_order' =>
                $request->input('rank_order', 0),

            'layer_id' =>
                $request->input('layer_id', 0),

            'parent_node_id' =>
                $request->input('parent_node_id', 0),

            'child_node_id' =>
                $request->input('child_node_id', 0),

            'node_id' =>
                $request->input('node_id', 0),

            'team_id' =>
                $request->input('team_id', 0),

            'queue_id' =>
                $request->input('queue_id', 0),

            'project_member_id' =>
                $request->input('project_member_id', 0),

            'staff_role_code' =>
                $request->input(
                    'staff_role_code',
                    'agent'
                ),

            'workload_weight' =>
                $request->input(
                    'workload_weight',
                    1
                ),

            'assignment_mode_code' =>
                $request->input(
                    'assignment_mode_code',
                    'manual'
                ),

            'max_open_per_agent' =>
                $request->input(
                    'max_open_per_agent',
                    ''
                ),

            'core_organization_reference' =>
                $request->input(
                    'core_organization_reference',
                    ''
                ),

            'scope_type_code' =>
                $request->input(
                    'scope_type_code',
                    ''
                ),

            'scope_reference' =>
                $request->input(
                    'scope_reference',
                    ''
                ),

            'sort_order' =>
                $request->input(
                    'sort_order',
                    0
                ),

            'can_observe_descendants' =>
                $request->input(
                    'can_observe_descendants',
                    0
                ),

            'can_assist_descendants' =>
                $request->input(
                    'can_assist_descendants',
                    0
                ),

            'can_takeover_descendants' =>
                $request->input(
                    'can_takeover_descendants',
                    0
                ),

            'can_transfer_downward' =>
                $request->input(
                    'can_transfer_downward',
                    0
                ),

            'is_entry_layer' =>
                $request->input(
                    'is_entry_layer',
                    0
                ),

            'is_terminal_layer' =>
                $request->input(
                    'is_terminal_layer',
                    0
                ),

            'is_intake_node' =>
                $request->input(
                    'is_intake_node',
                    0
                ),

            'is_primary_path' =>
                $request->input(
                    'is_primary_path',
                    0
                ),

            'allow_escalation' =>
                $request->input(
                    'allow_escalation',
                    0
                ),

            'allow_downward_transfer' =>
                $request->input(
                    'allow_downward_transfer',
                    0
                ),

            'is_default' =>
                $request->input(
                    'is_default',
                    0
                ),
        ];

        try {
            $service =
                new \App\Services\Ticketing\SupportTopologyAdminService();

            $result =
                $service->mutate(
                    $reference,
                    $action,
                    $input
                );

            if (!empty($result['not_found'])) {
                return $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'ساختار پشتیبانی',

                        'context' =>
                            $context,

                        'message' =>
                            'پروژه مورد نظر پیدا نشد.',
                    ],
                    404
                );
            }

            if (empty($result['ok'])) {

                $page =
                    $service->page(
                        $reference
                    );

                return $adminRender(
                    $response,
                    'ticketing-topology',
                    [
                        'title' =>
                            'ساختار پشتیبانی',

                        'context' =>
                            $context,

                        'page' =>
                            $page ?? [],

                        'status' =>
                            '',

                        'errors' =>
                            $result['errors']
                            ?? [
                                'عملیات انجام نشد.',
                            ],
                    ],
                    422
                );
            }

            return $response->redirect(
                '/admin/ticketing/projects/'
                . rawurlencode($reference)
                . '/topology?status='
                . rawurlencode(
                    (string) (
                        $result['status']
                        ?? 'updated'
                    )
                )
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_topology_mutate',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'project_reference' =>
                            $reference,

                        'action' =>
                            $action,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ساختار پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'عملیات ساختار پشتیبانی انجام نشد. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);



/*
 * ---------------------------------------------------------
 * Topic / Routing Administration
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/projects/{public_reference}/routing',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $path =
            '/admin/ticketing/projects/'
            . $reference
            . '/routing';

        $context =
            $adminGuard(
                $response,
                $path
            );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $page =
                (
                    new \App\Services\Ticketing\SupportTopicRoutingAdminService()
                )->page(
                    $reference
                );

            if ($page === null) {
                return $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'موضوعات و مسیریابی',

                        'context' =>
                            $context,

                        'message' =>
                            'پروژه مورد نظر پیدا نشد.',
                    ],
                    404
                );
            }

            return $adminRender(
                $response,
                'ticketing-routing',
                [
                    'title' =>
                        'موضوعات و مسیریابی',

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

                    'errors' =>
                        [],
                ]
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_routing_index',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'project_reference' =>
                            $reference,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'موضوعات و مسیریابی',

                    'context' =>
                        $context,

                    'message' =>
                        'تنظیمات مسیریابی در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/routing',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $path =
            '/admin/ticketing/projects/'
            . $reference
            . '/routing';

        $context =
            $adminGuard(
                $response,
                $path
            );

        if (!is_array($context)) {
            return $context;
        }

        $csrf =
            new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'موضوعات و مسیریابی',

                    'context' =>
                        $context,

                    'message' =>
                        'اعتبار فرم منقضی شده است.',
                ],
                419
            );
        }

        $action =
            trim(
                (string) $request->input(
                    'action',
                    ''
                )
            );

        $input = [
            'title' =>
                $request->input(
                    'title',
                    ''
                ),

            'code' =>
                $request->input(
                    'code',
                    ''
                ),

            'description' =>
                $request->input(
                    'description',
                    ''
                ),

            'service_id' =>
                $request->input(
                    'service_id',
                    0
                ),

            'parent_topic_id' =>
                $request->input(
                    'parent_topic_id',
                    0
                ),

            'is_selectable' =>
                $request->input(
                    'is_selectable',
                    0
                ),

            'is_default' =>
                $request->input(
                    'is_default',
                    0
                ),

            'topic_id' =>
                $request->input(
                    'topic_id',
                    0
                ),

            'scope_type_code' =>
                $request->input(
                    'scope_type_code',
                    'all'
                ),

            'scope_reference' =>
                $request->input(
                    'scope_reference',
                    ''
                ),

            'target_layer_id' =>
                $request->input(
                    'target_layer_id',
                    0
                ),

            'target_node_id' =>
                $request->input(
                    'target_node_id',
                    0
                ),

            'target_queue_id' =>
                $request->input(
                    'target_queue_id',
                    0
                ),

            'target_team_id' =>
                $request->input(
                    'target_team_id',
                    0
                ),

            'fixed_project_member_id' =>
                $request->input(
                    'fixed_project_member_id',
                    0
                ),

            'assignment_mode_code' =>
                $request->input(
                    'assignment_mode_code',
                    'inherit'
                ),

            'priority' =>
                $request->input(
                    'priority',
                    100
                ),

            'sort_order' =>
                $request->input(
                    'sort_order',
                    0
                ),
        ];

        try {
            $service =
                new \App\Services\Ticketing\SupportTopicRoutingAdminService();

            $result =
                $service->mutate(
                    $reference,
                    $action,
                    $input
                );

            if (!empty($result['not_found'])) {
                return $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'موضوعات و مسیریابی',

                        'context' =>
                            $context,

                        'message' =>
                            'پروژه مورد نظر پیدا نشد.',
                    ],
                    404
                );
            }

            if (empty($result['ok'])) {

                $page =
                    $service->page(
                        $reference
                    );

                return $adminRender(
                    $response,
                    'ticketing-routing',
                    [
                        'title' =>
                            'موضوعات و مسیریابی',

                        'context' =>
                            $context,

                        'page' =>
                            $page ?? [],

                        'status' =>
                            '',

                        'errors' =>
                            $result['errors']
                            ?? [
                                'عملیات انجام نشد.',
                            ],
                    ],
                    422
                );
            }

            return $response->redirect(
                '/admin/ticketing/projects/'
                . rawurlencode($reference)
                . '/routing?status='
                . rawurlencode(
                    (string) (
                        $result['status']
                        ?? 'updated'
                    )
                )
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_routing_mutate',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'project_reference' =>
                            $reference,

                        'action' =>
                            $action,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'موضوعات و مسیریابی',

                    'context' =>
                        $context,

                    'message' =>
                        'عملیات مسیریابی انجام نشد. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);


/*
 * ---------------------------------------------------------
 * My Tickets
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/tickets',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context = $adminGuard(
            $response,
            '/admin/ticketing/tickets'
        );

        if (!is_array($context)) {
            return $context;
        }

        $filters = [
            'q' =>
                $request->input(
                    'q',
                    ''
                ),

            'status' =>
                $request->input(
                    'status',
                    ''
                ),

            'priority' =>
                $request->input(
                    'priority',
                    ''
                ),

            'project_reference' =>
                $request->input(
                    'project',
                    ''
                ),

            'layer_id' =>
                $request->input(
                    'layer',
                    0
                ),

            'assignee_id' =>
                $request->input(
                    'assignee',
                    0
                ),

            'sort1' =>
                $request->input(
                    'sort1',
                    'last_activity'
                ),

            'dir1' =>
                $request->input(
                    'dir1',
                    'desc'
                ),

            'sort2' =>
                $request->input(
                    'sort2',
                    'created_at'
                ),

            'dir2' =>
                $request->input(
                    'dir2',
                    'desc'
                ),
        ];

        try {
            $list = (
                new \App\Services\Ticketing\TicketService()
            )->myTickets(
                (int) $context['user_id'],
                $filters
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_index',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'filters' =>
                            $filters,

                        'uri' =>
                            (string) $request->uri(),
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'تیکت‌های من',

                    'context' =>
                        $context,

                    'message' =>
                        'فهرست تیکت‌ها در حال حاضر '
                        . 'در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'ticketing-tickets',
            [
                'title' =>
                    'تیکت‌های من',

                'context' =>
                    $context,

                'list' =>
                    $list,
            ]
        );
    }
);


/*
 * ---------------------------------------------------------
 * Create form
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/tickets/create',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context = $adminGuard(
            $response,
            '/admin/ticketing/tickets/create'
        );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $page = (
                new \App\Services\Ticketing\TicketService()
            )->form(
                [],
                (int) $context['user_id']
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_create_form',
                    [
                        'user_id' =>
                            (int) $context['user_id'],
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'تیکت جدید',

                    'context' =>
                        $context,

                    'message' =>
                        'فرم ثبت تیکت در حال حاضر '
                        . 'در دسترس نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'ticketing-ticket-form',
            [
                'title' =>
                    'تیکت جدید',

                'context' =>
                    $context,

                'form' =>
                    $page['form'],

                'options' =>
                    $page['options'],

                'errors' => [],
            ]
        );
    }
);


/*
 * ---------------------------------------------------------
 * Create
 * ---------------------------------------------------------
 */
$router->post(
    '/admin/ticketing/tickets',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context = $adminGuard(
            $response,
            '/admin/ticketing/tickets'
        );

        if (!is_array($context)) {
            return $context;
        }

        $service =
            new \App\Services\Ticketing\TicketService();

        try {
            $result =
                $service->create(
                    [
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

                        'priority_code' =>
                            $request->input(
                                'priority_code',
                                'normal'
                            ),

                        'category_id' =>
                            $request->input(
                                'category_id',
                                ''
                            ),

                        'support_project_id' =>
                            $request->input(
                                'support_project_id',
                                ''
                            ),

                        'support_service_id' =>
                            $request->input(
                                'support_service_id',
                                ''
                            ),


                        'support_topic_id' =>
                            $request->input(
                                'support_topic_id',
                                ''
                            ),
                    ],
                    (int) $context['user_id'],
                    $context
                ,
                    $_FILES['attachments'] ?? []);

            if (empty($result['ok'])) {

                $page =
                    $service->form(
                        $result['form']
                        ?? [],
                        (int) $context['user_id']
                    );

                return $adminRender(
                    $response,
                    'ticketing-ticket-form',
                    [
                        'title' =>
                            'تیکت جدید',

                        'context' =>
                            $context,

                        'form' =>
                            $page['form'],

                        'options' =>
                            $page['options'],

                        'errors' =>
                            $result['errors']
                            ?? [],
                    ],
                    422
                );
            }

            return $response->redirect(
                '/admin/ticketing/tickets/'
                . rawurlencode(
                    (string) $result[
                        'public_reference'
                    ]
                )
                . '?status=created'
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_create',
                    [
                        'user_id' =>
                            (int) $context['user_id'],
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'ثبت تیکت',

                    'context' =>
                        $context,

                    'message' =>
                        'ثبت تیکت انجام نشد. '
                        . 'کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);


/*
 * ---------------------------------------------------------
 * Detail
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/tickets/{public_reference}',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $reference = trim(
            (string) $request->route(
                'public_reference'
            )
        );

        $path =
            '/admin/ticketing/tickets/'
            . rawurlencode(
                $reference
            );

        $context = $adminGuard(
            $response,
            $path
        );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $detail = (
                new \App\Services\Ticketing\TicketService()
            )->detailForUser(
                $reference,
                (int) $context['user_id']
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_detail',
                    [
                        'user_id' =>
                            (int) $context['user_id'],

                        'reference' =>
                            $reference,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'جزئیات تیکت',

                    'context' =>
                        $context,

                    'message' =>
                        'نمایش تیکت در حال حاضر '
                        . 'ممکن نیست. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }

        if ($detail === null) {
            return $response->redirect(
                '/admin/ticketing/tickets'
                . '?status=not_found'
            );
        }

        return $adminRender(
            $response,
            'ticketing-ticket-detail',
            [
                'title' =>
                    'جزئیات تیکت',

                'context' =>
                    $context,

                'detail' =>
                    $detail,

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
