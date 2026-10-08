<?php

declare(strict_types=1);

namespace IPKF\Http\Middleware;

use App\Repositories\ImpersonationAuthorizationRepository;
use App\Services\ImpersonationAuditService;
use App\Services\ImpersonationAuthorizationService;
use App\Services\ImpersonationContextService;
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


    private const OPERATE_PERMISSION =
        'users.impersonate.operate';


    private const SENSITIVE_PREFIXES = [
        '/admin/users',
        '/admin/access',
        '/admin/roles',
        '/admin/permissions',
        '/admin/security',
        '/admin/settings',
        '/admin/system',
        '/auth/password',
        '/auth/mfa',
        '/auth/otp',
        '/admin/impersonation',
    ];


    private const SENSITIVE_SEGMENTS = [
        'password',
        'mfa',
        'otp',
        'recovery',
        'role',
        'roles',
        'permission',
        'permissions',
        'access',
        'security',
        'impersonation',
    ];


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

        $mode =
            strtolower(
                trim(
                    (string) (
                        $state[
                            'mode'
                        ]
                        ?? ImpersonationContextService::MODE_OBSERVE
                    )
                )
            );

        if (
            $mode
            === ImpersonationContextService::MODE_OPERATE
        ) {
            return
                $this->operate(
                    $request,
                    $response,
                    $next,
                    $state,
                    $actorUserId,
                    $effectiveUserId,
                    $method,
                    $path
                );
        }

        /*
         * Keep literal blocked audit before literal 403.
         * This preserves the S4B ordering contract.
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
                        'mode' =>
                            $mode !== ''
                                ? $mode
                                : ImpersonationContextService::MODE_OBSERVE,

                        'http_method' =>
                            $method,

                        'request_path' =>
                            $path,
                    ]
                );

            } catch (Throwable) {
                /*
                 * Fail closed.
                 */
            }
        }

        if ($this->browserNavigation()) {
            return
                $response->redirect(
                    $this->statusUrl(
                        $this->safeReturnPath(),
                        'readonly_blocked'
                    )
                );
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


    private function operate(
        Request $request,
        Response $response,
        callable $next,
        array $state,
        int $actorUserId,
        int $effectiveUserId,
        string $method,
        string $path
    ): Response {
        $actorAssignmentId =
            (int) (
                $state[
                    'actor_assignment_id'
                ]
                ?? 0
            );

        $allowed =
            false;

        try {
            if (
                $actorUserId > 0
                && $effectiveUserId > 0
                && $actorAssignmentId > 0
            ) {
                $repository =
                    new ImpersonationAuthorizationRepository();

                $actorAssignment =
                    $repository->actorAssignment(
                        $actorUserId,
                        $actorAssignmentId
                    );

                $operatePermission =
                    $repository->permissionForAssignment(
                        $actorUserId,
                        $actorAssignmentId,
                        self::OPERATE_PERMISSION
                    );

                $decision =
                    (
                        new ImpersonationAuthorizationService()
                    )->decide(
                        $actorUserId,
                        $effectiveUserId,
                        $actorAssignmentId
                    );

                $allowed =
                    $actorAssignment !== null
                    && $operatePermission
                    && is_array($decision)
                    && (
                        $decision[
                            'allowed'
                        ]
                        ?? false
                    ) === true;
            }

        } catch (Throwable) {
            $allowed =
                false;
        }

        if (!$allowed) {
            $this->auditBlocked(
                $actorUserId,
                $effectiveUserId,
                'operate_authorization_invalid',
                [
                    'mode' =>
                        ImpersonationContextService::MODE_OPERATE,

                    'actor_assignment_id' =>
                        $actorAssignmentId,

                    'http_method' =>
                        $method,

                    'request_path' =>
                        $path,
                ]
            );

            return
                $this->blocked(
                    $response,
                    'operate_denied',
                    'impersonation_operation_not_authorized',
                    403
                );
        }

        if ($this->sensitiveMutation($path)) {
            $this->auditBlocked(
                $actorUserId,
                $effectiveUserId,
                'sensitive_operation_during_impersonation',
                [
                    'mode' =>
                        ImpersonationContextService::MODE_OPERATE,

                    'actor_assignment_id' =>
                        $actorAssignmentId,

                    'http_method' =>
                        $method,

                    'request_path' =>
                        $path,
                ]
            );

            return
                $this->blocked(
                    $response,
                    'sensitive_blocked',
                    'impersonation_mutation_blocked',
                    403
                );
        }

        /*
         * Mandatory attempt audit before Business write.
         */
        try {
            (
                new ImpersonationAuditService()
            )->record(
                'impersonation_operation_attempted',
                $actorUserId,
                $effectiveUserId,
                null,
                [
                    'mode' =>
                        ImpersonationContextService::MODE_OPERATE,

                    'actor_assignment_id' =>
                        $actorAssignmentId,

                    'http_method' =>
                        $method,

                    'request_path' =>
                        $path,
                ]
            );

        } catch (Throwable) {

            return
                $this->blocked(
                    $response,
                    'audit_unavailable',
                    'impersonation_audit_unavailable',
                    503
                );
        }

        try {
            $result =
                $next(
                    $request,
                    $response
                );

        } catch (Throwable $exception) {

            $this->auditCompleted(
                $actorUserId,
                $effectiveUserId,
                $actorAssignmentId,
                $method,
                $path,
                500,
                'downstream_exception'
            );

            throw $exception;
        }

        $statusCode =
            $result->statusCode();

        $this->auditCompleted(
            $actorUserId,
            $effectiveUserId,
            $actorAssignmentId,
            $method,
            $path,
            $statusCode,
            $statusCode >= 400
                ? 'downstream_http_error'
                : null
        );

        return $result;
    }


    private function auditBlocked(
        int $actorUserId,
        int $effectiveUserId,
        string $reason,
        array $metadata
    ): void {
        if (
            $actorUserId < 1
            || $effectiveUserId < 1
        ) {
            return;
        }

        try {
            (
                new ImpersonationAuditService()
            )->record(
                'impersonation_mutation_blocked',
                $actorUserId,
                $effectiveUserId,
                $reason,
                $metadata
            );

        } catch (Throwable) {
            /*
             * Denial remains closed.
             */
        }
    }


    private function auditCompleted(
        int $actorUserId,
        int $effectiveUserId,
        int $actorAssignmentId,
        string $method,
        string $path,
        int $httpStatus,
        ?string $reason
    ): void {
        try {
            (
                new ImpersonationAuditService()
            )->record(
                'impersonation_operation_completed',
                $actorUserId,
                $effectiveUserId,
                $reason,
                [
                    'mode' =>
                        ImpersonationContextService::MODE_OPERATE,

                    'actor_assignment_id' =>
                        $actorAssignmentId,

                    'http_method' =>
                        $method,

                    'request_path' =>
                        $path,

                    'http_status' =>
                        $httpStatus,
                ]
            );

        } catch (Throwable) {
            /*
             * Attempt audit already persisted.
             */
        }
    }


    private function sensitiveMutation(
        string $path
    ): bool {
        $path =
            strtolower(
                trim(
                    $path
                )
            );

        foreach (
            self::SENSITIVE_PREFIXES
            as $prefix
        ) {
            if (
                $path === $prefix
                || str_starts_with(
                    $path,
                    $prefix . '/'
                )
            ) {
                return true;
            }
        }

        $segments =
            array_values(
                array_filter(
                    explode(
                        '/',
                        trim(
                            $path,
                            '/'
                        )
                    )
                )
            );

        foreach (
            self::SENSITIVE_SEGMENTS
            as $segment
        ) {
            if (
                in_array(
                    $segment,
                    $segments,
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }


    private function blocked(
        Response $response,
        string $uiStatus,
        string $apiCode,
        int $apiStatus
    ): Response {
        if ($this->browserNavigation()) {
            return
                $response->redirect(
                    $this->statusUrl(
                        $this->safeReturnPath(),
                        $uiStatus
                    )
                );
        }

        return
            $response
                ->status(
                    $apiStatus
                )
                ->json([
                    'status' =>
                        'error',

                    'code' =>
                        $apiCode,
                ]);
    }


    private function browserNavigation(): bool
    {
        $requestedWith =
            strtolower(
                trim(
                    (string) (
                        $_SERVER[
                            'HTTP_X_REQUESTED_WITH'
                        ]
                        ?? ''
                    )
                )
            );

        if ($requestedWith === 'xmlhttprequest') {
            return false;
        }

        $fetchMode =
            strtolower(
                trim(
                    (string) (
                        $_SERVER[
                            'HTTP_SEC_FETCH_MODE'
                        ]
                        ?? ''
                    )
                )
            );

        $accept =
            strtolower(
                (string) (
                    $_SERVER[
                        'HTTP_ACCEPT'
                    ]
                    ?? ''
                )
            );

        return
            $fetchMode === 'navigate'
            || str_contains(
                $accept,
                'text/html'
            );
    }


    private function safeReturnPath(): string
    {
        $referer =
            trim(
                (string) (
                    $_SERVER[
                        'HTTP_REFERER'
                    ]
                    ?? ''
                )
            );

        if ($referer === '') {
            return '/admin';
        }

        $parts =
            parse_url(
                $referer
            );

        if ($parts === false) {
            return '/admin';
        }

        $path =
            (string) (
                $parts[
                    'path'
                ]
                ?? '/admin'
            );

        if (
            $path === ''
            || !str_starts_with(
                $path,
                '/'
            )
            || str_starts_with(
                $path,
                '//'
            )
        ) {
            return '/admin';
        }

        $query = [];

        if (
            isset(
                $parts[
                    'query'
                ]
            )
        ) {
            parse_str(
                (string) $parts[
                    'query'
                ],
                $query
            );
        }

        unset(
            $query[
                'impersonation_status'
            ]
        );

        return
            $path
            . (
                $query !== []
                    ? '?'
                        . http_build_query(
                            $query
                        )
                    : ''
            );
    }


    private function statusUrl(
        string $path,
        string $status
    ): string {
        $parts =
            parse_url(
                $path
            );

        if ($parts === false) {
            $parts = [];
        }

        $localPath =
            (string) (
                $parts[
                    'path'
                ]
                ?? '/admin'
            );

        if (
            $localPath === ''
            || !str_starts_with(
                $localPath,
                '/'
            )
            || str_starts_with(
                $localPath,
                '//'
            )
        ) {
            $localPath =
                '/admin';
        }

        $query = [];

        if (
            isset(
                $parts[
                    'query'
                ]
            )
        ) {
            parse_str(
                (string) $parts[
                    'query'
                ],
                $query
            );
        }

        $query[
            'impersonation_status'
        ] = $status;

        return
            $localPath
            . '?'
            . http_build_query(
                $query
            );
    }
}