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
