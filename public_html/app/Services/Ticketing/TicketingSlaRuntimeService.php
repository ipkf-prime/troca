<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketingSlaRepository;
use App\Repositories\TicketStaffOperationsRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use RuntimeException;

final class TicketingSlaRuntimeService
{
    private TicketingSlaRepository
        $repository;

    private BusinessCalendarService
        $calendar;

    private TicketStaffOperationsRepository
        $staffOperations;


    public function __construct(
        ?TicketingSlaRepository $repository = null,
        ?BusinessCalendarService $calendar = null,
        ?TicketStaffOperationsRepository $staffOperations = null
    ) {
        $this->repository =
            $repository
            ?? new TicketingSlaRepository();

        $this->calendar =
            $calendar
            ?? new BusinessCalendarService();

        $this->staffOperations =
            $staffOperations
            ?? new TicketStaffOperationsRepository();
    }


    public function process(
        int $limit = 200,
        bool $apply = false,
        ?DateTimeImmutable $nowUtc = null
    ): array {
        $now =
            (
                $nowUtc
                ?? new DateTimeImmutable(
                    'now',
                    new DateTimeZone(
                        'UTC'
                    )
                )
            )->setTimezone(
                new DateTimeZone(
                    'UTC'
                )
            );


        $rows =
            $this->repository
                ->runtimeCandidates(
                    $limit
                );


        $result = [
            'scanned' =>
                count($rows),

            'paused' => 0,
            'resumed' => 0,

            'response_met' => 0,
            'response_breached' => 0,

            'resolution_met' => 0,
            'resolution_breached' => 0,

            'auto_escalated' => 0,
            'auto_escalation_blocked' => 0,

            'completed' => 0,

            'items' => [],
        ];


        foreach ($rows as $row) {

            $item =
                $this->processOne(
                    $row,
                    $now,
                    $apply,
                    $result
                );


            $result['items'][] =
                $item;
        }


        return $result;
    }


