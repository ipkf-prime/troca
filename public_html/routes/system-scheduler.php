<?php

declare(strict_types=1);

$systemSchedulerGuard =
    static function (
        $request,
        $response,
        callable $adminGuard,
        callable $adminRender
    ) {
        $urls =
            new \IPKF\Support\ApplicationUrlRegistry();

        $requestHost =
            strtolower(
                trim(
                    (string) $request->host()
                )
            );

        $coreHost =
            strtolower(
                trim(
                    $urls->coreHost()
                )
            );

        /*
         * Scheduler management belongs to Core /
         * System Management only.
         *
         * Module runtimes redirect management traffic
         * to the Core control plane.
         */
        if (
            $requestHost !== ''
            &&
            $coreHost !== ''
            &&
            $requestHost !== $coreHost
        ) {
            return
                $response->redirect(
                    $urls->core(
                        '/admin/system/scheduler'
                    )
                );
        }

        /*
         * Dedicated system.scheduler permissions will
         * be introduced after Core Dev/Prod DB isolation.
         *
         * For now we reuse the existing System Management
         * access.manage permission without modifying
         * the shared Core database.
         */
        $context =
            $adminGuard(
                $response,
                '/admin/access-control'
            );

        if (!is_array($context)) {
            return $context;
        }

        $allowed =
            (new \App\Services\AuthorizationService())
                ->hasPermission(
                    (int) $context['user_id'],
                    'access.manage'
                );

        if (!$allowed) {
            return
                $adminRender(
                    $response,
                    'forbidden',
                    [
                        'title' =>
                            'دسترسی غیرمجاز',

                        'context' =>
                            $context,
                    ],
                    403
                );
        }

        return $context;
    };


$router->get(
    '/admin/system/scheduler',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $systemSchedulerGuard
    ) {
        $context =
            $systemSchedulerGuard(
                $request,
                $response,
                $adminGuard,
                $adminRender
            );

        if (!is_array($context)) {
            return $context;
        }

        $page =
            (new \IPKF\Scheduler\SchedulerControlPlaneService())
                ->page();

        $status =
            trim(
                (string) $request->input(
                    'status',
                    ''
                )
            );

        $notices = [
            'updated' =>
                'تنظیمات اجرای خودکار ذخیره شد.',

            'run_success' =>
                'اجرای دستی با موفقیت انجام شد.',

            'run_failed' =>
                'اجرای دستی با خطا پایان یافت.',

            'invalid' =>
                'درخواست معتبر نیست.',

            'invalid_csrf' =>
                'اعتبار فرم منقضی شده است.',
        ];

        return
            $adminRender(
                $response,
                'system-scheduler',
                [
                    'title' =>
                        'مدیریت اجرای خودکار',

                    'context' =>
                        $context,

                    'page' =>
                        $page,

                    'notice' =>
                        $notices[$status]
                        ?? '',
                ]
            );
    }
);


$router->post(
    '/admin/system/scheduler/{application_key}/{binding_id}/schedule',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $systemSchedulerGuard
    ) {
        $context =
            $systemSchedulerGuard(
                $request,
                $response,
                $adminGuard,
                $adminRender
            );

        if (!is_array($context)) {
            return $context;
        }

        if (
            !(new \IPKF\Security\Csrf())
                ->check(
                    (string) $request->input(
                        '_token',
                        ''
                    )
                )
        ) {
            return
                $response->redirect(
                    '/admin/system/scheduler'
                    . '?status=invalid_csrf'
                );
        }

        $ok =
            (new \IPKF\Scheduler\SchedulerControlPlaneService())
                ->update(
                    (string) $request->route(
                        'application_key'
                    ),

                    (int) $request->route(
                        'binding_id'
                    ),

                    [
                        'state_code' =>
                            $request->input(
                                'state_code',
                                ''
                            ),

                        'schedule_type' =>
                            $request->input(
                                'schedule_type',
                                'interval'
                            ),

                        'interval_minutes' =>
                            $request->input(
                                'interval_minutes',
                                5
                            ),
                    ],

                    'user:'
                    . (int) $context['user_id']
                );

        return
            $response->redirect(
                '/admin/system/scheduler'
                . '?status='
                . (
                    $ok
                        ? 'updated'
                        : 'invalid'
                )
            );
    }
);


$router->post(
    '/admin/system/scheduler/{application_key}/{binding_id}/run',
    function (
        $request,
        $response
    ) use (
        $adminRender,
        $adminGuard,
        $systemSchedulerGuard
    ) {
        $context =
            $systemSchedulerGuard(
                $request,
                $response,
                $adminGuard,
                $adminRender
            );

        if (!is_array($context)) {
            return $context;
        }

        if (
            !(new \IPKF\Security\Csrf())
                ->check(
                    (string) $request->input(
                        '_token',
                        ''
                    )
                )
        ) {
            return
                $response->redirect(
                    '/admin/system/scheduler'
                    . '?status=invalid_csrf'
                );
        }

        $result =
            (new \IPKF\Scheduler\SchedulerControlPlaneService())
                ->runNow(
                    (string) $request->route(
                        'application_key'
                    ),

                    (int) $request->route(
                        'binding_id'
                    ),

                    'user:'
                    . (int) $context['user_id']
                );

        return
            $response->redirect(
                '/admin/system/scheduler'
                . '?status='
                . (
                    (
                        $result['status']
                        ?? ''
                    ) === 'success'
                        ? 'run_success'
                        : 'run_failed'
                )
            );
    }
);
