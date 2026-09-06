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
            /*
             * Ticketing membership is authoritative for
             * choosing the in-module experience.
             *
             * A Core account may remain base `user` while
             * also being an active Ticketing member/manager
             * of an operational support team.
             */
            $staffDashboard = (
                new \App\Services\Ticketing\TicketStaffOperationsService()
            )->page(
                (int) $context['user_id'],
                $context,
                [
                    'scope' => 'all',
                ]
            );

            $isStaff =
                !empty(
                    $staffDashboard[
                        'is_staff'
                    ]
                );

            $dashboard =
                $isStaff
                    ? []
                    : (
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
                        . 'در دسترس نیست. کد خطا: '
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

                'is_staff' =>
                    $isStaff,

                'staff_dashboard' =>
                    $staffDashboard,

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
                        . 'کد خطا: '
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
                        'ثبت پروژه انجام نشد. کد خطا: '
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
                        'ویرایش پروژه انجام نشد. کد خطا: '
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
                        'فهرست مخاطبان در دسترس نیست. کد خطا: '
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
                        'افزودن کاربر انجام نشد. کد خطا: '
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
                        'ثبت مخاطب انجام نشد. کد خطا: '
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
                        'ساختار پشتیبانی در دسترس نیست. کد خطا: '
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
                        'عملیات ساختار پشتیبانی انجام نشد. کد خطا: '
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
                        'تنظیمات مسیریابی در دسترس نیست. کد خطا: '
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

            'status' =>
                $request->input(
                    'status',
                    'active'
                ),

            'confirm_impact' =>
                $request->input(
                    'confirm_impact',
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
                        'عملیات مسیریابی انجام نشد. کد خطا: '
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

/*
 * ---------------------------------------------------------
 * TICKETING_STATUS_TITLE_MANAGEMENT
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/statuses',
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
                '/admin/ticketing/statuses'
            );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $service =
                new \App\Services\Ticketing\TicketStatusTitleManagementService();

            $page =
                $service->page();

            return $adminRender(
                $response,
                'ticketing-statuses',
                [
                    'title' =>
                        'عنوان وضعیت‌های تیکتینگ',

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

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_status_title_index',
                    [
                        'user_id' =>
                            (int) (
                                $context['user_id']
                                ?? 0
                            ),
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'عنوان وضعیت‌های تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'فهرست وضعیت‌ها در دسترس نیست. کد خطا: '
                        . $incident,
                ],
                503
            );
        }
    }
);


$router->post(
    '/admin/ticketing/statuses',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/statuses'
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
            return $response->redirect(
                '/admin/ticketing/statuses'
                . '?status=status_title_invalid_csrf'
            );
        }

        try {
            $service =
                new \App\Services\Ticketing\TicketStatusTitleManagementService();

            $result =
                $service->updateTitle(
                    (string) $request->input(
                        'code',
                        ''
                    ),

                    (string) $request->input(
                        'title',
                        ''
                    )
                );

            $status =
                trim(
                    (string) (
                        $result['status']
                        ?? 'status_title_failed'
                    )
                );

            $allowed = [
                'status_title_updated',
                'status_title_required',
                'status_title_too_long',
                'status_title_persian_required',
                'status_title_invalid',
                'status_title_not_found',
            ];

            if (
                !in_array(
                    $status,
                    $allowed,
                    true
                )
            ) {
                $status =
                    'status_title_failed';
            }

            return $response->redirect(
                '/admin/ticketing/statuses'
                . '?status='
                . rawurlencode(
                    $status
                )
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_status_title_update',
                    [
                        'user_id' =>
                            (int) (
                                $context['user_id']
                                ?? 0
                            ),

                        'status_code' =>
                            trim(
                                (string) $request->input(
                                    'code',
                                    ''
                                )
                            ),
                    ]
                );

            error_log(
                'IPKF_TICKETING_STATUS_TITLE '
                . $incident
            );

            return $response->redirect(
                '/admin/ticketing/statuses'
                . '?status=status_title_failed'
            );
        }
    }
);

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
                    'priority'
                ),

            'dir1' =>
                $request->input(
                    'dir1',
                    'desc'
                ),

            'sort2' =>
                $request->input(
                    'sort2',
                    'last_activity'
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
                        . 'در دسترس نیست. کد خطا: '
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
                        . 'در دسترس نیست. کد خطا: '
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
                        . 'کد خطا: '
                        . $incident,
                ],
                503
            );
        }
    }
);


/*
 * ---------------------------------------------------------
 * Secure Ticket Attachment
 * ---------------------------------------------------------
 */
$router->get(
    '/admin/ticketing/tickets/{public_reference}/attachments/{attachment_id}',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $reference =
            trim(
                (string) $request->route(
                    'public_reference'
                )
            );

        $attachmentId =
            (int) $request->route(
                'attachment_id'
            );

        /*
         * Permission/RBAC context intentionally mirrors Ticket Detail.
         */
        $detailPath =
            '/admin/ticketing/tickets/'
            . rawurlencode(
                $reference
            );

        $context =
            $adminGuard(
                $response,
                $detailPath
            );

        if (!is_array($context)) {
            return $context;
        }


        try {

            /*
             * TICKETING_STAFF_ATTACHMENT_VISIBILITY_V1
             *
             * Requester / active-assignee authorization stays
             * first. Scoped staff fallback is permitted only
             * through canonical staff-cartable visibility.
             */
            $ticketService =
                new \App\Services\Ticketing\TicketService();

            $attachment =
                $ticketService->attachmentForUser(
                    $reference,
                    $attachmentId,
                    (int) $context['user_id']
                );


            if ($attachment === null) {

                $staffCanView =
                    (
                        new \App\Services\Ticketing\TicketStaffOperationsService()
                    )->canViewTicket(
                        $reference,
                        (int) $context['user_id']
                    );


                if ($staffCanView) {

                    $attachment =
                        $ticketService
                            ->attachmentForAuthorizedContext(
                                $reference,
                                $attachmentId
                            );
                }
            }


            if ($attachment === null) {
                return
                    $response
                        ->status(404)
                        ->header(
                            'Content-Type',
                            'text/plain; charset=UTF-8'
                        )
                        ->header(
                            'Cache-Control',
                            'private, no-store, max-age=0'
                        )
                        ->send(
                            'پیوست موردنظر یافت نشد.'
                        );
            }


            if (
                (string) (
                    $attachment[
                        'storage_disk'
                    ]
                    ?? ''
                )
                !== 'ticketing_private'
            ) {
                throw new \RuntimeException(
                    'Unexpected attachment storage disk.'
                );
            }


            $scanStatus =
                strtolower(
                    trim(
                        (string) (
                            $attachment[
                                'scan_status_code'
                            ]
                            ?? ''
                        )
                    )
                );


            if (
                !in_array(
                    $scanStatus,
                    [
                        'clean',
                        'approved',
                    ],
                    true
                )
            ) {
                return
                    $response
                        ->status(423)
                        ->header(
                            'Content-Type',
                            'text/plain; charset=UTF-8'
                        )
                        ->header(
                            'Cache-Control',
                            'private, no-store, max-age=0'
                        )
                        ->send(
                            'این فایل هنوز برای مشاهده تأیید نشده است.'
                        );
            }


            $storageKey =
                str_replace(
                    '\\',
                    '/',
                    trim(
                        (string) (
                            $attachment[
                                'storage_key'
                            ]
                            ?? ''
                        )
                    )
                );


            if (
                $storageKey === ''
                ||
                str_contains(
                    $storageKey,
                    "\0"
                )
                ||
                str_starts_with(
                    $storageKey,
                    '/'
                )
                ||
                preg_match(
                    '#(^|/)\.\.(/|$)#',
                    $storageKey
                )
            ) {
                throw new \RuntimeException(
                    'Unsafe attachment storage key.'
                );
            }


            /*
             * Upload contract:
             * BASE_PATH/storage/uploads/<storage_key>
             */
            $storageRoot =
                realpath(
                    BASE_PATH
                    . '/storage/uploads'
                );


            if ($storageRoot === false) {
                throw new \RuntimeException(
                    'Private attachment storage root unavailable.'
                );
            }


            $filePath =
                realpath(
                    $storageRoot
                    . DIRECTORY_SEPARATOR
                    . str_replace(
                        '/',
                        DIRECTORY_SEPARATOR,
                        $storageKey
                    )
                );


            if (
                $filePath === false
                ||
                !is_file(
                    $filePath
                )
                ||
                !str_starts_with(
                    $filePath,
                    $storageRoot
                    . DIRECTORY_SEPARATOR
                )
            ) {
                return
                    $response
                        ->status(404)
                        ->header(
                            'Content-Type',
                            'text/plain; charset=UTF-8'
                        )
                        ->send(
                            'فایل پیوست در فضای ذخیره‌سازی یافت نشد.'
                        );
            }


            $fileSize =
                filesize(
                    $filePath
                );


            if (
                $fileSize === false
                ||
                $fileSize
                !== (int) (
                    $attachment[
                        'size_bytes'
                    ]
                    ?? -1
                )
            ) {
                throw new \RuntimeException(
                    'Attachment size verification failed.'
                );
            }


            $storedChecksum =
                strtolower(
                    trim(
                        (string) (
                            $attachment[
                                'checksum_sha256'
                            ]
                            ?? ''
                        )
                    )
                );


            $actualChecksum =
                strtolower(
                    hash_file(
                        'sha256',
                        $filePath
                    )
                );


            if (
                $storedChecksum === ''
                ||
                !hash_equals(
                    $storedChecksum,
                    $actualChecksum
                )
            ) {
                throw new \RuntimeException(
                    'Attachment SHA256 verification failed.'
                );
            }


            $mime =
                strtolower(
                    trim(
                        (string) (
                            $attachment[
                                'mime_type'
                            ]
                            ?? ''
                        )
                    )
                );


            if (
                preg_match(
                    '~^[a-z0-9!#$&^_.+\-]+/[a-z0-9!#$&^_.+\-]+$~',
                    $mime
                ) !== 1
            ) {
                $mime =
                    'application/octet-stream';
            }


            /*
             * Never render active HTML/SVG inline.
             */
            $inlineMimeTypes = [
                'image/png',
                'image/jpeg',
                'image/gif',
                'image/webp',
                'application/pdf',
                'text/plain',
            ];


            $disposition =
                in_array(
                    $mime,
                    $inlineMimeTypes,
                    true
                )
                    ? 'inline'
                    : 'attachment';


            $originalName =
                basename(
                    str_replace(
                        '\\',
                        '/',
                        trim(
                            (string) (
                                $attachment[
                                    'original_name'
                                ]
                                ?? 'attachment'
                            )
                        )
                    )
                );


            $originalName =
                preg_replace(
                    '/[\x00-\x1F\x7F]+/u',
                    '',
                    $originalName
                )
                ?: 'attachment';


            $extension =
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                );


            $safeExtension =
                preg_replace(
                    '/[^A-Za-z0-9]+/',
                    '',
                    $extension
                )
                ?: '';


            $fallbackName =
                'attachment-'
                . (int) $attachment['id']
                . (
                    $safeExtension !== ''
                        ? '.'
                            . $safeExtension
                        : ''
                );


            $contentDisposition =
                $disposition
                . '; filename="'
                . $fallbackName
                . '"; filename*=UTF-8'
                . chr(39)
                . chr(39)
                . rawurlencode(
                    $originalName
                );


            $bytes =
                file_get_contents(
                    $filePath
                );


            if (!is_string($bytes)) {
                throw new \RuntimeException(
                    'Attachment cannot be read.'
                );
            }


            return
                $response
                    ->status(200)
                    ->header(
                        'Content-Type',
                        $mime
                    )
                    ->header(
                        'Content-Length',
                        (string) strlen(
                            $bytes
                        )
                    )
                    ->header(
                        'Content-Disposition',
                        $contentDisposition
                    )
                    ->header(
                        'X-Content-Type-Options',
                        'nosniff'
                    )
                    ->header(
                        'X-Frame-Options',
                        'SAMEORIGIN'
                    )
                    ->header(
                        'Cache-Control',
                        'private, no-store, max-age=0'
                    )
                    ->send(
                        $bytes
                    );


        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_attachment_read',
                    [
                        'user_id' =>
                            (int) (
                                $context[
                                    'user_id'
                                ]
                                ?? 0
                            ),

                        'reference' =>
                            $reference,

                        'attachment_id' =>
                            $attachmentId,
                    ]
                );


            return
                $response
                    ->status(500)
                    ->header(
                        'Content-Type',
                        'text/plain; charset=UTF-8'
                    )
                    ->header(
                        'Cache-Control',
                        'private, no-store, max-age=0'
                    )
                    ->send(
                        'نمایش پیوست انجام نشد. کد پیگیری: '
                        . $incident
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
            /*
             * TICKETING_STAFF_DETAIL_CONTEXT_V1
             *
             * Requester / active-assignee visibility remains
             * the primary and narrowest authorization path.
             *
             * Staff receives read visibility only when the same
             * ticket is already visible in the canonical cartable.
             * Reply ownership is NOT granted here.
             */
            $ticketService =
                new \App\Services\Ticketing\TicketService();

            $detail =
                $ticketService->detailForUser(
                    $reference,
                    (int) $context['user_id']
                );


            if ($detail === null) {

                $staffCanView =
                    (
                        new \App\Services\Ticketing\TicketStaffOperationsService()
                    )->canViewTicket(
                        $reference,
                        (int) $context['user_id']
                    );


                if ($staffCanView) {

                    $detail =
                        $ticketService->detail(
                            $reference
                        );
                }
            }

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
                        . 'ممکن نیست. کد خطا: '
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

        (
            new \App\Services\Ticketing\TicketNotificationService()
        )->markViewed(
            (int) $context['user_id'],
            (string) $request->route(
                'public_reference'
            )
        );

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


/*
 * ============================================================================
 * ticketing_staff_operations_a7
 * Staff cartable / Take Over / Transfer / Escalation
 * ============================================================================
 */

$router->get(
    '/admin/ticketing/staff',
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
                '/admin/ticketing/staff'
            );

        if (!is_array($context)) {
            return $context;
        }


        try {

            $service =
                new \App\Services\Ticketing\TicketStaffOperationsService();

            $page =
                $service->page(
                    (int) $context['user_id'],
                    $context,
                    [
                        'scope' =>
                            $request->input(
                                'scope',
                                'all'
                            ),

                        'q' =>
                            $request->input(
                                'q',
                                ''
                            ),
                    ]
                );


            return $adminRender(
                $response,
                'ticketing-staff',
                [
                    'title' =>
                        'کارتابل پشتیبانی',

                    'context' =>
                        $context,

                    'page' =>
                        $page,

                    'status' =>
                        $request->input(
                            'status',
                            ''
                        ),
                ]
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_staff_cartable',
                    [
                        'user_id' =>
                            (int) $context[
                                'user_id'
                            ],
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'کارتابل پشتیبانی',

                    'context' =>
                        $context,

                    'message' =>
                        'کارتابل پشتیبانی در دسترس نیست. '
                        . 'کد خطا: '
                        . $incident,
                ],
                503
            );
        }
    }
);


$router->post(
    '/admin/ticketing/staff/{public_reference}/takeover',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $publicReference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/staff/'
                . $publicReference
                . '/takeover'
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
            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status=csrf'
            );
        }


        try {

            $service =
                new \App\Services\Ticketing\TicketStaffOperationsService();

            $result =
                $service->takeOver(
                    (string) $publicReference,
                    (int) $context['user_id'],
                    $context
                );


            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status='
                . rawurlencode(
                    (string) (
                        $result['status']
                        ?? 'operation-failed'
                    )
                )
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_takeover',
                    [
                        'user_id' =>
                            (int) $context[
                                'user_id'
                            ],

                        'ticket_reference' =>
                            (string) $publicReference,
                    ]
                );

            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status=operation-failed'
                . '&error='
                . rawurlencode(
                    $incident
                )
            );
        }
    }
);