    private function processOne(
        array $row,
        DateTimeImmutable $now,
        bool $apply,
        array &$result
    ): array {
        $stateId =
            (int) $row['id'];

        $ticketId =
            (int) $row['ticket_id'];

        $ticketReference =
            trim(
                (string) (
                    $row[
                        'ticket_public_reference'
                    ]
                    ?? ''
                )
            );


        if (
            $stateId <= 0
            ||
            $ticketId <= 0
            ||
            $ticketReference === ''
        ) {
            throw new RuntimeException(
                'ticketing_sla_runtime_state_invalid'
            );
        }


        $calendar =
            $this->repository
                ->calendar(
                    (int) $row[
                        'calendar_id'
                    ]
                );


        if ($calendar === null) {
            throw new RuntimeException(
                'ticketing_sla_calendar_missing'
            );
        }


        $pauseStatuses =
            $this->pauseStatuses(
                (string) (
                    $row[
                        'pause_statuses_json'
                    ]
                    ?? '[]'
                )
            );


        $actions = [];


        $responseDue =
            $this->utc(
                (string) $row[
                    'response_due_at'
                ]
            );

        $resolutionDue =
            $this->utc(
                (string) $row[
                    'resolution_due_at'
                ]
            );


        $responseMetAt =
            $this->nullableUtc(
                $row[
                    'response_met_at'
                ]
                ?? null
            );

        $responseBreachedAt =
            $this->nullableUtc(
                $row[
                    'response_breached_at'
                ]
                ?? null
            );

        $resolutionMetAt =
            $this->nullableUtc(
                $row[
                    'resolution_met_at'
                ]
                ?? null
            );

        $resolutionBreachedAt =
            $this->nullableUtc(
                $row[
                    'resolution_breached_at'
                ]
                ?? null
            );

        $pausedAt =
            $this->nullableUtc(
                $row['paused_at']
                ?? null
            );


        /*
         * Terminal lifecycle has precedence over pause/breach.
         */
        $terminal =
            $this->terminalAt(
                $row,
                $now
            );


        if (
            $terminal !== null
            &&
            $resolutionMetAt === null
        ) {
            $late =
                $terminal > $resolutionDue;


            if (
                $late
                &&
                $resolutionBreachedAt
                    === null
            ) {
                $actions[] =
                    'resolution_breached';

                $result[
                    'resolution_breached'
                ]++;


                if (
                    $apply
                    &&
                    $this->repository
                        ->markResolutionBreached(
                            $stateId,
                            $this->formatUtc(
                                $resolutionDue
                            )
                        )
                ) {
                    $this->repository
                        ->recordSlaEvent(
                            $ticketId,
                            $stateId,
                            'sla_resolution_breached',
                            [
                                'resolution_due_at' =>
                                    $this->formatUtc(
                                        $resolutionDue
                                    ),

                                'observed_terminal_at' =>
                                    $this->formatUtc(
                                        $terminal
                                    ),
                            ]
                        );
                }
            }


            $actions[] =
                'resolution_met';

            $result[
                'resolution_met'
            ]++;

            $result['completed']++;


            if (
                $apply
                &&
                $this->repository
                    ->markResolutionMet(
                        $stateId,
                        $this->formatUtc(
                            $terminal
                        )
                    )
            ) {
                $this->repository
                    ->recordSlaEvent(
                        $ticketId,
                        $stateId,
                        'sla_resolution_met',
                        [
                            'resolution_met_at' =>
                                $this->formatUtc(
                                    $terminal
                                ),

                            'late' =>
                                $late,

                            'status_code' =>
                                (string) $row[
                                    'status_code'
                                ],
                        ]
                    );
            }


            return
                $this->item(
                    $row,
                    $actions
                );
        }


        /*
         * First response reconciliation.
         */
        $ticketFirstResponse =
            $this->nullableUtc(
                $row[
                    'first_response_at'
                ]
                ?? null
            );


        if (
            $ticketFirstResponse !== null
            &&
            $responseMetAt === null
        ) {
            $late =
                $ticketFirstResponse
                > $responseDue;


            if (
                $late
                &&
                $responseBreachedAt
                    === null
            ) {
                $actions[] =
                    'response_breached';

                $result[
                    'response_breached'
                ]++;


                if (
                    $apply
                    &&
                    $this->repository
                        ->markResponseBreached(
                            $stateId,
                            $this->formatUtc(
                                $responseDue
                            )
                        )
                ) {
                    $this->repository
                        ->recordSlaEvent(
                            $ticketId,
                            $stateId,
                            'sla_response_breached',
                            [
                                'response_due_at' =>
                                    $this->formatUtc(
                                        $responseDue
                                    ),

                                'first_response_at' =>
                                    $this->formatUtc(
                                        $ticketFirstResponse
                                    ),
                            ]
                        );
                }


                $responseBreachedAt =
                    $responseDue;
            }


            $actions[] =
                'response_met';

            $result[
                'response_met'
            ]++;


            if (
                $apply
                &&
                $this->repository
                    ->markResponseMet(
                        $stateId,
                        $this->formatUtc(
                            $ticketFirstResponse
                        )
                    )
            ) {
                $this->repository
                    ->recordSlaEvent(
                        $ticketId,
                        $stateId,
                        'sla_response_met',
                        [
                            'first_response_at' =>
                                $this->formatUtc(
                                    $ticketFirstResponse
                                ),

                            'late' =>
                                $late,
                        ]
                    );
            }


            $responseMetAt =
                $ticketFirstResponse;
        }


        $statusCode =
            trim(
                (string) $row[
                    'status_code'
                ]
            );


        $shouldPause =
            in_array(
                $statusCode,
                $pauseStatuses,
                true
            );


        /*
         * Enter pause.
         */
        if (
            $shouldPause
            &&
            $pausedAt === null
        ) {
            $actions[] = 'paused';

            $result['paused']++;


            if (
                $apply
                &&
                $this->repository
                    ->pauseState(
                        $stateId,
                        $statusCode,
                        $this->formatUtc(
                            $now
                        )
                    )
            ) {
                $this->repository
                    ->recordSlaEvent(
                        $ticketId,
                        $stateId,
                        'sla_paused',
                        [
                            'status_code' =>
                                $statusCode,

                            'paused_at' =>
                                $this->formatUtc(
                                    $now
                                ),
                        ]
                    );
            }


            return
                $this->item(
                    $row,
                    $actions
                );
        }


        /*
         * Remain paused.
         */
        if (
            $shouldPause
            &&
            $pausedAt !== null
        ) {
            $actions[] =
                'still_paused';


            if ($apply) {
                $this->repository
                    ->touchState(
                        $stateId
                    );
            }


            return
                $this->item(
                    $row,
                    $actions
                );
        }


        /*
         * Resume and move unresolved deadlines by the number
         * of business minutes elapsed during the pause.
         */
        if (
            !$shouldPause
            &&
            $pausedAt !== null
        ) {
            $pauseMinutes =
                $this->calendar
                    ->businessMinutesBetween(
                        $pausedAt,
                        $now,
                        $calendar
                    );


            if ($responseMetAt === null) {
                $responseDue =
                    $this->calendar
                        ->addBusinessMinutes(
                            $responseDue,
                            $pauseMinutes,
                            $calendar
                        );
            }


            if ($resolutionMetAt === null) {
                $resolutionDue =
                    $this->calendar
                        ->addBusinessMinutes(
                            $resolutionDue,
                            $pauseMinutes,
                            $calendar
                        );
            }


            $next =
                $this->nextGoal(
                    $responseMetAt,
                    $resolutionMetAt,
                    $responseDue,
                    $resolutionDue
                );


            $actions[] = 'resumed';

            $result['resumed']++;


            if (
                $apply
                &&
                $this->repository
                    ->resumeState(
                        $stateId,
                        $this->formatUtc(
                            $responseDue
                        ),
                        $this->formatUtc(
                            $resolutionDue
                        ),
                        $pauseMinutes,
                        $next !== null
                            ? $this->formatUtc(
                                $next
                            )
                            : null
                    )
            ) {
                $this->repository
                    ->recordSlaEvent(
                        $ticketId,
                        $stateId,
                        'sla_resumed',
                        [
                            'pause_business_minutes' =>
                                $pauseMinutes,

                            'response_due_at' =>
                                $this->formatUtc(
                                    $responseDue
                                ),

                            'resolution_due_at' =>
                                $this->formatUtc(
                                    $resolutionDue
                                ),
                        ]
                    );
            }


            $pausedAt = null;
        }


        /*
         * Open response breach.
         */
        if (
            $responseMetAt === null
            &&
            $responseBreachedAt === null
            &&
            $now >= $responseDue
        ) {
            $actions[] =
                'response_breached';

            $result[
                'response_breached'
            ]++;


            if (
                $apply
                &&
                $this->repository
                    ->markResponseBreached(
                        $stateId,
                        $this->formatUtc(
                            $responseDue
                        )
                    )
            ) {
                $this->repository
                    ->recordSlaEvent(
                        $ticketId,
                        $stateId,
                        'sla_response_breached',
                        [
                            'response_due_at' =>
                                $this->formatUtc(
                                    $responseDue
                                ),
                        ]
                    );
            }


            $responseBreachedAt =
                $responseDue;
        }


        /*
         * Open resolution breach.
         */
        if (
            $resolutionMetAt === null
            &&
            $resolutionBreachedAt === null
            &&
            $now >= $resolutionDue
        ) {
            $actions[] =
                'resolution_breached';

            $result[
                'resolution_breached'
            ]++;


            if (
                $apply
                &&
                $this->repository
                    ->markResolutionBreached(
                        $stateId,
                        $this->formatUtc(
                            $resolutionDue
                        )
                    )
            ) {
                $this->repository
                    ->recordSlaEvent(
                        $ticketId,
                        $stateId,
                        'sla_resolution_breached',
                        [
                            'resolution_due_at' =>
                                $this->formatUtc(
                                    $resolutionDue
                                ),
                        ]
                    );
            }


            $resolutionBreachedAt =
                $resolutionDue;
        }


        $activeResponseBreach =
            $responseBreachedAt !== null
            &&
            $responseMetAt === null;

        $activeResolutionBreach =
            $resolutionBreachedAt !== null
            &&
            $resolutionMetAt === null;


        $activeBreach =
            $activeResponseBreach
            ||
            $activeResolutionBreach;


        if ($activeBreach) {

            $this->handleEscalation(
                $row,
                $calendar,
                $now,
                $responseMetAt,
                $resolutionMetAt,
                $resolutionDue,
                $apply,
                $actions,
                $result
            );

        } else {

            $next =
                $this->nextGoal(
                    $responseMetAt,
                    $resolutionMetAt,
                    $responseDue,
                    $resolutionDue
                );


            if ($apply) {
                $this->repository
                    ->scheduleNextAction(
                        $stateId,
                        $next !== null
                            ? $this->formatUtc(
                                $next
                            )
                            : null
                    );
            }
        }


        if ($apply) {
            $this->repository
                ->touchState(
                    $stateId
                );
        }


        return
            $this->item(
                $row,
                $actions
            );
    }


