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

        /*
         * T7A2_STAFF_AWARE_ENTRY
         *
         * One public Ticketing card serves both audiences:
         *
         * - requester -> onboarding / project membership
         * - staff     -> Ticketing staff shell
         *
         * Project membership remains the scope boundary.
         */
        $onboarding =
            new \App\Services\Ticketing\TicketRequesterOnboardingService();

        $userId =
            (int) $context['user_id'];

        $hasTicketingPermission =
            (
                new \App\Services\AuthorizationService()
            )->hasPermission(
                $userId,
                'ticketing.ticket.view'
            );

        if (
            $hasTicketingPermission
            &&
            $onboarding->hasStaffMembership(
                $userId
            )
        ) {
            $urls =
                new \IPKF\Support\ApplicationUrlRegistry();

            return $response->redirect(
                $urls->ticketingLaunch(
                    '/admin/ticketing',
                    (string) $request->host()
                )
            );
        }

        try {
            $page =
                $onboarding->page(
                    $userId
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