$router->post(
    '/admin/ticketing/staff/{public_reference}/transfer',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $publicReference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/staff/'
                . $publicReference
                . '/transfer'
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
            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status=csrf'
            );
        }


        try {

            $service =
                new \App\Services\Ticketing\TicketStaffOperationsService();

            $result =
                $service->transfer(
                    (string) $publicReference,

                    (int) $request->input(
                        'target_member_id',
                        0
                    ),

                    (int) $context['user_id'],

                    $context
                );


            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status='
                . rawurlencode(
                    (string) (
                        $result['status']
                        ?? 'operation-failed'
                    )
                )
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_transfer',
                    [
                        'user_id' =>
                            (int) $context[
                                'user_id'
                            ],

                        'ticket_reference' =>
                            (string) $publicReference,
                    ]
                );

            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status=operation-failed'
                . '&error='
                . rawurlencode(
                    $incident
                )
            );
        }
    }
);


$router->post(
    '/admin/ticketing/staff/{public_reference}/escalate',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $publicReference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/staff/'
                . $publicReference
                . '/escalate'
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
            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status=csrf'
            );
        }


        try {

            $service =
                new \App\Services\Ticketing\TicketStaffOperationsService();

            $result =
                $service->escalate(
                    (string) $publicReference,
                    (int) $context['user_id'],
                    $context
                );


            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status='
                . rawurlencode(
                    (string) (
                        $result['status']
                        ?? 'operation-failed'
                    )
                )
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'ticket_escalate',
                    [
                        'user_id' =>
                            (int) $context[
                                'user_id'
                            ],

                        'ticket_reference' =>
                            (string) $publicReference,
                    ]
                );

            return $response->redirect(
                '/admin/ticketing/staff'
                . '?status=operation-failed'
                . '&error='
                . rawurlencode(
                    $incident
                )
            );
        }
    }
);


