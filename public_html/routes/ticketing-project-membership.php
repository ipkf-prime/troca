<?php

declare(strict_types=1);

/*
 * Dynamic project membership configuration.
 *
 * This file is loaded before the legacy requester
 * lifecycle block so that its static route contract
 * remains untouched.
 */
$router->post(
    '/admin/ticketing/projects/{public_reference}/membership',
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
            . '/edit';

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
                        'تنظیمات عضویت پروژه',

                    'context' =>
                        $context,

                    'message' =>
                        'اعتبار فرم منقضی شده است.',
                ],
                419
            );
        }

        $input = [
            'membership_mode' =>
                $request->input(
                    'membership_mode',
                    'public'
                ),

            'approval_mode' =>
                $request->input(
                    'approval_mode',
                    'manager'
                ),

            'invite_enabled' =>
                $request->input(
                    'invite_enabled',
                    0
                ),

            'form_enabled' =>
                $request->input(
                    'form_enabled',
                    0
                ),

            'membership_fields' =>
                $request->input(
                    'membership_fields',
                    []
                ),
        ];

        try {
            $result =
                (
                    new \App\Services\Ticketing\SupportProjectMembershipConfigurationService()
                )->save(
                    $reference,
                    $input,
                    (int) $context[
                        'user_id'
                    ]
                );

            if (
                !empty(
                    $result[
                        'not_found'
                    ]
                )
            ) {
                return $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'تنظیمات عضویت پروژه',

                        'context' =>
                            $context,

                        'message' =>
                            'پروژه مورد نظر پیدا نشد.',
                    ],
                    404
                );
            }

            if (empty($result['ok'])) {
                return $response->redirect(
                    '/admin/ticketing/projects/'
                    . rawurlencode(
                        $reference
                    )
                    . '/edit?tab=membership&membership_status=invalid'
                );
            }

            return $response->redirect(
                '/admin/ticketing/projects/'
                . rawurlencode(
                    $reference
                )
                . '/edit?tab=membership&membership_status=saved'
            );

        } catch (\Throwable $exception) {

            $incident =
                $ticketingRuntimeReport(
                    $exception,
                    'support_project_membership_configuration',
                    [
                        'user_id' =>
                            (int) $context[
                                'user_id'
                            ],

                        'project_reference' =>
                            $reference,
                    ]
                );

            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'تنظیمات عضویت پروژه',

                    'context' =>
                        $context,

                    'message' =>
                        'ذخیره تنظیمات عضویت انجام نشد. کد پیگیری: '
                        . $incident,
                ],
                503
            );
        }
    }
);

/*
 * TICKETING_REQUESTER_MANAGER_REVOKE_ROUTES
 *
 * Requester membership administration is kept inside
 * the dedicated project-membership route slice.
 */
$router->get(
    '/admin/ticketing/projects/{public_reference}/requesters',
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

        try {

            $page =
                (
                    new \App\Services\Ticketing\TicketRequesterOnboardingService()
                )->requesterMembersForManager(
                    $reference
                );

        } catch (\Throwable) {

            return
                $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'مدیریت اعضای متقاضی',

                        'context' =>
                            $context,

                        'message' =>
                            'اطلاعات اعضای متقاضی در حال حاضر در دسترس نیست.',
                    ],
                    503
                );
        }

        if (
            ($page['ok'] ?? false)
            !== true
        ) {
            return
                $adminRender(
                    $response,
                    'placeholder',
                    [
                        'title' =>
                            'مدیریت اعضای متقاضی',

                        'context' =>
                            $context,

                        'message' =>
                            'پروژه پشتیبانی موردنظر پیدا نشد.',
                    ],
                    404
                );
        }

        return
            $adminRender(
                $response,
                'ticketing-project-requester-members',
                [
                    'title' =>
                        'مدیریت اعضای متقاضی',

                    'context' =>
                        $context,

                    'page' =>
                        $page,
                ]
            );
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/requesters/{member_id}/revoke',
    function (
        $request,
        $response
    ) use (
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
                    '/admin/ticketing/projects/'
                    . rawurlencode(
                        $reference
                    )
                    . '/requesters'
                    . '?membership_status=csrf'
                );
        }

        $memberId =
            max(
                0,
                (int) $request->route(
                    'member_id',
                    0
                )
            );

        try {

            $result =
                (
                    new \App\Services\Ticketing\TicketRequesterOnboardingService()
                )->revokeRequester(
                    $reference,
                    $memberId,
                    (int) $context[
                        'user_id'
                    ]
                );

            $state =
                trim(
                    (string) (
                        $result['state']
                        ?? 'requester_revoke_failed'
                    )
                );

        } catch (\Throwable) {

            $state =
                'requester_revoke_failed';
        }

        if ($state === '') {
            $state =
                'requester_revoke_failed';
        }

        return
            $response->redirect(
                '/admin/ticketing/projects/'
                . rawurlencode(
                    $reference
                )
                . '/requesters'
                . '?membership_status='
                . rawurlencode(
                    $state
                )
            );
    }
);