    private function handleEscalation(
        array $row,
        array $calendar,
        DateTimeImmutable $now,
        ?DateTimeImmutable $responseMetAt,
        ?DateTimeImmutable $resolutionMetAt,
        DateTimeImmutable $resolutionDue,
        bool $apply,
        array &$actions,
        array &$result
    ): void {
        $stateId =
            (int) $row['id'];

        $ticketId =
            (int) $row['ticket_id'];

        $ticketReference =
            (string) $row[
                'ticket_public_reference'
            ];


        $actionCode =
            trim(
                (string) (
                    $row[
                        'breach_action_code'
                    ]
                    ?? ''
                )
            );


        if ($actionCode !== 'escalate') {

            $actions[] =
                'breach_no_escalation_action';

            return;
        }


        $count =
            (int) (
                $row[
                    'auto_escalation_count'
                ]
                ?? 0
            );

        $max =
            max(
                0,
                (int) (
                    $row[
                        'max_auto_escalations'
                    ]
                    ?? 0
                )
            );


        if ($count >= $max) {

            $actions[] =
                'auto_escalation_limit_reached';


            if ($apply) {
                $this->repository
                    ->scheduleNextAction(
                        $stateId,
                        null
                    );
            }


            return;
        }


        $nextAction =
            $this->nullableUtc(
                $row[
                    'next_action_at'
                ]
                ?? null
            );


        if (
            $nextAction !== null
            &&
            $nextAction > $now
        ) {
            $actions[] =
                'auto_escalation_waiting';

            return;
        }


        $actions[] =
            'auto_escalation';


        if (!$apply) {
            return;
        }


        try {

            $this->staffOperations
                ->escalateSystem(
                    $ticketReference
                );


            $routing =
                $this->repository
                    ->currentTicketRouting(
                        $ticketId
                    );


            $repeatMinutes =
                max(
                    1,
                    (int) (
                        $row[
                            'escalation_repeat_minutes'
                        ]
                        ?? 60
                    )
                );


            $repeatAt =
                $this->calendar
                    ->addBusinessMinutes(
                        $now,
                        $repeatMinutes,
                        $calendar
                    );


            /*
             * If resolution is not breached yet, its due time
             * must still wake the worker before the next repeat
             * escalation when it is earlier.
             */
            if (
                $resolutionMetAt === null
                &&
                $resolutionDue < $repeatAt
            ) {
                $repeatAt =
                    $resolutionDue;
            }


            $nodeId =
                is_array($routing)
                &&
                isset(
                    $routing[
                        'current_support_node_id'
                    ]
                )
                    ? (
                        $routing[
                            'current_support_node_id'
                        ] !== null
                            ? (int) $routing[
                                'current_support_node_id'
                            ]
                            : null
                    )
                    : null;


            if (
                $this->repository
                    ->markAutoEscalated(
                        $stateId,
                        $nodeId,
                        $this->formatUtc(
                            $repeatAt
                        ),
                        $this->formatUtc(
                            $now
                        )
                    )
            ) {
                $this->repository
                    ->recordSlaEvent(
                        $ticketId,
                        $stateId,
                        'sla_auto_escalated',
                        [
                            'auto_escalation_number' =>
                                $count + 1,

                            'target_node_id' =>
                                $nodeId,

                            'next_action_at' =>
                                $this->formatUtc(
                                    $repeatAt
                                ),

                            'ticket_routing' =>
                                $routing,
                        ]
                    );
            }


            $result[
                'auto_escalated'
            ]++;

        } catch (DomainException $exception) {

            $code =
                trim(
                    $exception->getMessage()
                );


            $terminal =
                in_array(
                    $code,
                    [
                        'no_escalation_path',
                        'no_escalation_route',
                    ],
                    true
                );


            $retryAt = null;


            if (!$terminal) {

                $repeatMinutes =
                    max(
                        1,
                        (int) (
                            $row[
                                'escalation_repeat_minutes'
                            ]
                            ?? 60
                        )
                    );


                $retryAt =
                    $this->calendar
                        ->addBusinessMinutes(
                            $now,
                            $repeatMinutes,
                            $calendar
                        );
            }


            /*
             * Even when there is no higher node, an unresolved
             * future resolution deadline must still be observed.
             */
            if (
                $resolutionMetAt === null
                &&
                $resolutionDue > $now
                &&
                (
                    $retryAt === null
                    ||
                    $resolutionDue < $retryAt
                )
            ) {
                $retryAt =
                    $resolutionDue;
            }


            $this->repository
                ->scheduleNextAction(
                    $stateId,
                    $retryAt !== null
                        ? $this->formatUtc(
                            $retryAt
                        )
                        : null
                );


            $this->repository
                ->recordSlaEvent(
                    $ticketId,
                    $stateId,
                    'sla_auto_escalation_blocked',
                    [
                        'reason_code' =>
                            $code,

                        'terminal' =>
                            $terminal,

                        'retry_at' =>
                            $retryAt !== null
                                ? $this->formatUtc(
                                    $retryAt
                                )
                                : null,
                    ]
                );


            $actions[] =
                'auto_escalation_blocked';

            $result[
                'auto_escalation_blocked'
            ]++;
        }
    }


