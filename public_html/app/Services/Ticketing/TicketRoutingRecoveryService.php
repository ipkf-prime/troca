<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\TicketCreateRoutingRepository;
use App\Services\AuthorizationService;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

final class TicketRoutingRecoveryService
{
    private TicketCreateRoutingRepository $router;
    private TicketRoutingExceptionService $exceptions;
    private AuthorizationService $authorization;
    private PDO $db;

    public function __construct(
        ?TicketCreateRoutingRepository $router = null,
        ?TicketRoutingExceptionService $exceptions = null,
        ?AuthorizationService $authorization = null,
        ?ConnectionResolver $connections = null
    ) {
        $this->router =
            $router
            ?? new TicketCreateRoutingRepository();

        $this->exceptions =
            $exceptions
            ?? new TicketRoutingExceptionService();

        $this->authorization =
            $authorization
            ?? new AuthorizationService();

        $this->db =
            (
                $connections
                ?? new ConnectionResolver()
            )->resolve('ticketing.primary');
    }

    public function recoverMissingTopic(
        string $publicReference,
        int $topicId,
        int $userId,
        array $context = []
    ): array {
        $publicReference = trim($publicReference);

        if (
            $publicReference === ''
            || $topicId < 1
            || $userId < 1
        ) {
            return [
                'ok' => false,
                'status' => 'routing_recovery_invalid',
            ];
        }

        try {
            if (
                !$this->authorization->hasPermission(
                    $userId,
                    'ticketing.project.manage'
                )
            ) {
                return [
                    'ok' => false,
                    'status' => 'routing_recovery_forbidden',
                ];
            }
        } catch (\Throwable) {
            return [
                'ok' => false,
                'status' => 'routing_recovery_forbidden',
            ];
        }

        $panel = $this->exceptions->panel(
            $publicReference,
            $userId
        );

        if (empty($panel['found'])) {
            return [
                'ok' => false,
                'not_found' => true,
                'status' => 'ticket_not_found',
            ];
        }

        $classification = is_array(
            $panel['classification'] ?? null
        )
            ? $panel['classification']
            : [];

        if (
            empty($panel['can_manage'])
            || empty($classification['actionable'])
            || ($classification['code'] ?? '') !== 'missing_topic'
        ) {
            return [
                'ok' => false,
                'status' => 'routing_recovery_not_eligible',
            ];
        }

        $ticket = is_array($panel['ticket'] ?? null)
            ? $panel['ticket']
            : [];

        $projectId = (int) (
            $ticket['support_project_id']
            ?? 0
        );

        if (
            $projectId < 1
            || !$this->isActiveProjectManager(
                $projectId,
                $userId
            )
        ) {
            return [
                'ok' => false,
                'status' => 'routing_recovery_forbidden',
            ];
        }

        $topics = is_array(
            $panel['selectable_topics'] ?? null
        )
            ? $panel['selectable_topics']
            : [];

        $allowedTopic = false;

        foreach ($topics as $topic) {
            if ((int) ($topic['id'] ?? 0) === $topicId) {
                $allowedTopic = true;
                break;
            }
        }

        if (!$allowedTopic) {
            return [
                'ok' => false,
                'status' => 'routing_recovery_invalid_topic',
            ];
        }

        $actorReference = 'user:' . $userId;

        $actorName = trim(
            (string) (
                $context['display_name']
                ?? $context['name']
                ?? $context['username']
                ?? $actorReference
            )
        );

        if ($actorName === '') {
            $actorName = $actorReference;
        }

        try {
            $result = $this->router->recoverMissingTopic(
                $publicReference,
                $topicId,
                $actorReference,
                $actorName
            );

            return [
                'ok' => true,
                'status' => 'routing_recovery_applied',
                'result' => $result,
            ];

        } catch (RuntimeException $exception) {
            $status = match ($exception->getMessage()) {
                'ticket_not_found' =>
                    'ticket_not_found',

                'routing_recovery_not_eligible',
                'routing_recovery_conflict',
                'routing_recovery_requester_identity_missing' =>
                    'routing_recovery_not_eligible',

                'routing_recovery_invalid_topic' =>
                    'routing_recovery_invalid_topic',

                'routing_recovery_no_route' =>
                    'routing_recovery_no_route',

                'routing_recovery_no_team',
                'routing_recovery_invalid_scope',
                'routing_recovery_invalid_topology',
                'routing_recovery_assignment_mode_invalid' =>
                    'routing_recovery_invalid_topology',

                'routing_recovery_no_eligible_assignee' =>
                    'routing_recovery_no_eligible_assignee',

                /*
                 * Unknown RuntimeExceptions are infrastructure or
                 * implementation failures, not user-domain statuses.
                 * Re-throw so the route can create a traceable incident.
                 */
                default =>
                    throw $exception,
            };

            return [
                'ok' => false,
                'not_found' =>
                    $status === 'ticket_not_found',
                'status' => $status,
            ];
        }
    }

    private function isActiveProjectManager(
        int $projectId,
        int $userId
    ): bool {
        if ($projectId < 1 || $userId < 1) {
            return false;
        }

        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM ticketing_support_project_members
            WHERE project_id = ?
              AND user_reference = ?
              AND role_code = 'manager'
              AND left_at IS NULL
        ");

        $statement->execute([
            $projectId,
            'user:' . $userId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
