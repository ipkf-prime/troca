<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ImpersonationAuthorizationRepository;
use IPKF\Support\Session;
use Throwable;

final class ImpersonationSessionLifecycleService
{
    private AuthService $auth;

    private ImpersonationAuthorizationService
        $authorization;

    private ImpersonationAuthorizationRepository
        $authorizationRepository;

    private ImpersonationContextService $context;

    private ?ImpersonationAuditService $audit;


    public function __construct(
        ?AuthService $auth = null,
        ?ImpersonationAuthorizationService
            $authorization = null,
        ?ImpersonationAuthorizationRepository
            $authorizationRepository = null,
        ?ImpersonationContextService $context = null,
        ?ImpersonationAuditService $audit = null
    ) {
        $this->auth =
            $auth
            ?? new AuthService();

        $this->authorization =
            $authorization
            ?? new ImpersonationAuthorizationService();

        $this->authorizationRepository =
            $authorizationRepository
            ?? new ImpersonationAuthorizationRepository();

        $this->context =
            $context
            ?? new ImpersonationContextService();

        /*
         * Keep audit lazy so ordinary non-impersonated
         * requests do not create an audit DB dependency.
         */
        $this->audit =
            $audit;
    }


    public function start(
        int $targetUserId,
        int $actorAssignmentId,
        ?string $returnPath = null
    ): array {
        $existing =
            Session::get(
                ImpersonationContextService::SESSION_KEY
            );

        if ($existing !== null) {
            $state =
                $this->context->inspect(
                    $existing
                );

            $reason =
                !empty($state['active'])
                    ? 'nested_impersonation'
                    : 'existing_impersonation_requires_restore';

            $this->auditBestEffort(
                'impersonation_start_denied',
                (int) (
                    $state['actor_user_id']
                    ?? 0
                ),
                (int) (
                    $state['effective_user_id']
                    ?? 0
                ),
                $reason
            );

            return
                $this->denied(
                    $reason
                );
        }

        /*
         * Capture the session actor first, then force
         * canonical AuthService fingerprint/eligibility
         * validation before authorization is evaluated.
         */
        $actorUserId =
            $this->auth->currentUserId();

        if (
            $actorUserId === null
            || $actorUserId < 1
        ) {
            return
                $this->denied(
                    'actor_not_authenticated'
                );
        }

        if (
            $this->auth->currentUser()
            === null
        ) {
            return
                $this->denyStart(
                    'actor_auth_invalid',
                    $actorUserId,
                    $targetUserId
                );
        }

        if (
            $this->auth->currentUserId()
            !== $actorUserId
        ) {
            return
                $this->denyStart(
                    'actor_identity_changed',
                    $actorUserId,
                    $targetUserId
                );
        }

        $snapshot =
            $this->auth
                ->impersonationAuthSnapshot();

        if (
            (int) (
                $snapshot[
                    'auth_user_id'
                ]
                ?? 0
            ) !== $actorUserId
        ) {
            return
                $this->denyStart(
                    'actor_snapshot_mismatch',
                    $actorUserId,
                    $targetUserId
                );
        }

        $snapshotAssignmentId =
            (int) (
                $snapshot[
                    'active_role_assignment_id'
                ]
                ?? 0
            );

        if (
            $actorAssignmentId < 1
            || $snapshotAssignmentId
                !== $actorAssignmentId
        ) {
            return
                $this->denyStart(
                    'actor_assignment_not_active',
                    $actorUserId,
                    $targetUserId
                );
        }

        $decision =
            $this->authorization
                ->decide(
                    $actorUserId,
                    $targetUserId,
                    $actorAssignmentId
                );

        if (
            !is_array($decision)
            || !array_key_exists(
                'allowed',
                $decision
            )
            || !is_bool(
                $decision['allowed']
            )
        ) {
            return
                $this->denyStart(
                    'authorization_contract_invalid',
                    $actorUserId,
                    $targetUserId
                );
        }

        $reasonCode =
            trim(
                (string) (
                    $decision[
                        'reason_code'
                    ]
                    ?? ''
                )
            );

        if (
            $decision['allowed']
            !== true
        ) {
            return
                $this->denyStart(
                    $reasonCode !== ''
                        ? $reasonCode
                        : 'authorization_denied',
                    $actorUserId,
                    $targetUserId,
                    [
                        'actor_assignment_id' =>
                            $actorAssignmentId,
                    ]
                );
        }

        $targetAssignments =
            $this->authorizationRepository
                ->activeAssignmentsForUser(
                    $targetUserId
                );

        if ($targetAssignments === []) {
            return
                $this->denyStart(
                    'target_assignment_missing',
                    $actorUserId,
                    $targetUserId
                );
        }

        $targetAssignmentId =
            (int) (
                $targetAssignments[0]['id']
                ?? 0
            );

        if ($targetAssignmentId < 1) {
            return
                $this->denyStart(
                    'target_assignment_invalid',
                    $actorUserId,
                    $targetUserId
                );
        }

        $context =
            $this->context->create(
                $actorUserId,
                $targetUserId,
                $snapshot,
                $this->nonce(),
                $returnPath
            );

        /*
         * Restore envelope must be present before
         * effective authentication identity changes.
         */
        Session::put(
            ImpersonationContextService::SESSION_KEY,
            $context
        );

        try {
            $switched =
                $this->auth
                    ->beginImpersonatedIdentity(
                        $targetUserId,
                        $targetAssignmentId
                    );
        } catch (Throwable $exception) {
            $this->rollbackStart(
                $snapshot
            );

            return
                $this->denyStart(
                    'session_switch_failed',
                    $actorUserId,
                    $targetUserId
                );
        }

        if (!$switched) {
            Session::forget(
                ImpersonationContextService::SESSION_KEY
            );

            return
                $this->denyStart(
                    'target_session_not_eligible',
                    $actorUserId,
                    $targetUserId
                );
        }

        if (
            $this->auth->currentUserId()
            !== $targetUserId
        ) {
            $this->rollbackStart(
                $snapshot
            );

            return
                $this->denyStart(
                    'effective_identity_mismatch',
                    $actorUserId,
                    $targetUserId
                );
        }

        /*
         * A successful privilege-boundary switch must
         * not survive without its mandatory audit pair.
         */
        try {
            $this->audit()->record(
                'impersonation_started',
                $actorUserId,
                $targetUserId,
                null,
                [
                    'actor_assignment_id' =>
                        $actorAssignmentId,

                    'effective_assignment_id' =>
                        $targetAssignmentId,

                    'expires_at' =>
                        (string) (
                            $context[
                                'expires_at'
                            ]
                            ?? ''
                        ),
                ]
            );
        } catch (Throwable $exception) {
            $this->rollbackStart(
                $snapshot
            );

            return
                $this->denied(
                    'impersonation_audit_failed'
                );
        }

        return [
            'ok' =>
                true,

            'action' =>
                'started',

            'actor_user_id' =>
                $actorUserId,

            'effective_user_id' =>
                $targetUserId,

            'active_role_assignment_id' =>
                $targetAssignmentId,

            'expires_at' =>
                (string) (
                    $context[
                        'expires_at'
                    ]
                    ?? ''
                ),

            'return_path' =>
                (string) (
                    $context[
                        'return_path'
                    ]
                    ?? '/admin/users'
                ),
        ];
    }


