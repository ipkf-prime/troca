<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketPriorityManagementRepository;
use App\Services\AuthorizationService;
use RuntimeException;

class TicketPriorityManagementService
{
    private TicketPriorityManagementRepository $repository;
    private AuthorizationService $authorization;

    public function __construct(
        ?TicketPriorityManagementRepository $repository = null,
        ?AuthorizationService $authorization = null
    ) {
        $this->repository =
            $repository
            ?? new TicketPriorityManagementRepository();

        $this->authorization =
            $authorization
            ?? new AuthorizationService();
    }

    public function panel(
        string $publicReference,
        int $userId
    ): array {
        $panel = $this->repository->panel(
            trim($publicReference),
            $userId
        );

        $canChange = false;

        if (
            !empty($panel['found'])
            && !empty($panel['operational'])
            && $userId > 0
        ) {
            try {
                $canChange =
                    $this->authorization->hasPermission(
                        $userId,
                        'ticketing.ticket.reply'
                    );
            } catch (\Throwable) {
                $canChange = false;
            }
        }

        $panel['can_change'] = $canChange;

        return $panel;
    }

    public function change(
        string $publicReference,
        int $userId,
        string $priorityCode,
        string $reason
    ): array {
        $publicReference = trim($publicReference);

        if ($publicReference === '' || $userId < 1) {
            return [
                'ok' => false,
                'status' => 'priority_invalid',
            ];
        }

        try {
            if (
                !$this->authorization->hasPermission(
                    $userId,
                    'ticketing.ticket.reply'
                )
            ) {
                return [
                    'ok' => false,
                    'status' => 'priority_forbidden',
                ];
            }
        } catch (\Throwable) {
            return [
                'ok' => false,
                'status' => 'priority_forbidden',
            ];
        }

        try {
            $result = $this->repository->change(
                $publicReference,
                $userId,
                trim($priorityCode),
                trim($reason)
            );

            return [
                'ok' => true,
                'status' => !empty($result['changed'])
                    ? 'priority_changed'
                    : 'priority_unchanged',
                'result' => $result,
            ];

        } catch (RuntimeException $exception) {
            $status = match ($exception->getMessage()) {
                'ticket_not_found' => 'ticket_not_found',
                'priority_change_forbidden' =>
                    'priority_forbidden',
                'priority_invalid' => 'priority_invalid',
                'priority_reason_invalid' =>
                    'priority_reason_invalid',
                default => 'priority_failed',
            };

            return [
                'ok' => false,
                'not_found' =>
                    $status === 'ticket_not_found',
                'status' => $status,
            ];
        }
    }
}
