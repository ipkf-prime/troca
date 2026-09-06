<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketStaffOperationsRepository;
use App\Support\TicketingDisplay;
use DomainException;

final class TicketStaffOperationsService
{
    public function __construct(
        private ?TicketStaffOperationsRepository $repository = null
    ) {
        $this->repository =
            $this->repository
            ?? new TicketStaffOperationsRepository();
    }


    public function page(
        int $userId,
        array $context = [],
        array $filters = []
    ): array {
        $userReference =
            'user:' . $userId;

        $scope =
            trim(
                (string) (
                    $filters['scope']
                    ?? 'all'
                )
            );

        if (
            !in_array(
                $scope,
                [
                    'all',
                    'my',
                    'unassigned',
                ],
                true
            )
        ) {
            $scope = 'all';
        }


        $query =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );


        $rows =
            $this->repository->cartable(
                $userReference,
                $scope
            );


        if ($query !== '') {
            $rows =
                array_values(
                    array_filter(
                        $rows,
                        fn (
                            array $ticket
                        ): bool =>
                            $this->matchesQuery(
                                $ticket,
                                $query
                            )
                    )
                );
        }


        foreach ($rows as &$ticket) {
            $ticket['staff_actions'] =
                $this->repository
                    ->actionContext(
                        (int) $ticket['id'],
                        $userReference
                    );
        }

        unset($ticket);


