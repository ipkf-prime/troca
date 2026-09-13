<?php

declare(strict_types=1);

use App\Services\Organization\OrganizationalAffiliationService;


/*
 * Generic Core self-service affiliation surface.
 */

$router->get(
    '/admin/profile/affiliation',
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
                '/admin/profile/affiliation'
            );


        if (
            !is_array(
                $context
            )
        ) {
            return
                $context;
        }


        try {

            $page =
                (
                    new OrganizationalAffiliationService()
                )->selfPage(
                    (int) $context[
                        'user_id'
                    ]
                );

        } catch (Throwable $exception) {

            error_log(
                'ORG_AFFILIATION_SELF_PAGE: '
                . $exception
                    ->getMessage()
            );


            $page = [
                'identity' => [],
                'organizations' => [],
                'positions' => [],
                'memberships' => [],
            ];
        }


        return
            $adminRender(
                $response,
                'profile-affiliation',
                [
                    'title' =>
                        'وابستگی سازمانی',

                    'context' =>
                        $context,

                    'page' =>
                        $page,

                    'status' =>
                        trim(
                            (string) $request
                                ->input(
                                    'status',
                                    ''
                                )
                        ),
                ]
            );
    }
);


$router->post(
    '/admin/profile/affiliation',
    function (
        $request,
        $response
    ) use (
        $adminGuard
    ) {

        $context =
            $adminGuard(
                $response,
                '/admin/profile/affiliation'
            );


        if (
            !is_array(
                $context
            )
        ) {
            return
                $context;
        }


        if (
            !(
                new \IPKF\Security\Csrf()
            )->check(
                (string) $request
                    ->input(
                        '_token',
                        ''
                    )
            )
        ) {
            return
                $response
                    ->redirect(
                        '/admin/profile/affiliation'
                        . '?status=invalid_csrf'
                    );
        }


        try {

            (
                new OrganizationalAffiliationService()
            )->request(
                (int) $context[
                    'user_id'
                ],
                [
                    'organization_reference' =>
                        (string) $request
                            ->input(
                                'organization_reference',
                                ''
                            ),

                    'position_reference' =>
                        (string) $request
                            ->input(
                                'position_reference',
                                ''
                            ),

                    'is_primary' =>
                        (string) $request
                            ->input(
                                'is_primary',
                                ''
                            )
                        === '1',
                ]
            );


            return
                $response
                    ->redirect(
                        '/admin/profile/affiliation'
                        . '?status=requested'
                    );

        } catch (Throwable $exception) {

            error_log(
                'ORG_AFFILIATION_REQUEST: '
                . $exception
                    ->getMessage()
            );


            $status =
                match (
                    $exception
                        ->getMessage()
                ) {

                    'affiliation_person_required'
                        =>
                            'person_required',

                    'affiliation_already_verified'
                        =>
                            'already_verified',

                    'affiliation_organization_required',
                    'affiliation_organization_invalid'
                        =>
                            'organization_invalid',

                    'affiliation_position_invalid'
                        =>
                            'position_invalid',

                    default
                        =>
                            'request_failed',
                };


            return
                $response
                    ->redirect(
                        '/admin/profile/affiliation'
                        . '?status='
                        . rawurlencode(
                            $status
                        )
                    );
        }
    }
);


/*
 * Organization managers see only memberships allowed by their
 * active scoped organizations.manage assignment.
 */

$router->get(
    '/admin/organization-affiliations',
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
                '/admin/organization-affiliations'
            );


        if (
            !is_array(
                $context
            )
        ) {
            return
                $context;
        }


        try {

            $page =
                (
                    new OrganizationalAffiliationService()
                )->managerPage(
                    (int) $context[
                        'user_id'
                    ],
                    trim(
                        (string) $request
                            ->input(
                                'filter',
                                'pending'
                            )
                    )
                );

        } catch (Throwable $exception) {

            error_log(
                'ORG_AFFILIATION_MANAGER_PAGE: '
                . $exception
                    ->getMessage()
            );


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


        return
            $adminRender(
                $response,
                'organization-affiliations',
                [
                    'title' =>
                        'مدیریت وابستگی‌های سازمانی',

                    'context' =>
                        $context,

                    'page' =>
                        $page,

                    'status' =>
                        trim(
                            (string) $request
                                ->input(
                                    'status',
                                    ''
                                )
                        ),
                ]
            );
    }
);


$router->post(
    '/admin/organization-affiliations/decision',
    function (
        $request,
        $response
    ) use (
        $adminGuard
    ) {

        $context =
            $adminGuard(
                $response,
                '/admin/organization-affiliations/decision'
            );


        if (
            !is_array(
                $context
            )
        ) {
            return
                $context;
        }


        if (
            !(
                new \IPKF\Security\Csrf()
            )->check(
                (string) $request
                    ->input(
                        '_token',
                        ''
                    )
            )
        ) {
            return
                $response
                    ->redirect(
                        '/admin/organization-affiliations'
                        . '?status=invalid_csrf'
                    );
        }


        try {

            $result =
                (
                    new OrganizationalAffiliationService()
                )->decide(
                    (int) $context[
                        'user_id'
                    ],

                    trim(
                        (string) $request
                            ->input(
                                'membership_reference',
                                ''
                            )
                    ),

                    trim(
                        (string) $request
                            ->input(
                                'decision',
                                ''
                            )
                    ),

                    max(
                        0,
                        (int) $request
                            ->input(
                                'role_id',
                                0
                            )
                    ),

                    (string) $request
                        ->input(
                            'include_descendants',
                            ''
                        )
                    === '1',

                    (string) (
                        $_SERVER[
                            'REMOTE_ADDR'
                        ]
                        ?? ''
                    )
                );


            if (
                (string) (
                    $result[
                        'status'
                    ]
                    ?? ''
                )
                === 'rejected'
            ) {

                $status =
                    'rejected';

            } elseif (
                trim(
                    (string) (
                        $result[
                            'access_error'
                        ]
                        ?? ''
                    )
                )
                !== ''
            ) {

                $status =
                    'approved_access_pending';

            } elseif (
                trim(
                    (string) (
                        $result[
                            'access_status'
                        ]
                        ?? ''
                    )
                )
                !== ''
            ) {

                $status =
                    'approved_access_assigned';

            } else {

                $status =
                    'approved';
            }


            return
                $response
                    ->redirect(
                        '/admin/organization-affiliations'
                        . '?status='
                        . rawurlencode(
                            $status
                        )
                    );

        } catch (Throwable $exception) {

            error_log(
                'ORG_AFFILIATION_DECISION: '
                . $exception
                    ->getMessage()
            );


            return
                $response
                    ->redirect(
                        '/admin/organization-affiliations'
                        . '?status=decision_failed'
                    );
        }
    }
);
