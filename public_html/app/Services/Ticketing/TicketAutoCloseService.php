<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketAutoClosePolicyRepository;
use App\Repositories\TicketLifecycleTransitionRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class TicketAutoCloseService
{
    private const SYSTEM_ACTOR =
        'system:ticketing-auto-close';

    private const SYSTEM_DISPLAY_NAME =
        'بستن خودکار سامانه';


    public function __construct(
        private ?TicketAutoClosePolicyRepository $policies = null,
        private ?TicketLifecycleTransitionRepository $lifecycle = null
    ) {
        $this->policies ??=
            new TicketAutoClosePolicyRepository();

        $this->lifecycle ??=
            new TicketLifecycleTransitionRepository();
    }


    public function process(
        int $projectId,
        int $limit = 100
    ): array {
        if ($projectId < 1) {
            throw new RuntimeException(
                'auto_close_project_invalid'
            );
        }

        $limit =
            max(
                1,
                min(
                    100,
                    $limit
                )
            );

        $policy =
            $this->policies
                ->policyForProject(
                    $projectId
                );

        $summary = [
            'project_id' =>
                $projectId,

            'policy_exists' =>
                !empty(
                    $policy['exists']
                ),

            'policy_enabled' =>
                !empty(
                    $policy['is_enabled']
                ),

            'policy_id' =>
                (int) (
                    $policy['id']
                    ?? 0
                ),

            'delay_hours' =>
                $policy['delay_hours']
                ?? null,

            'eligible_resolved_from' =>
                $policy[
                    'eligible_resolved_from'
                ]
                ?? null,

            'candidate_count' => 0,
            'closed_count' => 0,
            'skipped_count' => 0,
        ];

        /*
         * Safe default.
         *
         * Scheduler may invoke this Job, but without
         * explicit business-policy enablement the Job
         * is guaranteed to be a no-op.
         */
        if (
            empty(
                $policy['is_enabled']
            )
        ) {
            $summary['status'] =
                'policy_disabled';

            return $summary;
        }

        $delayHours =
            (int) (
                $policy['delay_hours']
                ?? 0
            );

        if ($delayHours < 1) {
            throw new RuntimeException(
                'auto_close_policy_delay_invalid'
            );
        }

        $eligibleResolvedFrom =
            trim(
                (string) (
                    $policy[
                        'eligible_resolved_from'
                    ]
                    ?? ''
                )
            );

        if ($eligibleResolvedFrom === '') {
            throw new RuntimeException(
                'auto_close_policy_eligibility_missing'
            );
        }

        $utc =
            new DateTimeZone(
                'UTC'
            );

        /*
         * Validate the policy boundary before
         * issuing any candidate query.
         */
        try {
            new DateTimeImmutable(
                $eligibleResolvedFrom,
                $utc
            );
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'auto_close_policy_eligibility_invalid',
                0,
                $exception
            );
        }

        $cutoff =
            (
                new DateTimeImmutable(
                    'now',
                    $utc
                )
            )
                ->modify(
                    '-'
                    . $delayHours
                    . ' hours'
                )
                ->format(
                    'Y-m-d H:i:s'
                );

        $summary['cutoff_at'] =
            $cutoff;

        $candidates =
            $this->policies
                ->candidates(
                    $projectId,
                    $eligibleResolvedFrom,
                    $cutoff,
                    $limit
                );

        $summary['candidate_count'] =
            count(
                $candidates
            );

        foreach ($candidates as $candidate) {
            $publicReference =
                trim(
                    (string) (
                        $candidate[
                            'public_reference'
                        ]
                        ?? ''
                    )
                );

            if ($publicReference === '') {
                $summary[
                    'skipped_count'
                ]++;

                continue;
            }

            try {
                $this->lifecycle
                    ->closeResolvedBySystem(
                        $publicReference,
                        $projectId,
                        self::SYSTEM_ACTOR,
                        self::SYSTEM_DISPLAY_NAME,
                        (int) (
                            $policy['id']
                            ?? 0
                        ),
                        $delayHours,
                        $eligibleResolvedFrom,
                        $cutoff
                    );

                $summary[
                    'closed_count'
                ]++;

            } catch (RuntimeException $exception) {
                /*
                 * Benign concurrent state changes:
                 * a human may close/reopen/move the ticket
                 * between candidate selection and row lock.
                 */
                if (
                    in_array(
                        $exception->getMessage(),
                        [
                            'ticket_not_found',
                            'auto_close_ticket_not_resolved',
                            'auto_close_ticket_not_due',
                            'auto_close_transition_conflict',
                        ],
                        true
                    )
                ) {
                    $summary[
                        'skipped_count'
                    ]++;

                    continue;
                }

                throw $exception;
            }
        }

        $summary['status'] =
            'processed';

        return $summary;
    }
}