        return [
            'viewer_user_reference' =>
                'user:' . $userId,

            'is_staff' =>
                $this->repository->isStaff(
                    $userReference
                ),

            'items' =>
                $rows,

            'scope' =>
                $scope,

            'q' =>
                $query,

            'counts' => [
                'all' =>
                    count(
                        $this->repository
                            ->cartable(
                                $userReference,
                                'all'
                            )
                    ),

                'my' =>
                    count(
                        $this->repository
                            ->cartable(
                                $userReference,
                                'my'
                            )
                    ),

                'unassigned' =>
                    count(
                        $this->repository
                            ->cartable(
                                $userReference,
                                'unassigned'
                            )
                    ),
            ],
        ];
    }


    /*
     * TICKETING_STAFF_DETAIL_VISIBILITY_V1
     *
     * Read visibility follows the canonical staff cartable
     * and is intentionally independent from reply ownership.
     */
    public function canViewTicket(
        string $publicReference,
        int $userId
    ): bool {
        $reference =
            trim(
                $publicReference
            );

        if (
            $reference === ''
            || $userId < 1
        ) {
            return false;
        }


        $rows =
            $this->repository->cartable(
                'user:' . $userId,
                'all'
            );


        foreach ($rows as $ticket) {

            if (
                trim(
                    (string) (
                        $ticket[
                            'public_reference'
                        ]
                        ?? ''
                    )
                )
                === $reference
            ) {
                return true;
            }
        }


        return false;
    }


    public function takeOver(
        string $publicReference,
        int $userId,
        array $context = []
    ): array {
        try {

            $this->repository->takeOver(
                trim($publicReference),
                'user:' . $userId,
                $this->actorDisplayName(
                    $context,
                    $userId
                )
            );

            return [
                'ok' => true,
                'status' =>
                    'taken-over',
            ];

        } catch (DomainException $exception) {
            return [
                'ok' => false,
                'status' =>
                    $this->errorStatus(
                        $exception->getMessage()
                    ),
            ];
        }
    }


    public function transfer(
        string $publicReference,
        int $targetProjectMemberId,
        int $userId,
        array $context = []
    ): array {
        try {

            $this->repository->transfer(
                trim($publicReference),
                $targetProjectMemberId,
                'user:' . $userId,
                $this->actorDisplayName(
                    $context,
                    $userId
                )
            );

            return [
                'ok' => true,
                'status' =>
                    'transferred',
            ];

        } catch (DomainException $exception) {
            return [
                'ok' => false,
                'status' =>
                    $this->errorStatus(
                        $exception->getMessage()
                    ),
            ];
        }
    }


    public function escalate(
        string $publicReference,
        int $userId,
        array $context = []
    ): array {
        try {

            $this->repository->escalate(
                trim($publicReference),
                'user:' . $userId,
                $this->actorDisplayName(
                    $context,
                    $userId
                )
            );

            return [
                'ok' => true,
                'status' =>
                    'escalated',
            ];

        } catch (DomainException $exception) {
            return [
                'ok' => false,
                'status' =>
                    $this->errorStatus(
                        $exception->getMessage()
                    ),
            ];
        }
    }


    private function matchesQuery(
        array $ticket,
        string $query
    ): bool {
        $query =
            trim($query);

        if ($query === '') {
            return true;
        }


        $latin =
            TicketingDisplay::latinDigits(
                $query
            );


        if (
            preg_match(
                '/^0*(\d{1,18})$/',
                $latin,
                $match
            ) === 1
        ) {
            $sequence =
                (int) $match[1];

            if (
                $sequence > 0
                &&
                preg_match(
                    '/(\d+)$/',
                    (string) (
                        $ticket[
                            'ticket_number'
                        ]
                        ?? ''
                    ),
                    $numberMatch
                ) === 1
            ) {
                return
                    (int) $numberMatch[1]
                    ===
                    $sequence;
            }
        }


        $displayNumber =
            TicketingDisplay
                ::ticketNumberFromRow(
                    $ticket
                );


        $haystack =
            implode(
                ' ',
                [
                    $displayNumber,

                    (string) (
                        $ticket[
                            'ticket_number'
                        ]
                        ?? ''
                    ),

                    (string) (
                        $ticket[
                            'subject'
                        ]
                        ?? ''
                    ),

                    (string) (
                        $ticket[
                            'support_topic_title_snapshot'
                        ]
                        ?? ''
                    ),

                    (string) (
                        $ticket[
                            'support_project_title_snapshot'
                        ]
                        ?? ''
                    ),

                    (string) (
                        $ticket[
                            'project_title'
                        ]
                        ?? ''
                    ),

                    (string) (
                        $ticket[
                            'assignee_name'
                        ]
                        ?? ''
                    ),

                    (string) (
                        $ticket[
                            'requester_display_name_snapshot'
                        ]
                        ?? ''
                    ),
                ]
            );


        return
            mb_stripos(
                $haystack,
                $query,
                0,
                'UTF-8'
            ) !== false
            ||
            mb_stripos(
                TicketingDisplay::latinDigits(
                    $haystack
                ),
                $latin,
                0,
                'UTF-8'
            ) !== false;
    }


    private function actorDisplayName(
        array $context,
        int $userId
    ): string {
        $userReference =
            'user:' . $userId;


        /*
         * Ticketing project membership is the authoritative
         * display-name snapshot for Ticketing audit events.
         */
        $membershipDisplayName =
            trim(
                (string) (
                    $this->repository
                        ->displayNameForUserReference(
                            $userReference
                        )
                    ?? ''
                )
            );


        if ($membershipDisplayName !== '') {
            return
                $membershipDisplayName;
        }


        /*
         * Keep Admin context as a secondary fallback for
         * non-standard integrations.
         */
        foreach ([
            'display_name',
            'user_display_name',
            'full_name',
            'name',
        ] as $key) {

            $value =
                trim(
                    (string) (
                        $context[$key]
                        ?? ''
                    )
                );

            if ($value !== '') {
                return $value;
            }
        }


        /*
         * Last-resort technical fallback. This should not be
         * reached for active Ticketing staff members.
         */
        return
            'کاربر '
            . $userId;
    }


    private function errorStatus(
        string $code
    ): string {
        $map = [
            'ticket_not_found' =>
                'not-found',

            'ticket_closed' =>
                'closed',

            'ticket_not_routed' =>
                'not-routed',

            'not_allowed' =>
                'forbidden',

            'already_owner' =>
                'already-owner',

            'target_invalid' =>
                'invalid-target',

            'same_assignee' =>
                'same-assignee',

            'no_escalation_path' =>
                'no-escalation',

            'no_escalation_route' =>
                'no-escalation-route',

            'no_assignee' =>
                'no-assignee',
        ];

        return
            $map[$code]
            ?? 'operation-failed';
    }
}
