<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\NotificationInboxService;
use App\Services\NotificationOutboxProcessorService;
use App\Services\NotificationPublisherService;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

final class TicketNotificationService
{
    private const CHANNEL_OPTIONS = [
        'in_app' => [
            'title' => 'پیام داخلی',
            'selectable' => true,
            'state' => 'active',
        ],

        'messenger' => [
            'title' => 'پیام‌رسان',
            'selectable' => false,
            'state' => 'deferred',
        ],

        'sms' => [
            'title' => 'پیامک',
            'selectable' => false,
            'state' => 'deferred',
        ],

        'email' => [
            'title' => 'ایمیل',
            'selectable' => false,
            'state' => 'deferred',
        ],
    ];

    private PDO $core;


    public function __construct(
        private ?NotificationPublisherService $publisher = null,
        private ?NotificationOutboxProcessorService $outbox = null,
        private ?NotificationInboxService $inbox = null,
        ?ConnectionResolver $connections = null
    ) {
        $this->publisher ??=
            new NotificationPublisherService();

        $this->outbox ??=
            new NotificationOutboxProcessorService();

        $this->inbox ??=
            new NotificationInboxService();

        $this->core =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve(
                'core.primary'
            );
    }


    public static function channelOptions(): array
    {
        return self::CHANNEL_OPTIONS;
    }


    public function staffReplied(
        array $event
    ): array {
        return $this->publishInternal(
            $event,
            (string) (
                $event[
                    'requester_user_reference'
                ]
                ?? ''
            ),
            'ticketing.ticket.staff_replied',
            'پاسخ جدید به تیکت',
            'کارشناس پشتیبانی به تیکت شما پاسخ داده است.'
        );
    }


    public function requesterReplied(
        array $event
    ): array {
        return $this->publishInternal(
            $event,
            (string) (
                $event[
                    'assignee_user_reference'
                ]
                ?? ''
            ),
            'ticketing.ticket.requester_replied',
            'پاسخ جدید درخواست‌کننده',
            'درخواست‌کننده به تیکت پاسخ داده است.'
        );
    }