/*
 * ticketing_lifecycle_a8d1
 *
 * Public staff reply.
 * SLA lifecycle is intentionally NOT invoked here.
 * The existing external SLA scheduler reconciles:
 * - first_response_at
 * - waiting_requester pause
 */
$router->post(
    '/admin/ticketing/tickets/{public_reference}/reply',
    function (
        $request,
        $response
    ) use (
        $adminGuard
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/tickets/{public_reference}/reply'
            );

        if (!is_array($context)) {
            return $context;
        }

        $publicReference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        if ($publicReference === '') {
            return $response->redirect(
                '/admin/ticketing/tickets'
                . '?status=ticket_not_found'
            );
        }

        $detailUrl =
            '/admin/ticketing/tickets/'
            . rawurlencode(
                $publicReference
            );

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
            return $response->redirect(
                $detailUrl
                . '?status=reply_invalid_csrf'
            );
        }

        /*
         * TICKETING_STAFF_REPLY_POST_OWNERSHIP_GUARD
         *
         * Permission alone cannot authorize a reply.
         *
         * The current assignee must resolve to this exact user
         * inside this exact support project and the lifecycle turn
         * must belong to staff.
         */
        $replyAccess =
            (
                new \App\Services\Ticketing\TicketStaffReplyAccessService()
            )->evaluate(
                $publicReference,
                (int) (
                    $context['user_id']
                    ?? 0
                )
            );

        if (
            empty(
                $replyAccess['can_reply']
            )
        ) {
            $replyAccessState =
                trim(
                    (string) (
                        $replyAccess['state']
                        ?? 'reply_forbidden'
                    )
                );

            $safeReplyAccessStates = [
                'reply_waiting_requester',
                'reply_takeover_required',
                'reply_not_assignee',
                'reply_assignment_invalid',
                'reply_closed',
                'reply_invalid',
            ];

            if (
                !in_array(
                    $replyAccessState,
                    $safeReplyAccessStates,
                    true
                )
            ) {
                $replyAccessState =
                    'reply_forbidden';
            }

            return
                $response->redirect(
                    $detailUrl
                    . '?status='
                    . rawurlencode(
                        $replyAccessState
                    )
                );
        }

        try {
            $result =
                (
                    new \App\Services\Ticketing\TicketLifecycleService()
                )->staffReply(
                    $publicReference,
                    (string) $request->input(
                        'body',
                        ''
                    ),
                    (int) (
                        $context['user_id']
                        ?? 0
                    ),
                    $context,
                      is_array(
                          $_FILES['attachments']
                          ?? null
                      )
                          ? $_FILES['attachments']
                          : []
                );

            if (
                !empty(
                    $result['ok']
                )
            ) {
                return $response->redirect(
                    $detailUrl
                    . '?status=reply_sent'
                );
            }

            if (
                !empty(
                    $result['not_found']
                )
            ) {
                return $response->redirect(
                    '/admin/ticketing/tickets'
                    . '?status=ticket_not_found'
                );
            }

            $status =
                trim(
                    (string) (
                        $result['status']
                        ?? 'reply_failed'
                    )
                );

            $allowedStatuses = [
                'reply_empty',
                'reply_too_long',
                'reply_closed',
                'reply_forbidden',
                'reply_invalid',
            ];

            if (
                !in_array(
                    $status,
                    $allowedStatuses,
                    true
                )
            ) {
                $status =
                    'reply_failed';
            }

            return $response->redirect(
                $detailUrl
                . '?status='
                . rawurlencode(
                    $status
                )
            );
        } catch (\Throwable $exception) {
            error_log(
                'IPKF_TICKETING_LIFECYCLE '
                . json_encode(
                    [
                        'operation' =>
                            'staff_reply',

                        'ticket' =>
                            $publicReference,

                        'exception' =>
                            get_class(
                                $exception
                            ),

                        'message' =>
                            $exception
                                ->getMessage(),
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
            );

            return $response->redirect(
                $detailUrl
                . '?status=reply_failed'
            );
        }
    }
);



