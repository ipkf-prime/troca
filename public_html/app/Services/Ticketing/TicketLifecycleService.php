<?php

namespace App\Services\Ticketing;

use App\Repositories\TicketLifecycleRepository;
use RuntimeException;

final class TicketLifecycleService
{
    public function __construct(
        private ?TicketLifecycleRepository $tickets = null
    ) {
        $this->tickets ??=
            new TicketLifecycleRepository();
    }


    public function staffReply(
        string $publicReference,
        string $body,
        int $userId,
        array $context = [],
        array $files = []
    ): array {
        $publicReference =
            trim($publicReference);

        if (
            $publicReference === ''
            || $userId < 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'reply_invalid',
            ];
        }

        /*
         * Preserve the exact body for storage.
         * Only the blank check uses trim().
         */
        if (trim($body) === '') {
            return [
                'ok' => false,
                'status' =>
                    'reply_empty',
            ];
        }

        $length =
            function_exists('mb_strlen')
                ? mb_strlen(
                    $body,
                    'UTF-8'
                )
                : strlen($body);

        if ($length > 20000) {
            return [
                'ok' => false,
                'status' =>
                    'reply_too_long',
            ];
        }

        $actorReference =
            'user:' . $userId;

        /*
         * TICKETING_STAFF_REPLY_DOMAIN_OWNERSHIP_GUARD
         *
         * Route RBAC answers only whether staff reply is a capability.
         * This domain guard determines whether THIS ticket is currently
         * owned by THIS user in THIS exact support project.
         *
         * Every caller of staffReply() is therefore protected, not only
         * the browser route.
         */
        $replyAccess =
            (
                new TicketStaffReplyAccessService()
            )->evaluate(
                $publicReference,
                $userId
            );

        if (
            empty(
                $replyAccess['can_reply']
            )
        ) {
            $state =
                trim(
                    (string) (
                        $replyAccess['state']
                        ?? 'reply_forbidden'
                    )
                );

            return match ($state) {
                'ticket_not_found' => [
                    'ok' => false,
                    'not_found' => true,
                    'status' => 'ticket_not_found',
                ],

                'reply_closed' => [
                    'ok' => false,
                    'status' => 'reply_closed',
                ],

                'reply_waiting_requester',
                'reply_takeover_required',
                'reply_not_assignee',
                'reply_assignment_invalid' => [
                    'ok' => false,
                    'status' => $state,
                ],

                default => [
                    'ok' => false,
                    'status' => 'reply_forbidden',
                ],
            };
        }

        /*
         * TICKETING_REPLY_MESSAGE_ATTACHMENTS
         *
         * Reuse the same secure/private upload service
         * as initial ticket creation.
         */
        $attachmentUpload =
            new TicketAttachmentUploadService();

        try {
            $preparedAttachments =
                $attachmentUpload->prepare(
                    is_array($files)
                        ? $files
                        : [],

                    'user:' . $userId
                );

        } catch (\InvalidArgumentException $exception) {
            return [
                'ok' =>
                    false,

                'status' =>
                    'reply_invalid',

                'attachment_error' =>
                    $attachmentUpload->errorMessage(
                        $exception->getMessage()
                    ),
            ];
        }

