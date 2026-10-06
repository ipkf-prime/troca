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

    /*
     * Only explicitly proven mutation control endpoints
     * may appear here.
     */
    private const MUTATION_ALLOWLIST = [
        'POST /auth/logout',
    ];

    /*
     * Both logout surfaces ultimately destroy the
     * current host-scoped authentication session.
     *
     * They therefore must close an active impersonation
     * lifecycle before the logout route itself executes.
     */
    private const FINAL_LOGOUT_ENDPOINTS = [
        'GET /admin/logout',
        'POST /auth/logout',
    ];


    public function handle(
        Request $request,
        Response $response,
        callable $next
    ): Response {
        $lifecycle =
            new ImpersonationSessionLifecycleService();

        /*
         * Expiry and Effective User credential
         * validation apply to every HTTP method.
         */
        $state =
            $lifecycle->enforceExpiry();

        $method =
            strtoupper(
                $request->method()
            );

        /*
         * Normalize to path only. This is essential for
         * federated GET /admin/logout requests carrying
         * logout_step, return_module or return_path.
         */
        $rawUri =
            (string) $request->uri();

        $parsedPath =
            parse_url(
                $rawUri,
                PHP_URL_PATH
            );

        $path =
            is_string($parsedPath)
            && $parsedPath !== ''
                ? $parsedPath
                : '/';

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

        $action =
            (string) (
                $state['action']
                ?? ''
            );

        /*
         * An automatic Effective -> Actor transition
         * must never allow an arbitrary request to
         * continue as Actor.
         *
         * Final logout is the sole exception because
         * the destination immediately destroys the
         * restored Actor session.
         */
        if ($action === 'expired_restored') {
            if ($isFinalLogout) {
                return
                    $next(
                        $request,
                        $response
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

        /*
         * Invalid impersonation state already caused
         * fail-closed local session termination.
         *
         * A requested final logout may still continue so
         * the existing federated logout chain can clear
         * the remaining application-host sessions.
         */
        if (
            !empty(
                $state[
                    'session_terminated'
                ]
            )
        ) {
            if ($isFinalLogout) {
                return
                    $next(
                        $request,
                        $response
                    );
            }

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

        /*
         * Final logout is a control operation, not a
         * business mutation.
         *
         * Close impersonation first so
         * impersonation_ended is durably audited before
         * either logout route destroys the session.
         */
        if ($isFinalLogout) {
            $restored =
                $lifecycle->restore();

            if (
                !empty(
                    $restored['ok']
                )
                && (
                    $restored['action']
                    ?? ''
                ) === 'restored'
            ) {
                return
                    $next(
                        $request,
                        $response
                    );
            }

            /*
             * restoreDenied()/invalid context paths
             * already terminate the local session.
             * Continuing is safe only because the target
             * is an exact final-logout endpoint.
             */
            if (
                !empty(
                    $restored[
                        'session_terminated'
                    ]
                )
            ) {
                return
                    $next(
                        $request,
                        $response
                    );
            }

            return
                $response
                    ->status(409)
                    ->json([
                        'status' =>
                            'error',

                        'code' =>
                            'impersonation_logout_restore_failed',
                    ]);
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

        /*
         * Exact method + path matching prevents another
         * verb on an allow-listed path from becoming
         * write-enabled accidentally.
         */
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
         * Audit failure never converts a blocked
         * mutation into an allowed mutation.
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
                 * The deny decision is independent from
                 * audit backend availability.
                 */
            }
        }

        /*
         * Machine-readable only. UI text remains
         * dynamically resolved by the presentation
         * layer.
         */
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