require
    __DIR__
    . '/ticketing-project-membership.php';

/*
 * ticketing_lifecycle_a8d2
 *
 * Requester reply:
 * - Core route access uses ticketing.ticket.view.
 * - Actual authorization is ticket ownership.
 * - Ownership is verified transactionally by the
 *   lifecycle repository.
 * - No direct SLA call occurs here.
 * - The active SLA scheduler reconciles the status
 *   transition and resumes the paused SLA state.
 */
/*
 * TICKETING_ROUTING_RECOVERY_V1_ROUTE
 *
 * Coarse RBAC uses the existing project-management route.
 * Exact ticket state and project-manager scope are checked again
 * inside TicketRoutingRecoveryService.
 */
$router->post(
    '/admin/ticketing/tickets/{public_reference}/recover-routing',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $ticketingRuntimeReport
    ) {
        $context = $adminGuard(
            $response,
            '/admin/ticketing/projects'
        );

        if (!is_array($context)) {
            return $context;
        }

        $publicReference = trim(
            (string) $request->route(
                'public_reference',
                ''
            )
        );

        if ($publicReference === '') {
            return $response->redirect(
                '/admin/ticketing/tickets'
                . '?status=ticket_not_found'
            );
        }

        $detailUrl =
            '/admin/ticketing/tickets/'
            . rawurlencode($publicReference);

        $csrf = new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                $detailUrl
                . '?routing_notice='
                . 'routing_recovery_invalid_csrf'
            );
        }

        try {
            $result = (
                new \App\Services\Ticketing\TicketRoutingRecoveryService()
            )->recoverMissingTopic(
                $publicReference,
                (int) $request->input(
                    'support_topic_id',
                    0
                ),
                (int) ($context['user_id'] ?? 0),
                $context
            );

            if (!empty($result['not_found'])) {
                return $response->redirect(
                    '/admin/ticketing/tickets'
                    . '?status=ticket_not_found'
                );
            }

            $status = trim(
                (string) (
                    $result['status']
                    ?? 'routing_recovery_failed'
                )
            );

            $allowed = [
                'routing_recovery_applied',
                'routing_recovery_invalid',
                'routing_recovery_invalid_csrf',
                'routing_recovery_invalid_topic',
                'routing_recovery_not_eligible',
                'routing_recovery_no_route',
                'routing_recovery_invalid_topology',
                'routing_recovery_no_eligible_assignee',
                'routing_recovery_forbidden',
                'routing_recovery_failed',
            ];

            if (!in_array($status, $allowed, true)) {
                $status = 'routing_recovery_failed';
            }

            return $response->redirect(
                $detailUrl
                . '?routing_notice='
                . rawurlencode($status)
            );

        } catch (\Throwable $exception) {
            $incident = $ticketingRuntimeReport(
                $exception,
                'ticket_routing_recovery',
                [
                    'user_id' =>
                        (int) ($context['user_id'] ?? 0),
                    'ticket_reference' =>
                        $publicReference,
                ]
            );

            return $response->redirect(
                $detailUrl
                . '?routing_notice=routing_recovery_failed'
                . '&error='
                . rawurlencode($incident)
            );
        }
    }
);