    public function status(): array
    {
        $raw =
            Session::get(
                ImpersonationContextService::SESSION_KEY
            );

        if ($raw === null) {
            return [
                'valid' =>
                    true,

                'active' =>
                    false,

                'expired' =>
                    false,
            ];
        }

        return
            $this->context->inspect(
                $raw
            );
    }


    public function restore(): array
    {
        $raw =
            Session::get(
                ImpersonationContextService::SESSION_KEY
            );

        if ($raw === null) {
            return
                $this->denied(
                    'no_active_impersonation'
                );
        }

        $state =
            $this->context->inspect(
                $raw
            );

        if (empty($state['valid'])) {
            return
                $this->terminateInvalidContext(
                    'invalid_impersonation_context'
                );
        }

        return
            $this->restoreState(
                $state,
                false
            );
    }


    /**
     * This method is intended for the central HTTP
     * impersonation guard and therefore validates both
     * expiry and the current effective credential.
     */
    public function enforceExpiry(): array
    {
        $raw =
            Session::get(
                ImpersonationContextService::SESSION_KEY
            );

        if ($raw === null) {
            return [
                'ok' =>
                    true,

                'active' =>
                    false,

                'action' =>
                    'none',
            ];
        }

        $state =
            $this->context->inspect(
                $raw
            );

        if (empty($state['valid'])) {
            return
                $this->terminateInvalidContext(
                    'invalid_impersonation_context'
                );
        }

        if (!empty($state['expired'])) {
            return
                $this->restoreState(
                    $state,
                    true
                );
        }

        if (!empty($state['active'])) {
            $effectiveUserId =
                (int) (
                    $state[
                        'effective_user_id'
                    ]
                    ?? 0
                );

            if (
                $effectiveUserId < 1
                || $this->auth
                    ->currentUserId()
                    !== $effectiveUserId
            ) {
                return
                    $this->terminateInvalidContext(
                        'effective_identity_changed'
                    );
            }

            /*
             * currentUser() revalidates Target
             * eligibility + password fingerprint and
             * destroys the Session on failure.
             */
            if (
                $this->auth->currentUser()
                === null
            ) {
                $this->auditBestEffort(
                    'impersonation_restore_denied',
                    (int) (
                        $state[
                            'actor_user_id'
                        ]
                        ?? 0
                    ),
                    $effectiveUserId,
                    'effective_auth_invalid'
                );

                return [
                    'ok' =>
                        false,

                    'active' =>
                        false,

                    'action' =>
                        'session_terminated',

                    'reason' =>
                        'effective_auth_invalid',

                    'session_terminated' =>
                        true,
                ];
            }

            return [
                'ok' =>
                    true,

                'active' =>
                    true,

                'action' =>
                    'active',

                'actor_user_id' =>
                    (int) (
                        $state[
                            'actor_user_id'
                        ]
                        ?? 0
                    ),

                'effective_user_id' =>
                    $effectiveUserId,

                'expires_at' =>
                    (string) (
                        $state[
                            'expires_at'
                        ]
                        ?? ''
                    ),
            ];
        }

        return
            $this->terminateInvalidContext(
                'invalid_impersonation_state'
            );
    }


