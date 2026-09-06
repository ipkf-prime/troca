<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;


/*
 * TICKETING_CONTEXT_AWARE_TOPBAR_TARGET_V1
 *
 * Topbar routing is based on the viewer's relationship to
 * the exact source ticket of the latest unread Ticketing
 * notification.
 *
 * Requester:
 *     My Tickets
 *
 * Current staff owner:
 *     Staff cartable / My
 *
 * Scoped non-owner staff:
 *     Staff cartable / All
 *
 * No usable unread notification:
 *     Ticketing home
 */
final class TicketTopbarTargetService
{
    private PDO $core;

    private PDO $ticketing;

    private TicketStaffOperationsService $staff;


    public function __construct(
        ?ConnectionResolver $connections = null,
        ?TicketStaffOperationsService $staff = null
    ) {
        $connections ??=
            new ConnectionResolver();

        $this->core =
            $connections->resolve(
                'core.primary'
            );

        $this->ticketing =
            $connections->resolve(
                'ticketing.primary'
            );

        $this->staff =
            $staff
            ?? new TicketStaffOperationsService();
    }


    public function targetForUser(
        int $userId
    ): array {
        if ($userId < 1) {
            return
                $this->fallback(
                    'invalid_user'
                );
        }


        $notification =
            $this->latestUnreadNotificationContext(
                $userId
            );


        if (!is_array($notification)) {
            return
                $this->fallback(
                    'no_unread_ticketing_notification'
                );
        }


        $ticketReference =
            trim(
                (string) (
                    $notification[
                        'ticket_reference'
                    ]
                    ?? ''
                )
            );


        $notificationReference =
            trim(
                (string) (
                    $notification[
                        'notification_reference'
                    ]
                    ?? ''
                )
            );


        if (
            $ticketReference === ''
            || $notificationReference === ''
        ) {
            return
                $this->fallback(
                    'invalid_notification_context'
                );
        }


        $result =
            $this->targetForTicket(
                $userId,
                $ticketReference
            );


        /*
         * The dispatcher uses this exact reference to consume
         * only the notification that caused the click.
         */
        $result[
            'notification_reference'
        ] =
            $notificationReference;


        return $result;
    }


    public function targetForTicket(
        int $userId,
        string $ticketReference
    ): array {
        $ticketReference =
            trim(
                $ticketReference
            );


        if (
            $userId < 1
            || $ticketReference === ''
        ) {
            return
                $this->fallback(
                    'invalid_ticket_context'
                );
        }


        $ticket =
            $this->ticket(
                $ticketReference
            );


        if (!is_array($ticket)) {
            return
                $this->fallback(
                    'ticket_not_found'
                );
        }


        $userReference =
            'user:' . $userId;


        $ticketNumber =
            trim(
                (string) (
                    $ticket[
                        'ticket_number'
                    ]
                    ?? ''
                )
            );


        $isRequester =
            hash_equals(
                trim(
                    (string) (
                        $ticket[
                            'requester_user_reference'
                        ]
                        ?? ''
                    )
                ),
                $userReference
            );


        $isCurrentAssignee =
            hash_equals(
                trim(
                    (string) (
                        $ticket[
                            'assignee_user_reference'
                        ]
                        ?? ''
                    )
                ),
                $userReference
            );


        /*
         * Requester context has precedence.
         *
         * The same account may have a staff role somewhere else,
         * but its own ticket still belongs in My Tickets.
         */
        if ($isRequester) {

            return [
                'target' =>
                    $this->myTicketsTarget(
                        $ticketNumber
                    ),

                'reason' =>
                    'requester_ticket',

                'notification_reference' =>
                    '',

                'ticket_reference' =>
                    $ticketReference,

                'ticket_number' =>
                    $ticketNumber,

                'is_requester' =>
                    true,

                'is_current_assignee' =>
                    $isCurrentAssignee,

                'staff_can_view' =>
                    false,
            ];
        }


        $staffCanView =
            $this->staff->canViewTicket(
                $ticketReference,
                $userId
            );


        if (!$staffCanView) {
            return
                $this->fallback(
                    'ticket_not_visible',
                    $ticketReference,
                    $ticketNumber
                );
        }


        if ($isCurrentAssignee) {

            return [
                'target' =>
                    $this->staffTarget(
                        'my',
                        $ticketNumber
                    ),

                'reason' =>
                    'current_assignee',

                'notification_reference' =>
                    '',

                'ticket_reference' =>
                    $ticketReference,

                'ticket_number' =>
                    $ticketNumber,

                'is_requester' =>
                    false,

                'is_current_assignee' =>
                    true,

                'staff_can_view' =>
                    true,
            ];
        }


        return [
            'target' =>
                $this->staffTarget(
                    'all',
                    $ticketNumber
                ),

            'reason' =>
                'scoped_non_owner_staff',

            'notification_reference' =>
                '',

            'ticket_reference' =>
                $ticketReference,

            'ticket_number' =>
                $ticketNumber,

            'is_requester' =>
                false,

            'is_current_assignee' =>
                false,

            'staff_can_view' =>
                true,
        ];
    }