/*
 * TICKETING_PROJECT_MEMBER_ACCESS_CENTER_ROUTES
 *
 * Canonical project-local member/access administration.
 * Existing requester-only routes remain available for backward compatibility.
 */

$router->get(
    '/admin/ticketing/projects/{public_reference}/members',
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
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        try {
            $page =
                (new \App\Services\Ticketing\TicketProjectMemberAccessService())
                    ->page(
                        $reference
                    );
        } catch (\Throwable) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'اعضا و دسترسی‌ها',

                    'context' =>
                        $context,

                    'message' =>
                        'اطلاعات اعضا و دسترسی‌های پروژه در حال حاضر در دسترس نیست.',
                ],
                503
            );
        }

        if (
            ($page['ok'] ?? false)
            !== true
        ) {
            return $adminRender(
                $response,
                'placeholder',
                [
                    'title' =>
                        'پروژه پیدا نشد',

                    'context' =>
                        $context,

                    'message' =>
                        'پروژه پشتیبانی موردنظر پیدا نشد.',
                ],
                404
            );
        }

        return $adminRender(
            $response,
            'ticketing-project-members',
            [
                'title' =>
                    'اعضا و دسترسی‌ها',

                'context' =>
                    $context,

                'page' =>
                    $page,
            ]
        );
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/members/{member_id}/role',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $memberId =
            max(
                0,
                (int) $request->route(
                    'member_id',
                    0
                )
            );

        $base =
            '/admin/ticketing/projects/'
            . rawurlencode($reference)
            . '/members';

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                $base
                . '?status=csrf'
            );
        }

        try {
            $result =
                (new \App\Services\Ticketing\TicketProjectMemberAccessService())
                    ->changeRole(
                        $reference,
                        $memberId,
                        (string) $request->input(
                            'role_code',
                            ''
                        ),
                        (int) $context['user_id']
                    );

            $status =
                !empty($result['ok'])
                    ? (string) (
                        $result['state']
                        ?? 'member_role_saved'
                    )
                    : (string) (
                        $result['error']
                        ?? 'failed'
                    );
        } catch (\Throwable) {
            $status =
                'failed';
        }

        return $response->redirect(
            $base
            . '?status='
            . rawurlencode($status)
        );
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/members/{member_id}/revoke',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $memberId =
            max(
                0,
                (int) $request->route(
                    'member_id',
                    0
                )
            );

        $base =
            '/admin/ticketing/projects/'
            . rawurlencode($reference)
            . '/members';

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                $base
                . '?status=csrf'
            );
        }

        try {
            $result =
                (new \App\Services\Ticketing\TicketProjectMemberAccessService())
                    ->revoke(
                        $reference,
                        $memberId,
                        (int) $context['user_id']
                    );

            $status =
                !empty($result['ok'])
                    ? (string) (
                        $result['state']
                        ?? 'member_revoked'
                    )
                    : (string) (
                        $result['error']
                        ?? 'failed'
                    );
        } catch (\Throwable) {
            $status =
                'failed';
        }

        return $response->redirect(
            $base
            . '?status='
            . rawurlencode($status)
        );
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/members/{member_id}/restore',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $memberId =
            max(
                0,
                (int) $request->route(
                    'member_id',
                    0
                )
            );

        $base =
            '/admin/ticketing/projects/'
            . rawurlencode($reference)
            . '/members';

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                $base
                . '?status=csrf'
            );
        }

        try {
            $result =
                (new \App\Services\Ticketing\TicketProjectMemberAccessService())
                    ->restore(
                        $reference,
                        $memberId,
                        (int) $context['user_id']
                    );

            $status =
                !empty($result['ok'])
                    ? (string) (
                        $result['state']
                        ?? 'member_restored'
                    )
                    : (string) (
                        $result['error']
                        ?? 'failed'
                    );
        } catch (\Throwable) {
            $status =
                'failed';
        }

        return $response->redirect(
            $base
            . '?status='
            . rawurlencode($status)
        );
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/members/{member_id}/team',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $memberId =
            max(
                0,
                (int) $request->route(
                    'member_id',
                    0
                )
            );

        $base =
            '/admin/ticketing/projects/'
            . rawurlencode($reference)
            . '/members';

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                $base
                . '?status=csrf'
            );
        }

        $input = [
            'team_id' =>
                $request->input(
                    'team_id',
                    0
                ),

            'staff_role_code' =>
                $request->input(
                    'staff_role_code',
                    ''
                ),

            'can_assign' =>
                $request->input(
                    'can_assign',
                    0
                ),

            'can_observe' =>
                $request->input(
                    'can_observe',
                    0
                ),

            'can_assist' =>
                $request->input(
                    'can_assist',
                    0
                ),

            'can_takeover' =>
                $request->input(
                    'can_takeover',
                    0
                ),

            'can_transfer' =>
                $request->input(
                    'can_transfer',
                    0
                ),
        ];

        try {
            $result =
                (new \App\Services\Ticketing\TicketProjectMemberAccessService())
                    ->saveTeam(
                        $reference,
                        $memberId,
                        $input,
                        (int) $context['user_id']
                    );

            $status =
                !empty($result['ok'])
                    ? (string) (
                        $result['state']
                        ?? 'team_saved'
                    )
                    : (string) (
                        $result['error']
                        ?? 'failed'
                    );
        } catch (\Throwable) {
            $status =
                'failed';
        }

        return $response->redirect(
            $base
            . '?status='
            . rawurlencode($status)
        );
    }
);


