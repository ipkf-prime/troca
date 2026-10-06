<?php

declare(strict_types=1);

namespace IPKF\Http\Middleware;

use App\Services\ImpersonationAuditService;
use App\Services\ImpersonationSessionLifecycleService;
use IPKF\Http\Request;
use IPKF\Http\Response;
use Throwable;

final class ImpersonationMutationGuardMiddleware
{
    private const MUTATING_METHODS = [
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
    ];


    private const MUTATION_ALLOWLIST = [
        'POST /auth/logout',
        'POST /admin/impersonation/stop',
    ];


    private const FINAL_LOGOUT_ENDPOINTS = [
        'GET /admin/logout',
        'POST /auth/logout',
    ];


    private const EXPLICIT_STOP_ENDPOINT =
        'POST /admin/impersonation/stop';


    public function handle(
        Request $request,
        Response $response,
        callable $next
    ): Response {
        $lifecycle =
            new ImpersonationSessionLifecycleService();

        $method =
            strtoupper(
                $request->method()
            );

        /*
         * Request::uri() already returns the normalized
         * path without query-string material.
         */
        $path =
            $request->uri();

        $requestKey =
            $method
            . ' '
            . $path;

        $isFinalLogout =
            in_array(
                $requestKey,
                self::FINAL_LOGOUT_ENDPOINTS,
                true
            );

        /*
         * Final logout is terminal. It must be handled
         * before expiry enforcement, otherwise an expired
         * impersonation could restore Actor immediately
         * before logout.
         */
        if ($isFinalLogout) {
            $lifecycle
                ->terminateForLogout();

            return
                $next(
                    $request,
                    $response
                );
        }

        /*
         * Explicit Stop owns CSRF + nonce + restore
         * validation inside its route/lifecycle contract.
         * Do not auto-restore before that nonce is checked.
         */
        if (
            $requestKey
            === self::EXPLICIT_STOP_ENDPOINT
        ) {
            return
                $next(
                    $request,
                    $response
                );
        }

        /*
         * All ordinary requests enforce TTL and Effective
         * credential validity centrally.
         */
        $state =
            $lifecycle->enforceExpiry();

        $action =
            (string) (
                $state[
                    'action'
                ]
                ?? ''
            );

        /*
         * Never continue the same business request after
         * automatic Effective -> Actor restoration.
         */
        if ($action === 'expired_restored') {
            if (
                $method === 'GET'
                || $method === 'HEAD'
            ) {
                $returnPath =
                    trim(
                        (string) (
                            $state[
                                'return_path'
                            ]
                            ?? '/admin'
                        )
                    );

                if (
                    $returnPath === ''
                    || !str_starts_with(
                        $returnPath,
                        '/'
                    )
                    || str_starts_with(
                        $returnPath,
                        '//'
                    )
                ) {
                    $returnPath =
                        '/admin';
                }

                $separator =
                    str_contains(
                        $returnPath,
                        '?'
                    )
                        ? '&'
                        : '?';

                return
                    $response->redirect(
                        $returnPath
                        . $separator
                        . 'impersonation_status=expired'
                    );
            }

            return
                $response
                    ->status(409)
                    ->json([
                        'status' =>
                            'error',

                        'code' =>
                            'impersonation_expired_restored',
                    ]);
        }

        if (
            !empty(
                $state[
                    'session_terminated'
                ]
            )
        ) {
            return
                $response
                    ->status(401)
                    ->json([
                        'status' =>
                            'error',

                        'code' =>
                            'impersonation_session_terminated',
                    ]);
        }

        if (
            empty(
                $state[
                    'active'
                ]
            )
        ) {
            return
                $next(
                    $request,
                    $response
                );
        }

        if (
            !in_array(
                $method,
                self::MUTATING_METHODS,
                true
            )
        ) {
            return
                $next(
                    $request,
                    $response
                );
        }

        if (
            in_array(
                $requestKey,
                self::MUTATION_ALLOWLIST,
                true
            )
        ) {
            return
                $next(
                    $request,
                    $response
                );
        }

        $actorUserId =
            (int) (
                $state[
                    'actor_user_id'
                ]
                ?? 0
            );

        $effectiveUserId =
            (int) (
                $state[
                    'effective_user_id'
                ]
                ?? 0
            );

        /*
         * Audit storage failure never grants write
         * permission.
         */
        if (
            $actorUserId > 0
            && $effectiveUserId > 0
        ) {
            try {
                (
                    new ImpersonationAuditService()
                )->record(
                    'impersonation_mutation_blocked',
                    $actorUserId,
                    $effectiveUserId,
                    'read_only_impersonation',
                    [
                        'http_method' =>
                            $method,

                        'request_path' =>
                            $path,
                    ]
                );
            } catch (Throwable $exception) {
                /*
                 * Fail closed: mutation remains denied.
                 */
            }
        }

        return
            $response
                ->status(403)
                ->json([
                    'status' =>
                        'error',

                    'code' =>
                        'impersonation_mutation_blocked',
                ]);
    }
}