/*
 * TICKETING_PRIORITY_GOVERNANCE_ROUTE
 *
 * Coarse RBAC intentionally reuses the existing staff-reply
 * permission contract. Exact project/team operational access
 * is enforced again inside TicketPriorityManagementRepository.
 */
$router->post(
    '/admin/ticketing/tickets/{public_reference}/priority',
    function ($request, $response) use ($adminGuard) {
        $context = $adminGuard(
            $response,
            '/admin/ticketing/tickets/{public_reference}/reply'
        );

        if (!is_array($context)) {
            return $context;
        }

        $publicReference = trim(
            (string) $request->route(
                'public_reference',
                ''
            )
        );

        $detailUrl =
            '/admin/ticketing/tickets/'
            . rawurlencode($publicReference);

        $csrf = new \IPKF\Security\Csrf();

        if (
            !$csrf->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                $detailUrl
                . '?priority_notice=priority_invalid_csrf'
            );
        }

        try {
            $result =
                (
                    new \App\Services\Ticketing\TicketPriorityManagementService()
                )->change(
                    $publicReference,
                    (int) (
                        $context['user_id']
                        ?? 0
                    ),
                    (string) $request->input(
                        'priority_code',
                        ''
                    ),
                    (string) $request->input(
                        'priority_reason',
                        ''
                    )
                );

            if (!empty($result['not_found'])) {
                return $response->redirect(
                    '/admin/ticketing/tickets'
                    . '?status=ticket_not_found'
                );
            }

            $status = trim(
                (string) (
                    $result['status']
                    ?? 'priority_failed'
                )
            );

            $allowed = [
                'priority_changed',
                'priority_unchanged',
                'priority_invalid',
                'priority_reason_invalid',
                'priority_forbidden',
                'priority_failed',
            ];

            if (!in_array($status, $allowed, true)) {
                $status = 'priority_failed';
            }

            return $response->redirect(
                $detailUrl
                . '?priority_notice='
                . rawurlencode($status)
            );

        } catch (\Throwable $exception) {
            error_log(
                'IPKF_TICKETING_PRIORITY '
                . json_encode(
                    [
                        'ticket_reference' =>
                            $publicReference,
                        'exception' =>
                            get_class($exception),
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
            );

            return $response->redirect(
                $detailUrl
                . '?priority_notice=priority_failed'
            );
        }
    }
);


$router->post(
    '/admin/ticketing/tickets/{public_reference}/requester-reply',
    function (
        $request,
        $response
    ) use (
        $adminGuard
    ) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/tickets/{public_reference}/requester-reply'
            );

        if (!is_array($context)) {
            return $context;
        }

        $publicReference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        if ($publicReference === '') {
            return $response->redirect(
                '/admin/ticketing/tickets'
                . '?status=ticket_not_found'
            );
        }

        $detailUrl =
            '/admin/ticketing/tickets/'
            . rawurlencode(
                $publicReference
            );

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
            return $response->redirect(
                $detailUrl
                . '?status=requester_reply_invalid_csrf'
            );
        }

        $intent =
            trim(
                (string) $request->input(
                    'intent',
                    'update'
                )
            );

        try {
            $lifecycle =
                new \App\Services\Ticketing\TicketLifecycleService();

            if ($intent === 'resolve') {
                $result =
                    $lifecycle->requesterResolve(
                        $publicReference,
                        (int) (
                            $context['user_id']
                            ?? 0
                        )
                    );

                $successStatus =
                    'requester_resolved';

            } else {
                $intent = 'update';

                $result =
                    $lifecycle->requesterReply(
                        $publicReference,
                        (string) $request->input(
                            'body',
                            ''
                        ),
                        (int) (
                            $context['user_id']
                            ?? 0
                        ),
                        $_FILES['attachments'] ?? []
                    );

                $successStatus =
                    'requester_reply_sent';
            }

            if (!empty($result['ok'])) {
                return $response->redirect(
                    $detailUrl
                    . '?status='
                    . rawurlencode(
                        $successStatus
                    )
                );
            }

            if (!empty($result['not_found'])) {
                return $response->redirect(
                    '/admin/ticketing/tickets'
                    . '?status=ticket_not_found'
                );
            }

            $status =
                trim(
                    (string) (
                        $result['status']
                        ?? 'requester_reply_failed'
                    )
                );

            /*
             * TICKETING_REQUESTER_ATTACHMENT_ERROR_SURFACE_V2
             *
             * Convert the internal finite upload code to a finite
             * presentation status. Never redirect arbitrary error text.
             */
            $attachmentErrorCode =
                trim(
                    (string) (
                        $result['attachment_error_code']
                        ?? ''
                    )
                );

            $attachmentStatusByCode = [
                'ticket_attachment_too_many' =>
                    'requester_attachment_too_many',

                'ticket_attachment_upload_failed' =>
                    'requester_attachment_upload_failed',

                'ticket_attachment_upload_invalid' =>
                    'requester_attachment_upload_invalid',

                'ticket_attachment_empty' =>
                    'requester_attachment_empty',

                'ticket_attachment_too_large' =>
                    'requester_attachment_too_large',

                'ticket_attachment_total_too_large' =>
                    'requester_attachment_total_too_large',

                'ticket_attachment_type_invalid' =>
                    'requester_attachment_type_invalid',

                'ticket_attachment_infected' =>
                    'requester_attachment_infected',

                'ticket_attachment_scan_failed' =>
                    'requester_attachment_scan_failed',
            ];

            if (
                $status === 'requester_reply_invalid'
                && $attachmentErrorCode !== ''
            ) {
                $status =
                    $attachmentStatusByCode[
                        $attachmentErrorCode
                    ]
                    ?? 'requester_attachment_invalid';
            }

            $allowed = [
                'requester_reply_empty',
                'requester_reply_too_long',
                'requester_reply_forbidden',
                'requester_update_forbidden_state',
                'requester_resolve_forbidden_state',
                'requester_reply_invalid',

                'requester_attachment_too_many',
                'requester_attachment_upload_failed',
                'requester_attachment_upload_invalid',
                'requester_attachment_empty',
                'requester_attachment_too_large',
                'requester_attachment_total_too_large',
                'requester_attachment_type_invalid',
                'requester_attachment_infected',
                'requester_attachment_scan_failed',
                'requester_attachment_invalid',
            ];

            if (
                !in_array(
                    $status,
                    $allowed,
                    true
                )
            ) {
                $status =
                    'requester_reply_failed';
            }

            return $response->redirect(
                $detailUrl
                . '?status='
                . rawurlencode(
                    $status
                )
            );

        } catch (\Throwable $exception) {
            error_log(
                'IPKF_TICKETING_LIFECYCLE '
                . json_encode(
                    [
                        'operation' =>
                            $intent === 'resolve'
                                ? 'requester_resolve'
                                : 'requester_update',
                        'ticket_reference' =>
                            $publicReference,
                        'exception' =>
                            get_class($exception),
                    ],
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
            );

            return $response->redirect(
                $detailUrl
                . '?status=requester_reply_failed'
            );
        }
    }
);




