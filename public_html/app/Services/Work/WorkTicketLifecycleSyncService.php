<?php

declare(strict_types=1);

namespace App\Services\Work;

use App\Repositories\WorkTicketLifecycleSyncRepository;

/**
 * Pure planning boundary for Work -> Ticket lifecycle synchronization.
 *
 * C3-A1 does NOT execute a Ticket lifecycle transition. It converts dynamic
 * rule rows plus Ticket source links into deterministic plans. The executor
 * will be introduced only after the schema/rule engine is proven in DEV.
 */
final class WorkTicketLifecycleSyncService
{
    /**
     * Canonical action vocabulary exposed by the native Ticket lifecycle API.
     *
     * These are actions, not Work-status mappings.
     */
    private const SUPPORTED_TICKET_ACTIONS = [
        'resolve',
        'close',
        'reopen',
    ];

    public function __construct(
        private ?WorkTicketLifecycleSyncRepository $repository = null
    ) {
        $this->repository ??=
            new WorkTicketLifecycleSyncRepository();
    }

    /**
     * Produce at most one plan per Ticket source link.
     *
     * The highest-priority matching rule wins for a given source link.
     * With zero active rules the result is an empty array: synchronization is
     * disabled by default.
     */
    public function plansForTransition(
        int $workProjectId,
        int $workItemId,
        int $workActivityEventId,
        string $previousWorkStatusCode,
        string $resultingWorkStatusCode,
        string $actorUserReference
    ): array {
        if (
            $workProjectId < 1
            || $workItemId < 1
            || $workActivityEventId < 1
        ) {
            throw new \InvalidArgumentException(
                'work_ticket_lifecycle_sync_identity_invalid'
            );
        }

        $previousWorkStatusCode =
            trim($previousWorkStatusCode);

        $resultingWorkStatusCode =
            trim($resultingWorkStatusCode);

        $actorUserReference =
            trim($actorUserReference);

        if (
            $previousWorkStatusCode === ''
            || $resultingWorkStatusCode === ''
            || $actorUserReference === ''
        ) {
            throw new \InvalidArgumentException(
                'work_ticket_lifecycle_sync_transition_invalid'
            );
        }

        if (
            $previousWorkStatusCode
            === $resultingWorkStatusCode
        ) {
            return [];
        }

        $rules =
            $this->repository
                ->activeRulesForStatus(
                    $workProjectId,
                    $resultingWorkStatusCode
                );

        if ($rules === []) {
            return [];
        }

        $links =
            $this->repository
                ->ticketSourceLinksForItem(
                    $workItemId
                );

        if ($links === []) {
            return [];
        }

        $plans = [];

        foreach ($links as $link) {
            $relationTypeCode =
                trim(
                    (string) (
                        $link['relation_type_code']
                        ?? ''
                    )
                );

            foreach ($rules as $rule) {
                $ruleRelation =
                    trim(
                        (string) (
                            $rule['relation_type_code']
                            ?? ''
                        )
                    );

                if (
                    $ruleRelation !== ''
                    && $ruleRelation !== $relationTypeCode
                ) {
                    continue;
                }

                $action =
                    trim(
                        (string) (
                            $rule['ticket_action_code']
                            ?? ''
                        )
                    );

                if (
                    !in_array(
                        $action,
                        self::SUPPORTED_TICKET_ACTIONS,
                        true
                    )
                ) {
                    throw new \RuntimeException(
                        'work_ticket_lifecycle_sync_action_unsupported'
                    );
                }

                $ruleReference =
                    trim(
                        (string) (
                            $rule['public_reference']
                            ?? ''
                        )
                    );

                $sourceLinkReference =
                    trim(
                        (string) (
                            $link['public_reference']
                            ?? ''
                        )
                    );

                $ticketReference =
                    trim(
                        (string) (
                            $link['source_reference']
                            ?? ''
                        )
                    );

                if (
                    $ruleReference === ''
                    || $sourceLinkReference === ''
                    || $ticketReference === ''
                ) {
                    throw new \RuntimeException(
                        'work_ticket_lifecycle_sync_plan_identity_missing'
                    );
                }

                $idempotencyKey =
                    $this->idempotencyKey(
                        $ruleReference,
                        $workActivityEventId,
                        $sourceLinkReference,
                        $ticketReference,
                        $action
                    );

                $plans[] = [
                    'rule_id' =>
                        (int) $rule['id'],

                    'rule_reference' =>
                        $ruleReference,

                    'work_project_id' =>
                        $workProjectId,

                    'work_item_id' =>
                        $workItemId,

                    'work_activity_event_id' =>
                        $workActivityEventId,

                    'source_link_id' =>
                        (int) $link['id'],

                    'source_link_reference' =>
                        $sourceLinkReference,

                    'ticket_reference' =>
                        $ticketReference,

                    'relation_type_code' =>
                        $relationTypeCode,

                    'previous_work_status_code' =>
                        $previousWorkStatusCode,

                    'resulting_work_status_code' =>
                        $resultingWorkStatusCode,

                    'ticket_action_code' =>
                        $action,

                    'actor_user_reference' =>
                        $actorUserReference,

                    'idempotency_key' =>
                        $idempotencyKey,

                    'correlation_reference' =>
                        $this->correlationReference(
                            $idempotencyKey
                        ),
                ];

                /*
                 * Rules are already priority ordered.
                 * One source Ticket receives at most one lifecycle action per
                 * Work activity event.
                 */
                break;
            }
        }

        return $plans;
    }

    public function idempotencyKey(
        string $ruleReference,
        int $workActivityEventId,
        string $sourceLinkReference,
        string $ticketReference,
        string $ticketActionCode
    ): string {
        $ruleReference =
            trim($ruleReference);

        $sourceLinkReference =
            trim($sourceLinkReference);

        $ticketReference =
            trim($ticketReference);

        $ticketActionCode =
            trim($ticketActionCode);

        if (
            $ruleReference === ''
            || $workActivityEventId < 1
            || $sourceLinkReference === ''
            || $ticketReference === ''
            || !in_array(
                $ticketActionCode,
                self::SUPPORTED_TICKET_ACTIONS,
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'work_ticket_lifecycle_sync_idempotency_input_invalid'
            );
        }

        return hash(
            'sha256',
            implode(
                '|',
                [
                    'ticket-work-lifecycle-sync-v1',
                    $ruleReference,
                    (string) $workActivityEventId,
                    $sourceLinkReference,
                    $ticketReference,
                    $ticketActionCode,
                ]
            )
        );
    }

    private function correlationReference(
        string $idempotencyKey
    ): string {
        return
            'TWLCS-'
            . strtoupper(
                substr(
                    $idempotencyKey,
                    0,
                    24
                )
            );
    }
}
