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


    /*
     * TICKETING_PROJECT_LOCAL_EFFECTIVE_ACCESS_SERVICE_V1
     */
    public function isStaff(
        int $userId
    ): bool {
        return
            $userId > 0
            &&
            $this->repository
                ->isStaff(
                    'user:' . $userId
                );
    }


    public function accessScopes(
        int $userId
    ): array {
        if ($userId < 1) {
            return [];
        }

        return
            $this->repository
                ->staffAccessScopes(
                    'user:' . $userId
                );
    }


    /*
     * Dashboard is deliberately summary-only.
     * Ticket rows and operational actions belong to cartable.
     */
    public function dashboard(
        int $userId
    ): array {
        $userReference =
            'user:' . $userId;

        $statusCounts =
            $this->repository
                ->dashboardStatusCounts(
                    $userReference
                );


        return [
            'viewer_user_reference' =>
                $userReference,

            'is_staff' =>
                $this->repository->isStaff(
                    $userReference
                ),

            /*
             * Keep the complete status map available for
             * future dashboard KPIs without changing the
             * repository contract again.
             */
            'status_counts' =>
                $statusCounts,

            /*
             * "Open" intentionally means NEW here.
             * This keeps the four dashboard cards mutually
             * exclusive instead of overlapping with
             * in_progress / waiting statuses.
             */
            'kpis' => [
                'open' =>
                    (int) (
                        $statusCounts['new']
                        ?? 0
                    ),

                'in_progress' =>
                    (int) (
                        $statusCounts[
                            'in_progress'
                        ]
                        ?? 0
                    ),

                'resolved' =>
                    (int) (
                        $statusCounts[
                            'resolved'
                        ]
                        ?? 0
                    ),

                'closed' =>
                    (int) (
                        $statusCounts[
                            'closed'
                        ]
                        ?? 0
                    ),
            ],
        ];
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
            $scope =
                'all';
        }


        $query =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        if (
            mb_strlen(
                $query,
                'UTF-8'
            ) > 180
        ) {
            $query =
                mb_substr(
                    $query,
                    0,
                    180,
                    'UTF-8'
                );
        }


        $ticketStatus =
            trim(
                (string) (
                    $filters[
                        'ticket_status'
                    ]
                    ?? 'active'
                )
            );

        if ($ticketStatus === '') {
            $ticketStatus =
                'active';
        }

        if (
            !in_array(
                $ticketStatus,
                [
                    'active',
                    'all',
                ],
                true
            )
            &&
            preg_match(
                '/^[a-zA-Z0-9_.:-]{1,80}$/',
                $ticketStatus
            ) !== 1
        ) {
            $ticketStatus =
                'active';
        }


        $priority =
            trim(
                (string) (
                    $filters[
                        'priority'
                    ]
                    ?? ''
                )
            );

        if (
            $priority !== ''
            &&
            preg_match(
                '/^[a-zA-Z0-9_.:-]{1,80}$/',
                $priority
            ) !== 1
        ) {
            $priority =
                '';
        }


        $topicId =
            max(
                0,
                (int) (
                    $filters[
                        'topic_id'
                    ]
                    ?? 0
                )
            );


        $layerId =
            max(
                0,
                (int) (
                    $filters[
                        'layer_id'
                    ]
                    ?? 0
                )
            );


        $assignee =
            trim(
                (string) (
                    $filters[
                        'assignee'
                    ]
                    ?? ''
                )
            );

        if (
            strlen($assignee)
            > 180
        ) {
            $assignee =
                substr(
                    $assignee,
                    0,
                    180
                );
        }

        if ($scope === 'unassigned') {
            $assignee =
                '';
        }


        $sort =
            trim(
                (string) (
                    $filters[
                        'sort'
                    ]
                    ?? 'priority_desc'
                )
            );

        if (
            !in_array(
                $sort,
                [
                    'priority_desc',
                    'activity_desc',
                    'activity_asc',
                    'created_desc',
                    'created_asc',
                ],
                true
            )
        ) {
            $sort =
                'priority_desc';
        }


        $pageNumber =
            max(
                1,
                (int) (
                    $filters[
                        'page'
                    ]
                    ?? 1
                )
            );


        $perPage =
            (int) (
                $filters[
                    'per_page'
                ]
                ?? 25
            );

        if (
            !in_array(
                $perPage,
                [
                    25,
                    50,
                ],
                true
            )
        ) {
            $perPage =
                25;
        }


        $repositoryFilters = [
            'scope' =>
                $scope,

            /*
             * Presentation may accept Persian/Arabic digits while the
             * database search contract uses normalized Latin digits.
             */
            'q' =>
                TicketingDisplay::latinDigits(
                    $query
                ),

            'ticket_status' =>
                $ticketStatus,

            'priority' =>
                $priority,

            'topic_id' =>
                $topicId,

            'layer_id' =>
                $layerId,

            'assignee' =>
                $assignee,

            'sort' =>
                $sort,

            'page' =>
                $pageNumber,

            'per_page' =>
                $perPage,
        ];


        $result =
            $this->repository
                ->cartablePage(
                    $userReference,
                    $repositoryFilters
                );

        $rows =
            is_array(
                $result['items']
                ?? null
            )
                ? $result['items']
                : [];


        /*
         * ActionContext remains per-row for this stage, but pagination
         * bounds it to 25/50 rows instead of an unbounded cartable.
         * A later batch optimization can replace this without changing
         * the page/filter contract.
         */
        foreach ($rows as &$ticket) {
            $ticket['staff_actions'] =
                $this->repository
                    ->actionContext(
                        (int) $ticket['id'],
                        $userReference
                    );
        }

        unset($ticket);


        /*
         * TICKETING_CARTABLE_SUMMARY_COUNTS_V1
         *
         * Summary cards describe the operator's complete active
         * operational workload.
         *
         * They are intentionally independent from the list filters:
         * - text search
         * - explicit ticket status
         * - priority
         * - support stage
         * - assignee
         * - sorting
         * - pagination
         *
         * Clicking a summary card changes only the list scope.
         * It must never redefine the numbers shown by the cards.
         */
        $summaryFilters = [
            'ticket_status' =>
                'active',

            'q' =>
                '',

            'priority' =>
                '',

            'topic_id' =>
                0,

            'layer_id' =>
                0,

            'assignee' =>
                '',
        ];


        $counts = [];

        foreach (
            [
                'all',
                'my',
                'unassigned',
            ]
            as $countScope
        ) {
            $scopeFilters =
                $summaryFilters;

            $scopeFilters['scope'] =
                $countScope;

            $counts[$countScope] =
                $this->repository
                    ->cartableCount(
                        $userReference,
                        $scopeFilters
                    );
        }


        return [
            'viewer_user_reference' =>
                $userReference,

            'is_staff' =>
                $this->repository
                    ->isStaff(
                        $userReference
                    ),

            'items' =>
                $rows,

            'scope' =>
                $scope,

            'q' =>
                $query,

            'filters' => [
                'scope' =>
                    $scope,

                'q' =>
                    $query,

                'ticket_status' =>
                    $ticketStatus,

                'priority' =>
                    $priority,

                'topic_id' =>
                    $topicId,

                'layer_id' =>
                    $layerId,

                'assignee' =>
                    $assignee,

                'sort' =>
                    $sort,

                'page' =>
                    (int) (
                        $result['page']
                        ?? 1
                    ),

                'per_page' =>
                    (int) (
                        $result['per_page']
                        ?? $perPage
                    ),
            ],

            'pagination' => [
                'total' =>
                    (int) (
                        $result['total']
                        ?? 0
                    ),

                'page' =>
                    (int) (
                        $result['page']
                        ?? 1
                    ),

                'per_page' =>
                    (int) (
                        $result['per_page']
                        ?? $perPage
                    ),

                'total_pages' =>
                    (int) (
                        $result['total_pages']
                        ?? 1
                    ),
            ],

            'filter_options' =>
                $this->repository
                    ->cartableFilterOptions(
                        $userReference
                    ),

            'counts' =>
                $counts,
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
                'all',
                $reference
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



    /*
     * TICKETING_OPERATIONAL_DETAIL_HISTORY_T3C1
     *
     * Reuses the canonical cartable visibility primitive and the
     * exact existing ActionContext contract. No new business action
     * or authorization rule is introduced by Detail.
     */
    public function detailContext(
        string $publicReference,
        int $userId
    ): array {
        $reference =
            trim(
                $publicReference
            );

        $empty = [
            'visible' => false,
            'ticket' => [],
            'actions' => [
                'can_takeover' => false,
                'can_transfer' => false,
                'can_escalate' => false,
                'transfer_targets' => [],
                'escalation_target_title' => '',
            ],
        ];

        if (
            $reference === ''
            || $userId < 1
        ) {
            return $empty;
        }

        $userReference =
            'user:' . $userId;

        $rows =
            $this->repository->cartable(
                $userReference,
                'all',
                $reference
            );

        if ($rows === []) {
            return $empty;
        }

        $ticket =
            is_array($rows[0])
                ? $rows[0]
                : [];

        if (
            trim(
                (string) (
                    $ticket[
                        'public_reference'
                    ]
                    ?? ''
                )
            ) !== $reference
        ) {
            return $empty;
        }

        $ticketId =
            (int) (
                $ticket['id']
                ?? 0
            );

        if ($ticketId < 1) {
            return $empty;
        }

        return [
            'visible' => true,
            'ticket' => $ticket,
            'actions' =>
                $this->repository
                    ->actionContext(
                        $ticketId,
                        $userReference
                    ),
        ];
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