    private function restoreState(
        array $state,
        bool $expired
    ): array {
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

        $snapshot =
            is_array(
                $state[
                    'actor_auth_snapshot'
                ]
                ?? null
            )
                ? $state[
                    'actor_auth_snapshot'
                ]
                : [];

        if (
            $actorUserId < 1
            || $effectiveUserId < 1
            || $actorUserId
                === $effectiveUserId
        ) {
            return
                $this->terminateInvalidContext(
                    'invalid_impersonation_identity_pair'
                );
        }

        if (
            $this->auth->currentUserId()
            !== $effectiveUserId
        ) {
            return
                $this->restoreDenied(
                    $state,
                    'effective_identity_changed'
                );
        }

        /*
         * Target credential/eligibility changes are a
         * fail-closed boundary. currentUser() destroys
         * the active session when validation fails.
         */
        if (
            $this->auth->currentUser()
            === null
        ) {
            $this->auditBestEffort(
                'impersonation_restore_denied',
                $actorUserId,
                $effectiveUserId,
                'effective_auth_invalid',
                [
                    'expired' =>
                        $expired,
                ]
            );

            return [
                'ok' =>
                    false,

                'active' =>
                    false,

                'action' =>
                    'session_terminated',

                'reason' =>
                    'effective_auth_invalid',

                'session_terminated' =>
                    true,
            ];
        }

        $actorAssignmentId =
            (int) (
                $snapshot[
                    'active_role_assignment_id'
                ]
                ?? 0
            );

        if ($actorAssignmentId < 1) {
            return
                $this->restoreDenied(
                    $state,
                    'actor_assignment_missing'
                );
        }

        $actorAssignment =
            $this->authorizationRepository
                ->actorAssignment(
                    $actorUserId,
                    $actorAssignmentId
                );

        if ($actorAssignment === null) {
            return
                $this->restoreDenied(
                    $state,
                    'actor_assignment_no_longer_valid'
                );
        }

        $restored =
            $this->auth
                ->restoreImpersonationAuthSnapshot(
                    $snapshot
                );

        if (!$restored) {
            return
                $this->restoreDenied(
                    $state,
                    'actor_auth_restore_failed'
                );
        }

        /*
         * Keep the context until the required end/expiry
         * audit is durably recorded. If audit fails,
         * terminate the newly restored Actor session.
         */
        try {
            $this->audit()->record(
                $expired
                    ? 'impersonation_expired'
                    : 'impersonation_ended',
                $actorUserId,
                $effectiveUserId,
                $expired
                    ? 'ttl_expired'
                    : null,
                [
                    'actor_assignment_id' =>
                        $actorAssignmentId,

                    'expired' =>
                        $expired,
                ]
            );
        } catch (Throwable $exception) {
            $this->auth->logout();

            return [
                'ok' =>
                    false,

                'active' =>
                    false,

                'action' =>
                    'session_terminated',

                'reason' =>
                    'impersonation_audit_failed',

                'session_terminated' =>
                    true,
            ];
        }

        Session::forget(
            ImpersonationContextService::SESSION_KEY
        );

        return [
            'ok' =>
                true,

            'active' =>
                false,

            'action' =>
                $expired
                    ? 'expired_restored'
                    : 'restored',

            'actor_user_id' =>
                $actorUserId,

            'effective_user_id' =>
                $effectiveUserId,

            'return_path' =>
                (string) (
                    $state[
                        'return_path'
                    ]
                    ?? '/admin/users'
                ),
        ];
    }