$router->post(
    '/admin/ticketing/projects/{public_reference}/members/{member_id}/teams/{team_id}/remove',
    function (
        $request,
        $response
    ) use ($adminGuard) {
        $context =
            $adminGuard(
                $response,
                '/admin/ticketing/projects'
            );

        if (!is_array($context)) {
            return $context;
        }

        $reference =
            trim(
                (string) $request->route(
                    'public_reference',
                    ''
                )
            );

        $memberId =
            max(
                0,
                (int) $request->route(
                    'member_id',
                    0
                )
            );

        $teamId =
            max(
                0,
                (int) $request->route(
                    'team_id',
                    0
                )
            );

        $base =
            '/admin/ticketing/projects/'
            . rawurlencode($reference)
            . '/members';

        if (
            !(new \IPKF\Security\Csrf())->check(
                (string) $request->input(
                    '_token',
                    ''
                )
            )
        ) {
            return $response->redirect(
                $base
                . '?status=csrf'
            );
        }

        try {
            $result =
                (new \App\Services\Ticketing\TicketProjectMemberAccessService())
                    ->removeTeam(
                        $reference,
                        $memberId,
                        $teamId,
                        (int) $context['user_id']
                    );

            $status =
                !empty($result['ok'])
                    ? (string) (
                        $result['state']
                        ?? 'team_removed'
                    )
                    : (string) (
                        $result['error']
                        ?? 'failed'
                    );
        } catch (\Throwable) {
            $status =
                'failed';
        }

        return $response->redirect(
            $base
            . '?status='
            . rawurlencode($status)
        );
    }
);