        try {
            $result =
                $this->tickets->staffReply(
                    $publicReference,
                    $body,
                    $actorReference,
                    $this->contextDisplayName(
                        $context,
                        $userId
                    ),
                    $preparedAttachments
                );

            $notification =
                (new TicketNotificationService())
                    ->staffReplied(
                        $result
                    );

            return [
                'ok' => true,
                ...$result,
                'notification' =>
                    $notification,
            ];
        } catch (RuntimeException $exception) {
            return match (
                $exception->getMessage()
            ) {
                'ticket_not_found' => [
                    'ok' => false,
                    'not_found' => true,
                    'status' =>
                        'ticket_not_found',
                ],

                'ticket_closed' => [
                    'ok' => false,
                    'status' =>
                        'reply_closed',
                ],

                'ticket_reply_membership_required'
                    => [
                        'ok' => false,
                        'status' =>
                            'reply_forbidden',
                    ],

                default =>
                    throw $exception,
            };
        }
    }


    public function requesterReply(
        string $publicReference,
        string $body,
        int $userId,
        array $files = []
    ): array {
        $publicReference =
            trim($publicReference);

        if (
            $publicReference === ''
            || $userId < 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'requester_reply_invalid',
            ];
        }

        if (trim($body) === '') {
            return [
                'ok' => false,
                'status' =>
                    'requester_reply_empty',
            ];
        }

        $length =
            function_exists('mb_strlen')
                ? mb_strlen(
                    $body,
                    'UTF-8'
                )
                : strlen($body);

        if ($length > 20000) {
            return [
                'ok' => false,
                'status' =>
                    'requester_reply_too_long',
            ];
        }

        /*
         * TICKETING_REPLY_MESSAGE_ATTACHMENTS
         *
         * Requester updates reuse the canonical private
         * attachment preparation pipeline.
         */
        $attachmentUpload =
            new TicketAttachmentUploadService();

        try {
            $preparedAttachments =
                $attachmentUpload->prepare(
                    is_array($files)
                        ? $files
                        : [],
                    'user:' . $userId
                );

        } catch (\InvalidArgumentException $exception) {
            return [
                'ok' => false,
                'status' =>
                    'requester_reply_invalid',
                /*
                 * Preserve only an internal classification code.
                 * Route/UI never render this value directly.
                 */
                'attachment_error_code' =>
                    $exception->getMessage(),
                'attachment_error' =>
                    $attachmentUpload->errorMessage(
                        $exception->getMessage()
                    ),
            ];
        }

        try {
            $result =
                $this->tickets
                    ->requesterReply(
                        $publicReference,
                        $body,
                        'user:' . $userId,
                        $preparedAttachments
                    );

            $notification =
                (new TicketNotificationService())
                    ->requesterReplied(
                        $result
                    );

            return [
                'ok' => true,
                ...$result,
                'notification' =>
                    $notification,
            ];

        } catch (RuntimeException $exception) {
            return match (
                $exception->getMessage()
            ) {
                'ticket_not_found' => [
                    'ok' => false,
                    'not_found' => true,
                    'status' =>
                        'ticket_not_found',
                ],

                'requester_reply_forbidden'
                    => [
                        'ok' => false,
                        'status' =>
                            'requester_reply_forbidden',
                    ],

                'requester_update_forbidden_state'
                    => [
                        'ok' => false,
                        'status' =>
                            'requester_update_forbidden_state',
                    ],

                default =>
                    throw $exception,
            };
        }
    }

    public function requesterResolve(
        string $publicReference,
        int $userId
    ): array {
        $publicReference =
            trim($publicReference);

        if (
            $publicReference === ''
            || $userId < 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'requester_reply_invalid',
            ];
        }

        try {
            $result =
                $this->tickets
                    ->requesterResolve(
                        $publicReference,
                        'user:' . $userId
                    );

            return [
                'ok' => true,
                ...$result,
            ];

        } catch (RuntimeException $exception) {
            return match (
                $exception->getMessage()
            ) {
                'ticket_not_found' => [
                    'ok' => false,
                    'not_found' => true,
                    'status' =>
                        'ticket_not_found',
                ],

                'requester_reply_forbidden'
                    => [
                        'ok' => false,
                        'status' =>
                            'requester_reply_forbidden',
                    ],

                'requester_resolve_forbidden_state'
                    => [
                        'ok' => false,
                        'status' =>
                            'requester_resolve_forbidden_state',
                    ],

                default =>
                    throw $exception,
            };
        }
    }

    private function contextDisplayName(
        array $context,
        int $userId
    ): string {
        $user =
            is_array(
                $context['user']
                ?? null
            )
                ? $context['user']
                : [];

        foreach ([
            'display_name',
            'name',
            'full_name',
            'username',
        ] as $field) {
            $value =
                trim(
                    (string) (
                        $user[$field]
                        ?? $context[$field]
                        ?? ''
                    )
                );

            if ($value !== '') {
                return $value;
            }
        }

        return
            'کاربر '
            . $userId;
    }
}