    private function latestUnreadNotificationContext(
        int $userId
    ): ?array {
        $statement =
            $this->core->prepare("
                SELECT
                    notifications.public_reference
                        AS notification_reference,

                    events.source_entity_reference
                        AS ticket_reference

                FROM notification_recipients
                    AS recipients

                INNER JOIN notifications
                    AS notifications
                  ON notifications.id =
                        recipients.notification_id

                INNER JOIN notification_events
                    AS events
                  ON events.id =
                        notifications.event_id

                WHERE events.source_module =
                        'ticketing'

                  AND events.source_entity_type =
                        'ticket'

                  AND
                  (
                      recipients.user_id = ?
                      OR recipients.user_reference = ?
                  )

                  AND recipients.read_at IS NULL
                  AND recipients.archived_at IS NULL

                  AND
                  (
                      notifications.expires_at IS NULL
                      OR notifications.expires_at >
                            CURRENT_TIMESTAMP
                  )

                ORDER BY
                    notifications.created_at DESC,
                    notifications.id DESC

                LIMIT 1
            ");

        $statement->execute([
            $userId,
            (string) $userId,
        ]);


        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        if (!is_array($row)) {
            return null;
        }


        $notificationReference =
            trim(
                (string) (
                    $row[
                        'notification_reference'
                    ]
                    ?? ''
                )
            );


        $ticketReference =
            trim(
                (string) (
                    $row[
                        'ticket_reference'
                    ]
                    ?? ''
                )
            );


        if (
            $notificationReference === ''
            || $ticketReference === ''
        ) {
            return null;
        }


        return [
            'notification_reference' =>
                $notificationReference,

            'ticket_reference' =>
                $ticketReference,
        ];
    }


    private function ticket(
        string $ticketReference
    ): ?array {
        $statement =
            $this->ticketing->prepare("
                SELECT
                    t.id,
                    t.ticket_number,
                    t.public_reference,
                    t.requester_user_reference,
                    t.current_assignee_project_member_id,

                    pm.user_reference
                        AS assignee_user_reference

                FROM ticketing_tickets t

                LEFT JOIN ticketing_support_project_members pm
                  ON pm.id =
                        t.current_assignee_project_member_id

                WHERE t.public_reference = ?

                LIMIT 1
            ");

        $statement->execute([
            $ticketReference,
        ]);


        $ticket =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );


        return
            is_array($ticket)
                ? $ticket
                : null;
    }


    private function myTicketsTarget(
        string $ticketNumber
    ): string {
        $target =
            '/admin/ticketing/tickets';


        if ($ticketNumber !== '') {
            $target .=
                '?q='
                . rawurlencode(
                    $ticketNumber
                );
        }


        return $target;
    }


    private function staffTarget(
        string $scope,
        string $ticketNumber
    ): string {
        $scope =
            $scope === 'my'
                ? 'my'
                : 'all';


        $target =
            '/admin/ticketing/staff'
            . '?scope='
            . rawurlencode(
                $scope
            );


        if ($ticketNumber !== '') {
            $target .=
                '&q='
                . rawurlencode(
                    $ticketNumber
                );
        }


        return $target;
    }


    private function fallback(
        string $reason,
        string $ticketReference = '',
        string $ticketNumber = ''
    ): array {
        return [
            'target' =>
                '/admin/ticketing',

            'reason' =>
                $reason,

            'notification_reference' =>
                '',

            'ticket_reference' =>
                $ticketReference,

            'ticket_number' =>
                $ticketNumber,

            'is_requester' =>
                false,

            'is_current_assignee' =>
                false,

            'staff_can_view' =>
                false,
        ];
    }
}
