<?php

namespace App\Services\Ticketing;

use App\Repositories\TicketLifecycleTransitionRepository;
use RuntimeException;

final class TicketLifecycleTransitionService
{
    public function __construct(
        private ?TicketLifecycleTransitionRepository $tickets = null
    ) {
        $this->tickets ??=
            new TicketLifecycleTransitionRepository();
    }


    public function capabilities(
        string $publicReference,
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
                'found' => false,
                'can_resolve' => false,
                'can_close' => false,
                'can_reopen' => false,
            ];
        }

        return
            $this->tickets
                ->capabilities(
                    $publicReference,
                    'user:' . $userId
                );
    }


    public function transition(
        string $publicReference,
        string $action,
        int $userId,
        array $context = []
    ): array {
        $publicReference =
            trim($publicReference);

        $action =
            strtolower(
                trim($action)
            );

        if (
            $publicReference === ''
            ||
            $userId < 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'lifecycle_invalid',
            ];
        }

        try {
            $result =
                $this->tickets
                    ->transition(
                        $publicReference,
                        $action,
                        'user:' . $userId,
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
            $code =
                trim(
                    $exception
                        ->getMessage()
                );

            if ($code === 'ticket_not_found') {
                return [
                    'ok' => false,
                    'not_found' => true,
                    'status' =>
                        'ticket_not_found',
                ];
            }

            $safe = [
                'lifecycle_invalid_action',
                'lifecycle_owner_required',
                'lifecycle_waiting_requester',
                'lifecycle_invalid_state',
                'lifecycle_resolve_first',
                'lifecycle_close_forbidden',
                'lifecycle_reopen_invalid_state',
                'lifecycle_reopen_forbidden',
                'lifecycle_transition_conflict',
            ];

            if (
                in_array(
                    $code,
                    $safe,
                    true
                )
            ) {
                return [
                    'ok' => false,
                    'status' =>
                        $code,
                ];
            }

            throw $exception;
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
