<?php

declare(strict_types=1);

/* T7A2_EXTERNAL_REQUESTER_ONBOARDING */

$router->get(
    '/admin/support/ticketing',
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
                '/admin/support/ticketing'
            );

        if (!is_array($context)) {
            return $context;
        }

        try {
            $page =
                (new \App\Services\Ticketing\TicketRequesterOnboardingService())
                    ->page(
                        (int) $context['user_id']
                    );

        } catch (\Throwable) {

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'پشتیبانی و تیکتینگ',

                    'context' =>
                        $context,

                    'message' =>
                        'امکان بارگذاری پروژه‌های پشتیبانی وجود ندارد.',
                ],
                503
            );
        }

        return $adminRender(
            $response,
            'ticketing-requester-onboarding',
            [
                'title' =>
                    'پشتیبانی و تیکتینگ',

                'context' =>
                    $context,

                'page' =>
                    $page,

                'status' =>
                    (string) $request->input(
                        'status',
                        ''
                    ),

                'error' =>
                    (string) $request->input(
                        'error',
                        ''
                    ),
            ]
        );
    }
);


$router->post(
    '/admin/support/ticketing/join',
    function (
        $request,
        $response
    ) use ($adminGuard) {

        $context =
            $adminGuard(
                $response,
                '/admin/support/ticketing/join'
            );

        if (!is_array($context)) {
            return $context;
        }

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                '/admin/support/ticketing?error=csrf'
            );
        }

        $result =
            (new \App\Services\Ticketing\TicketRequesterOnboardingService())
                ->joinOpen(
                    (string) $request->input(
                        'project_reference',
                        ''
                    ),
                    (int) $context['user_id']
                );

        if (empty($result['ok'])) {
            return $response->redirect(
                '/admin/support/ticketing?error='
                . rawurlencode(
                    (string) (
                        $result['error']
                        ?? 'join_failed'
                    )
                )
            );
        }

        $state =
            (string) (
                $result['membership']['state']
                ?? ''
            );

        return $response->redirect(
            '/admin/support/ticketing?status='
            . (
                $state === 'already_active'
                    ? 'already'
                    : 'joined'
            )
        );
    }
);


$router->post(
    '/admin/support/ticketing/invite',
    function (
        $request,
        $response
    ) use ($adminGuard) {

        $context =
            $adminGuard(
                $response,
                '/admin/support/ticketing/invite'
            );

        if (!is_array($context)) {
            return $context;
        }

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                '/admin/support/ticketing?error=csrf'
            );
        }

        $result =
            (new \App\Services\Ticketing\TicketRequesterOnboardingService())
                ->joinWithCode(
                    (string) $request->input(
                        'invite_code',
                        ''
                    ),
                    (int) $context['user_id']
                );

        if (empty($result['ok'])) {
            return $response->redirect(
                '/admin/support/ticketing?error='
                . rawurlencode(
                    (string) (
                        $result['error']
                        ?? 'invite_failed'
                    )
                )
            );
        }

        $state =
            (string) (
                $result['membership']['state']
                ?? ''
            );

        return $response->redirect(
            '/admin/support/ticketing?status='
            . (
                $state === 'already_active'
                    ? 'already'
                    : 'joined'
            )
        );
    }
);