    private function restoreDenied(
        array $state,
        string $reason
    ): array {
        $this->auditBestEffort(
            'impersonation_restore_denied',
            (int) (
                $state[
                    'actor_user_id'
                ]
                ?? 0
            ),
            (int) (
                $state[
                    'effective_user_id'
                ]
                ?? 0
            ),
            $reason
        );

        return
            $this->terminateInvalidContext(
                $reason
            );
    }


    private function denyStart(
        string $reason,
        int $actorUserId,
        int $effectiveUserId,
        array $metadata = []
    ): array {
        $this->auditBestEffort(
            'impersonation_start_denied',
            $actorUserId,
            $effectiveUserId,
            $reason,
            $metadata
        );

        return
            $this->denied(
                $reason
            );
    }


    private function auditBestEffort(
        string $eventCode,
        int $actorUserId,
        int $effectiveUserId,
        ?string $reasonCode = null,
        array $metadata = []
    ): void {
        /*
         * Denied operations remain denied even if the
         * audit backend itself is unavailable.
         *
         * Successful privilege-boundary transitions use
         * mandatory audit instead of this helper.
         */
        if (
            $actorUserId < 1
            || $effectiveUserId < 1
        ) {
            return;
        }

        try {
            $this->audit()->record(
                $eventCode,
                $actorUserId,
                $effectiveUserId,
                $reasonCode,
                $metadata
            );
        } catch (Throwable $exception) {
            /*
             * Fail closed is already satisfied because
             * the requested privileged action is denied.
             */
        }
    }


    private function audit(): ImpersonationAuditService
    {
        if ($this->audit === null) {
            $this->audit =
                new ImpersonationAuditService();
        }

        return
            $this->audit;
    }


    private function rollbackStart(
        array $snapshot
    ): void {
        Session::forget(
            ImpersonationContextService::SESSION_KEY
        );

        try {
            if (
                !$this->auth
                    ->restoreImpersonationAuthSnapshot(
                        $snapshot
                    )
            ) {
                $this->auth->logout();
            }
        } catch (Throwable $exception) {
            $this->auth->logout();
        }
    }


    private function terminateInvalidContext(
        string $reason
    ): array {
        Session::forget(
            ImpersonationContextService::SESSION_KEY
        );

        $this->auth->logout();

        return [
            'ok' =>
                false,

            'active' =>
                false,

            'action' =>
                'session_terminated',

            'reason' =>
                $reason,

            'session_terminated' =>
                true,
        ];
    }


    private function denied(
        string $reason
    ): array {
        return [
            'ok' =>
                false,

            'reason' =>
                $reason,
        ];
    }


    private function nonce(): string
    {
        return
            rtrim(
                strtr(
                    base64_encode(
                        random_bytes(24)
                    ),
                    '+/',
                    '-_'
                ),
                '='
            );
    }
}
