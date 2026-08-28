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
