<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketingSlaRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class TicketingSlaService
{
    private TicketingSlaRepository
        $repository;

    private BusinessCalendarService
        $calendar;


    public function __construct(
        ?TicketingSlaRepository $repository = null,
        ?BusinessCalendarService $calendar = null
    ) {
        $this->repository =
            $repository
            ?? new TicketingSlaRepository();

        $this->calendar =
            $calendar
            ?? new BusinessCalendarService();
    }


    public function initializeEligible(
        int $limit = 100,
        bool $apply = false
    ): array {
        $candidates =
            $this->repository
                ->initializationCandidates(
                    $limit
                );


        $result = [
            'scanned' =>
                count($candidates),

            'eligible' =>
                0,

            'initialized' =>
                0,

            'skipped' =>
                0,

            'items' =>
                [],
        ];


        foreach ($candidates as $ticket) {

            $prepared =
                $this->prepareState(
                    $ticket
                );


            if ($prepared === null) {

                $result['skipped']++;

                continue;
            }


            $result['eligible']++;

            $result['items'][] = [
                'ticket_id' =>
                    (int) $ticket['id'],

                'ticket_number' =>
                    (string) (
                        $ticket[
                            'ticket_number'
                        ]
                        ?? ''
                    ),

                'priority_code' =>
                    (string) $prepared[
                        'priority_code'
                    ],

                'policy_scope_key' =>
                    (string) $prepared[
                        'policy_scope_key'
                    ],

                'response_due_at' =>
                    (string) $prepared[
                        'response_due_at'
                    ],

                'resolution_due_at' =>
                    (string) $prepared[
                        'resolution_due_at'
                    ],
            ];


            if (!$apply) {
                continue;
            }


            if (
                $this->repository
                    ->createState(
                        $prepared
                    )
            ) {
                $result['initialized']++;
            }
        }


        return $result;
    }


    private function prepareState(
        array $ticket
    ): ?array {
        $policy =
            $this->repository
                ->policyForTicket(
                    $ticket
                );


        if ($policy === null) {
            return null;
        }


        $calendar =
            $this->repository
                ->calendar(
                    (int) $policy[
                        'calendar_id'
                    ]
                );


        if ($calendar === null) {
            throw new RuntimeException(
                'ticketing_sla_calendar_missing'
            );
        }


        $createdAt =
            $this->utc(
                (string) $ticket[
                    'created_at'
                ]
            );


        $responseMinutes =
            (int) $policy[
                'response_minutes'
            ];

        $resolutionMinutes =
            (int) $policy[
                'resolution_minutes'
            ];


        $responseDue =
            $this->calendar
                ->addBusinessMinutes(
                    $createdAt,
                    $responseMinutes,
                    $calendar
                );


        $resolutionDue =
            $this->calendar
                ->addBusinessMinutes(
                    $createdAt,
                    $resolutionMinutes,
                    $calendar
                );


        $responseDueText =
            $this->formatUtc(
                $responseDue
            );

        $resolutionDueText =
            $this->formatUtc(
                $resolutionDue
            );


        return [
            'ticket_id' =>
                (int) $ticket['id'],

            'policy_id' =>
                (int) $policy['id'],

            'calendar_id' =>
                (int) $policy[
                    'calendar_id'
                ],

            'policy_scope_key' =>
                (string) $policy[
                    'scope_key'
                ],

            'priority_code' =>
                (string) $ticket[
                    'priority_code'
                ],

            'response_minutes' =>
                $responseMinutes,

            'resolution_minutes' =>
                $resolutionMinutes,

            'response_started_at' =>
                $this->formatUtc(
                    $createdAt
                ),

            'response_due_at' =>
                $responseDueText,

            'resolution_started_at' =>
                $this->formatUtc(
                    $createdAt
                ),

            'resolution_due_at' =>
                $resolutionDueText,

            'next_action_at' =>
                $responseDue <= $resolutionDue
                    ? $responseDueText
                    : $resolutionDueText,
        ];
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
}