    private function terminalAt(
        array $row,
        DateTimeImmutable $now
    ): ?DateTimeImmutable {
        foreach ([
            'resolved_at',
            'closed_at',
        ] as $field) {

            $value =
                $this->nullableUtc(
                    $row[$field]
                    ?? null
                );


            if ($value !== null) {
                return $value;
            }
        }


        if (
            !empty(
                $row[
                    'status_is_closed'
                ]
            )
        ) {
            $activity =
                $this->nullableUtc(
                    $row[
                        'last_activity_at'
                    ]
                    ?? null
                );


            return
                $activity
                ?? $now;
        }


        return null;
    }


    private function pauseStatuses(
        string $json
    ): array {
        try {

            $decoded =
                json_decode(
                    $json,
                    true,
                    32,
                    JSON_THROW_ON_ERROR
                );

        } catch (\Throwable) {

            return [];
        }


        if (!is_array($decoded)) {
            return [];
        }


        $result = [];


        foreach ($decoded as $status) {

            $status =
                trim(
                    (string) $status
                );


            if ($status !== '') {
                $result[] =
                    $status;
            }
        }


        return
            array_values(
                array_unique(
                    $result
                )
            );
    }


    private function nextGoal(
        ?DateTimeImmutable $responseMetAt,
        ?DateTimeImmutable $resolutionMetAt,
        DateTimeImmutable $responseDue,
        DateTimeImmutable $resolutionDue
    ): ?DateTimeImmutable {
        if ($resolutionMetAt !== null) {
            return null;
        }


        if ($responseMetAt === null) {
            return
                $responseDue
                <= $resolutionDue
                    ? $responseDue
                    : $resolutionDue;
        }


        return $resolutionDue;
    }


