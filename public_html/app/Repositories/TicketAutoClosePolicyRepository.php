<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;

final class TicketAutoClosePolicyRepository
{
    private PDO $db;


    public function __construct(
        ?PDO $db = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    public function policyForProject(
        int $projectId
    ): array {
        if ($projectId < 1) {
            return
                $this->disabledPolicy(
                    $projectId
                );
        }

        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    support_project_id,
                    is_enabled,
                    delay_hours,
                    eligible_resolved_from,
                    enabled_at,
                    updated_by_user_reference,
                    created_at,
                    updated_at

                FROM
                    ticketing_auto_close_policies

                WHERE
                    support_project_id = ?

                LIMIT 1
            ");

        $statement->execute([
            $projectId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return
                $this->disabledPolicy(
                    $projectId
                );
        }

        return [
            'exists' =>
                true,

            'id' =>
                (int) (
                    $row['id']
                    ?? 0
                ),

            'support_project_id' =>
                (int) (
                    $row['support_project_id']
                    ?? 0
                ),

            'is_enabled' =>
                (int) (
                    $row['is_enabled']
                    ?? 0
                ) === 1,

            'delay_hours' =>
                $row['delay_hours'] === null
                    ? null
                    : (int) $row['delay_hours'],

            'eligible_resolved_from' =>
                $row['eligible_resolved_from']
                === null
                    ? null
                    : (string)
                        $row[
                            'eligible_resolved_from'
                        ],

            'enabled_at' =>
                $row['enabled_at'] === null
                    ? null
                    : (string)
                        $row['enabled_at'],

            'updated_by_user_reference' =>
                $row[
                    'updated_by_user_reference'
                ] === null
                    ? null
                    : (string)
                        $row[
                            'updated_by_user_reference'
                        ],
        ];
    }


    public function candidates(
        int $projectId,
        string $eligibleResolvedFrom,
        string $cutoffAt,
        int $limit = 100
    ): array {
        if (
            $projectId < 1
            ||
            trim($eligibleResolvedFrom) === ''
            ||
            trim($cutoffAt) === ''
        ) {
            return [];
        }

        $limit =
            max(
                1,
                min(
                    100,
                    $limit
                )
            );

        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    public_reference,
                    ticket_number,
                    support_project_id,
                    status_code,
                    resolved_at,
                    closed_at

                FROM ticketing_tickets

                WHERE support_project_id = ?
                  AND status_code = 'resolved'
                  AND resolved_at IS NOT NULL
                  AND closed_at IS NULL
                  AND archived_at IS NULL

                  /*
                   * First-run safety:
                   * tickets resolved before the explicit
                   * eligibility boundary are never selected.
                   */
                  AND resolved_at >= ?

                  /*
                   * Delay threshold.
                   */
                  AND resolved_at <= ?

                ORDER BY
                    resolved_at ASC,
                    id ASC

                LIMIT {$limit}
            ");

        $statement->execute([
            $projectId,
            $eligibleResolvedFrom,
            $cutoffAt,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            )
            ?: [];
    }


    private function disabledPolicy(
        int $projectId
    ): array {
        return [
            'exists' => false,
            'id' => 0,
            'support_project_id' =>
                $projectId,
            'is_enabled' => false,
            'delay_hours' => null,
            'eligible_resolved_from' =>
                null,
            'enabled_at' => null,
            'updated_by_user_reference' =>
                null,
        ];
    }
}