/*
 * TICKETING_RESOLVE_CLOSE_REOPEN_RUNTIME
 *
 * Lifecycle completion routes.
 * RBAC is only the outer coarse gate;
 * TicketLifecycleTransitionRepository re-validates
 * project role, requester identity and exact ownership.
 */
foreach (
    [
        'resolve',
        'close',
        'reopen',
    ]
    as $ticketLifecycleAction
) {
    $router->post(
        '/admin/ticketing/tickets/{public_reference}/'
        . $ticketLifecycleAction,

        function (
            $request,
            $response
        ) use (
            $adminGuard,
            $ticketLifecycleAction
        ) {
            $routePattern =
                '/admin/ticketing/tickets/{public_reference}/'
                . $ticketLifecycleAction;

            $context =
                $adminGuard(
                    $response,
                    $routePattern
                );

            if (!is_array($context)) {
                return $context;
            }

            $publicReference =
                trim(
                    (string) $request->route(
                        'public_reference',
                        ''
                    )
                );

            if ($publicReference === '') {
                return
                    $response->redirect(
                        '/admin/ticketing/tickets'
                        . '?status=ticket_not_found'
                    );
            }

            $detailUrl =
                '/admin/ticketing/tickets/'
                . rawurlencode(
                    $publicReference
                );

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
                return
                    $response->redirect(
                        $detailUrl
                        . '?status=lifecycle_invalid_csrf'
                    );
            }

            try {
                $result =
                    (
                        new \App\Services\Ticketing\TicketLifecycleTransitionService()
                    )->transition(
                        $publicReference,
                        $ticketLifecycleAction,
                        (int) (
                            $context['user_id']
                            ?? 0
                        ),
                        $context
                    );

                if (
                    !empty(
                        $result['ok']
                    )
                ) {
                    $successMap = [
                        'resolve' =>
                            'ticket_resolved',

                        'close' =>
                            'ticket_closed',

                        'reopen' =>
                            'ticket_reopened',
                    ];

                    return
                        $response->redirect(
                            $detailUrl
                            . '?status='
                            . rawurlencode(
                                (string) (
                                    $successMap[
                                        $ticketLifecycleAction
                                    ]
                                    ?? 'lifecycle_failed'
                                )
                            )
                        );
                }

                if (
                    !empty(
                        $result['not_found']
                    )
                ) {
                    return
                        $response->redirect(
                            '/admin/ticketing/tickets'
                            . '?status=ticket_not_found'
                        );
                }

                $status =
                    trim(
                        (string) (
                            $result['status']
                            ?? 'lifecycle_failed'
                        )
                    );

                $allowed = [
                    'lifecycle_invalid',
                    'lifecycle_invalid_action',
                    'lifecycle_owner_required',
                    'lifecycle_waiting_requester',
                    'lifecycle_invalid_state',
                    'lifecycle_resolve_first',
                    'lifecycle_close_forbidden',
                    'lifecycle_reopen_invalid_state',
                    'lifecycle_reopen_forbidden',
                    'lifecycle_transition_conflict',
                ];

                if (
                    !in_array(
                        $status,
                        $allowed,
                        true
                    )
                ) {
                    $status =
                        'lifecycle_failed';
                }

                return
                    $response->redirect(
                        $detailUrl
                        . '?status='
                        . rawurlencode(
                            $status
                        )
                    );

            } catch (\Throwable $exception) {
                error_log(
                    'IPKF_TICKETING_LIFECYCLE_TRANSITION '
                    . json_encode(
                        [
                            'operation' =>
                                $ticketLifecycleAction,

                            'ticket' =>
                                $publicReference,

                            'exception' =>
                                get_class(
                                    $exception
                                ),

                            'message' =>
                                $exception
                                    ->getMessage(),
                        ],
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    )
                );

                return
                    $response->redirect(
                        $detailUrl
                        . '?status=lifecycle_failed'
                    );
            }
        }
    );
}