    private function nullableUtc(
        mixed $value
    ): ?DateTimeImmutable {
        if ($value === null) {
            return null;
        }


        $value =
            trim(
                (string) $value
            );


        if ($value === '') {
            return null;
        }


        return
            $this->utc(
                $value
            );
    }


    private function utc(
        string $value
    ): DateTimeImmutable {
        $value =
            trim($value);


        if ($value === '') {
            throw new RuntimeException(
                'ticketing_sla_datetime_missing'
            );
        }


        return
            new DateTimeImmutable(
                $value,
                new DateTimeZone(
                    'UTC'
                )
            );
    }


    private function formatUtc(
        DateTimeImmutable $value
    ): string {
        return
            $value
                ->setTimezone(
                    new DateTimeZone(
                        'UTC'
                    )
                )
                ->format(
                    'Y-m-d H:i:s'
                );
    }


    private function item(
        array $row,
        array $actions
    ): array {
        return [
            'state_id' =>
                (int) $row['id'],

            'ticket_id' =>
                (int) $row['ticket_id'],

            'ticket_number' =>
                (string) (
                    $row[
                        'ticket_number'
                    ]
                    ?? ''
                ),

            'status_code' =>
                (string) (
                    $row[
                        'status_code'
                    ]
                    ?? ''
                ),

            'actions' =>
                $actions,
        ];
    }
}
