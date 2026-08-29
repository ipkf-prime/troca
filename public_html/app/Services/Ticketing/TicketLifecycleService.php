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
        array $context = []
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

        try {
            $result =
                $this->tickets->staffReply(
                    $publicReference,
                    $body,
                    $actorReference,
                    $this->contextDisplayName(
                        $context,
                        $userId
                    )
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
        int $userId
    ): array {
        $publicReference =
            trim($publicReference);

        if (
            $publicReference === ''
            ||
            $userId < 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'requester_reply_invalid',
            ];
        }

        /*
         * Preserve body exactly as supplied.
         * trim() is only used for blank validation.
         */
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

        try {
            $result =
                $this->tickets
                    ->requesterReply(
                        $publicReference,
                        $body,
                        'user:' . $userId
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

                'requester_reply_not_expected'
                    => [
                        'ok' => false,
                        'status' =>
                            'requester_reply_not_expected',
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