    public function unreadCount(
        int $userId
    ): int {
        if ($userId < 1) {
            return 0;
        }

        try {
            $statement =
                $this->core->prepare("
                    SELECT COUNT(
                        DISTINCT recipients.id
                    )

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
                ");

            $statement->execute([
                $userId,
                (string) $userId,
            ]);

            return max(
                0,
                (int) $statement->fetchColumn()
            );

        } catch (Throwable $exception) {

            error_log(
                'TICKETING_NOTIFICATION_UNREAD_COUNT '
                . get_class($exception)
                . ': '
                . $exception->getMessage()
            );

            return 0;
        }
    }


    public function markViewed(
        int $userId,
        string $ticketReference
    ): int {
        if (
            $userId < 1
            ||
            trim($ticketReference) === ''
        ) {
            return 0;
        }

        return $this->inbox->markActionRead(
            $userId,
            $this->actionUrl(
                $ticketReference
            )
        );
    }


    private function publishInternal(
        array $event,
        string $recipientReference,
        string $eventType,
        string $titlePrefix,
        string $bodyPrefix
    ): array {
        $eventReference =
            trim(
                (string) (
                    $event[
                        'event_reference'
                    ]
                    ?? ''
                )
            );

        $ticketReference =
            trim(
                (string) (
                    $event[
                        'public_reference'
                    ]
                    ?? ''
                )
            );

        if (
            $eventReference === ''
            ||
            $ticketReference === ''
        ) {
            return [
                'status' => 'skipped',
                'reason' =>
                    'ticket_notification_event_invalid',
            ];
        }

        $recipientUserId =
            $this->coreUserId(
                $recipientReference
            );

        if ($recipientUserId === null) {
            return [
                'status' => 'skipped',
                'reason' =>
                    'ticket_notification_recipient_not_core_user',
            ];
        }

        if (
            !$this->inAppEnabled(
                $recipientUserId
            )
        ) {
            return [
                'status' => 'skipped',
                'reason' =>
                    'ticket_notification_in_app_disabled',
                'recipient_user_id' =>
                    $recipientUserId,
            ];
        }

        $ticketNumber =
            trim(
                (string) (
                    $event[
                        'ticket_number'
                    ]
                    ?? ''
                )
            );

        $subject =
            trim(
                (string) (
                    $event[
                        'subject'
                    ]
                    ?? ''
                )
            );

        $actorName =
            trim(
                (string) (
                    $event[
                        'actor_display_name'
                    ]
                    ?? ''
                )
            );

        $title =
            $titlePrefix
            . (
                $ticketNumber !== ''
                    ? ' - ' . $ticketNumber
                    : ''
            );

        $body =
            $bodyPrefix;

        if ($subject !== '') {
            $body .=
                "\nموضوع: "
                . $subject;
        }

        if ($actorName !== '') {
            $body .=
                "\nاقدام‌کننده: "
                . $actorName;
        }

        $actionUrl =
            $this->actionUrl(
                $ticketReference
            );

        try {
            $publish =
                $this->publisher->publish([
                    'event_type' =>
                        $eventType,

                    'source_module' =>
                        'ticketing',

                    'source_entity_type' =>
                        'ticket',

                    'source_entity_reference' =>
                        $ticketReference,

                    'actor_user_reference' =>
                        (string) (
                            $event[
                                'actor_user_reference'
                            ]
                            ?? ''
                        ),

                    'title' =>
                        $title,

                    'body' =>
                        $body,

                    'template_data' => [
                        'ticket_reference' =>
                            $ticketReference,

                        'ticket_number' =>
                            $ticketNumber,

                        'subject' =>
                            $subject,

                        'actor_name' =>
                            $actorName,

                        'action_url' =>
                            $actionUrl,
                    ],

                    'action_url' =>
                        $actionUrl,

                    'category_code' =>
                        'ticketing',

                    'priority_code' =>
                        $this->priority(
                            (string) (
                                $event[
                                    'priority_code'
                                ]
                                ?? 'normal'
                            )
                        ),

                    'recipient_user_references' => [
                        (string) $recipientUserId,
                    ],

                    /*
                     * Current Ticketing phase:
                     * only internal delivery is enabled.
                     */
                    'channels' => [
                        'in_app',
                    ],

                    /*
                     * Ticket event references are unique and
                     * immutable, providing retry idempotency.
                     */
                    'idempotency_key' =>
                        'ticketing:'
                        . $eventReference,
                ]);

            $outbox =
                $this->outbox->process(
                    20,
                    'web:ticketing'
                );

            return [
                'status' => 'published',
                'recipient_user_id' =>
                    $recipientUserId,
                'channel_code' => 'in_app',
                'event_reference' =>
                    $eventReference,
                'publisher_result' =>
                    $publish,
                'outbox_result' =>
                    $outbox,
            ];

        } catch (Throwable $exception) {

            /*
             * The business transaction has already committed.
             * Notification failure must not turn a successful
             * Ticket operation into a false failure.
             */
            error_log(
                'TICKETING_NOTIFICATION_PUBLISH '
                . $eventReference
                . ' '
                . get_class($exception)
                . ': '
                . $exception->getMessage()
            );

            return [
                'status' => 'failed',
                'reason' =>
                    'ticket_notification_publish_failed',
                'event_reference' =>
                    $eventReference,
            ];
        }
    }


    private function inAppEnabled(
        int $userId
    ): bool {
        $statement =
            $this->core->prepare("
                SELECT is_enabled

                FROM notification_preferences

                WHERE user_id = ?
                  AND event_type = '*'
                  AND channel_code = 'in_app'

                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $value =
            $statement->fetchColumn();

        /*
         * No explicit preference means the platform
         * default for the internal channel is ON.
         */
        return
            $value === false
            ||
            (int) $value === 1;
    }


    private function coreUserId(
        string $reference
    ): ?int {
        $reference =
            trim(
                $reference
            );

        if (
            preg_match(
                '/^user:(\d+)$/D',
                $reference,
                $match
            ) !== 1
        ) {
            return null;
        }

        $userId =
            (int) $match[1];

        if ($userId < 1) {
            return null;
        }

        $statement =
            $this->core->prepare("
                SELECT id

                FROM users

                WHERE id = ?

                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $resolved =
            $statement->fetchColumn();

        return
            $resolved === false
                ? null
                : (int) $resolved;
    }


    private function actionUrl(
        string $ticketReference
    ): string {
        return
            '/admin/ticketing/tickets/'
            . rawurlencode(
                trim(
                    $ticketReference
                )
            );
    }


    private function priority(
        string $priority
    ): string {
        $priority =
            strtolower(
                trim(
                    $priority
                )
            );

        return
            in_array(
                $priority,
                [
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ],
                true
            )
                ? $priority
                : 'normal';
    }
}
