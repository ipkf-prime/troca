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

            'priority' =>
                trim(
                    (string) $request->input(
                        'priority',
                        ''
                    )
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
            )->form();

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
                    ],
                    (int) $context['user_id'],
                    $context
                );

            if (empty($result['ok'])) {

                $page =
                    $service->form(
                        $result['form']
                        ?? []
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
