<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AccessControlRepository;
use App\Repositories\ImpersonationAuthorizationRepository;
use RuntimeException;
use Throwable;

final class ImpersonationOperateGrantService
{
    public const OPERATE_PERMISSION =
        'users.impersonate.operate';

    public const ASSIGN_PERMISSION =
        'users.impersonate.operate.assign';

    private AccessControlRepository
        $accessRepository;

    private ImpersonationAuthorizationRepository
        $authorizationRepository;

    private ImpersonationAuthorizationService
        $authorizationService;


    public function __construct(
        ?AccessControlRepository
            $accessRepository = null,

        ?ImpersonationAuthorizationRepository
            $authorizationRepository = null,

        ?ImpersonationAuthorizationService
            $authorizationService = null
    ) {
        $this->accessRepository =
            $accessRepository
            ?? new AccessControlRepository();

        $this->authorizationRepository =
            $authorizationRepository
            ?? new ImpersonationAuthorizationRepository();

        $this->authorizationService =
            $authorizationService
            ?? new ImpersonationAuthorizationService(
                $this->authorizationRepository
            );
    }


    public function state(
        int $actorUserId,
        int $actorAssignmentId,
        int $targetUserId
    ): array {
        $hidden = [
            'visible' =>
                false,

            'effect' =>
                null,

            'recipient_eligible' =>
                false,

            'base_capability_active' =>
                false,

            'can_grant' =>
                false,

            'can_revoke' =>
                false,
        ];

        if (
            $actorUserId < 1
            || $actorAssignmentId < 1
            || $targetUserId < 1
            || $actorUserId === $targetUserId
        ) {
            return $hidden;
        }

        try {
            $actorAssignment =
                $this->authorizationRepository
                    ->actorAssignment(
                        $actorUserId,
                        $actorAssignmentId
                    );

            if ($actorAssignment === null) {
                return $hidden;
            }

            if (
                !$this->authorizationRepository
                    ->permissionForAssignment(
                        $actorUserId,
                        $actorAssignmentId,
                        self::ASSIGN_PERMISSION
                    )
            ) {
                return $hidden;
            }

            /*
             * Reuse the canonical impersonation hierarchy
             * and scope envelope. Grant authority does not
             * expand the actor's management boundary.
             */
            $decision =
                $this->authorizationService
                    ->decide(
                        $actorUserId,
                        $targetUserId,
                        $actorAssignmentId
                    );

            if (
                !is_array($decision)
                || (
                    $decision['allowed']
                    ?? false
                ) !== true
            ) {
                return $hidden;
            }

            $current =
                $this->accessRepository
                    ->impersonationOperateOverride(
                        $targetUserId
                    );

            $effect =
                in_array(
                    $current['effect_code']
                    ?? null,
                    ['allow', 'deny'],
                    true
                )
                    ? (string) $current[
                        'effect_code'
                    ]
                    : null;

            $baseCapabilityActive =
                $this->baseCapabilityActive(
                    $targetUserId
                );

            return [
                'visible' =>
                    true,

                'effect' =>
                    $effect,

                /*
                 * Entitlement assignment is independent
                 * from current possession of the base
                 * impersonation capability.
                 */
                'recipient_eligible' =>
                    true,

                /*
                 * Informational only. Runtime Start/Guard
                 * still require canonical base
                 * authorization before Operate can be
                 * exercised.
                 */
                'base_capability_active' =>
                    $baseCapabilityActive,

                'can_grant' =>
                    $effect !== 'allow',

                'can_revoke' =>
                    $effect === 'allow',
            ];

        } catch (Throwable) {
            return $hidden;
        }
    }


    public function update(
        int $actorUserId,
        int $actorAssignmentId,
        int $targetUserId,
        string $effect,
        string $reason,
        string $ip
    ): array {
        $effect =
            strtolower(
                trim(
                    $effect
                )
            );

        $reason =
            trim(
                $reason
            );

        if (
            $actorUserId < 1
            || $actorAssignmentId < 1
            || $targetUserId < 1
        ) {
            throw new RuntimeException(
                'impersonation_operate_grant_invalid_request'
            );
        }

        if (
            $actorUserId
            === $targetUserId
        ) {
            throw new RuntimeException(
                'impersonation_operate_self_grant_denied'
            );
        }

        if (
            !in_array(
                $effect,
                ['allow', 'deny'],
                true
            )
        ) {
            throw new RuntimeException(
                'impersonation_operate_effect_invalid'
            );
        }

        if (
            mb_strlen(
                $reason,
                'UTF-8'
            ) < 3
        ) {
            throw new RuntimeException(
                'impersonation_operate_reason_required'
            );
        }

        $state =
            $this->state(
                $actorUserId,
                $actorAssignmentId,
                $targetUserId
            );

        if (
            (
                $state['visible']
                ?? false
            ) !== true
        ) {
            throw new RuntimeException(
                'impersonation_operate_grant_forbidden'
            );
        }


        $this->accessRepository
            ->saveImpersonationOperateOverride(
                $targetUserId,
                $effect,
                $actorUserId,
                $reason,
                $ip
            );

        return [
            'ok' =>
                true,

            'user_id' =>
                $targetUserId,

            'effect' =>
                $effect,
        ];
    }


    private function baseCapabilityActive(
        int $userId
    ): bool {
        $target =
            $this->authorizationRepository
                ->targetUser(
                    $userId
                );

        if (
            !is_array($target)
            || empty(
                $target['eligible']
            )
        ) {
            return false;
        }

        foreach (
            $this->authorizationRepository
                ->activeAssignmentsForUser(
                    $userId
                )
            as $assignment
        ) {
            $assignmentId =
                (int) (
                    $assignment['id']
                    ?? 0
                );

            if ($assignmentId < 1) {
                continue;
            }

            $actorCompatibleAssignment =
                $this->authorizationRepository
                    ->actorAssignment(
                        $userId,
                        $assignmentId
                    );

            if (
                !is_array(
                    $actorCompatibleAssignment
                )
                || empty(
                    $actorCompatibleAssignment[
                        'can_manage_other_users'
                    ]
                )
            ) {
                continue;
            }

            if (
                $this->authorizationRepository
                    ->permissionForAssignment(
                        $userId,
                        $assignmentId,
                        ImpersonationAuthorizationService::PERMISSION
                    )
            ) {
                return true;
            }
        }

        return false;
    }
}
