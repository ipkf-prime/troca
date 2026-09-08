<?php

declare(strict_types=1);

/*
 * TICKETING_AUTO_CLOSE_POLICY_ADMIN_ROUTE_V1
 *
 * The route deliberately reuses the canonical
 * Support Project administration RBAC path:
 *
 *     /admin/ticketing/projects
 *
 * No new guessed permission is introduced.
 */

$autoClosePolicyRedirect =
    static function (
        $response,
        string $projectReference,
        string $status
    ) {
        return
            $response->redirect(
                '/admin/ticketing/projects/'
                . rawurlencode(
                    $projectReference
                )
                . '/edit'
                . '?tab=auto-close'
                . '&status='
                . rawurlencode(
                    $status
                )
            );
    };


$router->post(
    '/admin/ticketing/projects/{public_reference}/auto-close',
    function (
        $request,
        $response
    ) use (
        $adminGuard,
        $autoClosePolicyRedirect
    ) {
        /*
         * Reuse the existing Project-management
         * authorization contract.
         */
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

        if ($reference === '') {
            return
                $response->status(
                    404
                )->send(
                    '404 - Project not found'
                );
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
                $autoClosePolicyRedirect(
                    $response,
                    $reference,
                    'invalid_csrf'
                );
        }


        try {
            $result =
                (
                    new \App\Services\Ticketing\TicketAutoClosePolicyAdminService()
                )->save(
                    $reference,
                    [
                        'is_enabled' =>
                            $request->input(
                                'is_enabled',
                                0
                            ),

                        'delay_hours' =>
                            $request->input(
                                'delay_hours',
                                ''
                            ),
                    ],
                    (int) $context[
                        'user_id'
                    ]
                );


            return
                $autoClosePolicyRedirect(
                    $response,
                    $reference,
                    trim(
                        (string) (
                            $result[
                                'status'
                            ]
                            ?? 'auto_close_error'
                        )
                    )
                );

        } catch (\Throwable $exception) {

            error_log(
                'IPKF_TICKETING_AUTO_CLOSE_POLICY_ADMIN '
                . get_class(
                    $exception
                )
                . ': '
                . $exception->getMessage()
            );

            return
                $autoClosePolicyRedirect(
                    $response,
                    $reference,
                    'auto_close_error'
                );
        }
    }
);
