<?php

declare(strict_types=1);

namespace App\Repositories;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

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


    /*
     * TICKETING_AUTO_CLOSE_POLICY_WRITE_V1
     *
     * Safe enablement contract:
     *
     * - Policy writes serialize per Project.
     * - Disabled -> Enabled always creates a fresh
     *   eligible_resolved_from boundary.
     * - Enabled -> Enabled preserves the boundary.
     * - Enabled -> Disabled preserves history but stops
     *   execution immediately.
     * - Re-enable creates another fresh boundary.
     *
     * eligible_resolved_from is system-managed and is
     * never accepted from browser input.
     */
    public function savePolicy(
        int $projectId,
        bool $isEnabled,
        ?int $delayHours,
        string $actorReference
    ): array {
        $actorReference =
            trim(
                $actorReference
            );

        if (
            $projectId < 1
            ||
            $actorReference === ''
        ) {
            throw new \RuntimeException(
                'auto_close_policy_write_invalid'
            );
        }

        if (
            $delayHours !== null
            &&
            (
                $delayHours < 1
                ||
                $delayHours > 65535
            )
        ) {
            throw new \RuntimeException(
                'auto_close_delay_invalid'
            );
        }

        if (
            $isEnabled
            &&
            $delayHours === null
        ) {
            throw new \RuntimeException(
                'auto_close_delay_required'
            );
        }

        $this->db->beginTransaction();

        try {
            /*
             * Serialize all policy mutations for the
             * same Support Project.
             */
            $projectStatement =
                $this->db->prepare("
                    SELECT
                        id

                    FROM
                        ticketing_support_projects

                    WHERE id = ?
                      AND archived_at IS NULL

                    LIMIT 1

                    FOR UPDATE
                ");

            $projectStatement->execute([
                $projectId,
            ]);

            if (
                $projectStatement
                    ->fetchColumn()
                === false
            ) {
                throw new \RuntimeException(
                    'auto_close_project_not_found'
                );
            }


            $policyStatement =
                $this->db->prepare("
                    SELECT
                        id,
                        is_enabled,
                        delay_hours,
                        eligible_resolved_from,
                        enabled_at

                    FROM
                        ticketing_auto_close_policies

                    WHERE
                        support_project_id = ?

                    LIMIT 1

                    FOR UPDATE
                ");

            $policyStatement->execute([
                $projectId,
            ]);

            $existing =
                $policyStatement->fetch(
                    PDO::FETCH_ASSOC
                );

            $wasEnabled =
                is_array($existing)
                &&
                (int) (
                    $existing[
                        'is_enabled'
                    ]
                    ?? 0
                ) === 1;

            $existingBoundary =
                is_array($existing)
                    ? trim(
                        (string) (
                            $existing[
                                'eligible_resolved_from'
                            ]
                            ?? ''
                        )
                    )
                    : '';

            /*
             * A fresh safety boundary is required on:
             *
             * 1. first enable;
             * 2. every disabled -> enabled transition;
             * 3. repair of an invalid enabled row that
             *    somehow lost its boundary.
             */
            $freshBoundary =
                $isEnabled
                &&
                (
                    !$wasEnabled
                    ||
                    $existingBoundary === ''
                );


            if (!is_array($existing)) {

                if ($isEnabled) {

                    $statement =
                        $this->db->prepare("
                            INSERT INTO
                                ticketing_auto_close_policies
                            (
                                support_project_id,
                                is_enabled,
                                delay_hours,
                                eligible_resolved_from,
                                enabled_at,
                                updated_by_user_reference,
                                created_at,
                                updated_at
                            )
                            VALUES
                            (
                                ?,
                                1,
                                ?,
                                UTC_TIMESTAMP(),
                                UTC_TIMESTAMP(),
                                ?,
                                UTC_TIMESTAMP(),
                                UTC_TIMESTAMP()
                            )
                        ");

                    $statement->execute([
                        $projectId,
                        $delayHours,
                        $actorReference,
                    ]);

                } else {

                    $statement =
                        $this->db->prepare("
                            INSERT INTO
                                ticketing_auto_close_policies
                            (
                                support_project_id,
                                is_enabled,
                                delay_hours,
                                eligible_resolved_from,
                                enabled_at,
                                updated_by_user_reference,
                                created_at,
                                updated_at
                            )
                            VALUES
                            (
                                ?,
                                0,
                                ?,
                                NULL,
                                NULL,
                                ?,
                                UTC_TIMESTAMP(),
                                UTC_TIMESTAMP()
                            )
                        ");

                    $statement->execute([
                        $projectId,
                        $delayHours,
                        $actorReference,
                    ]);
                }

            } elseif ($freshBoundary) {

                $statement =
                    $this->db->prepare("
                        UPDATE
                            ticketing_auto_close_policies

                        SET
                            is_enabled = 1,

                            delay_hours = ?,

                            eligible_resolved_from =
                                UTC_TIMESTAMP(),

                            enabled_at =
                                UTC_TIMESTAMP(),

                            updated_by_user_reference = ?,

                            updated_at =
                                UTC_TIMESTAMP()

                        WHERE
                            support_project_id = ?
                    ");

                $statement->execute([
                    $delayHours,
                    $actorReference,
                    $projectId,
                ]);

            } else {

                /*
                 * Preserve the existing safety boundary
                 * for enabled->enabled edits and for
                 * disabling.
                 */
                $statement =
                    $this->db->prepare("
                        UPDATE
                            ticketing_auto_close_policies

                        SET
                            is_enabled = ?,

                            delay_hours = ?,

                            updated_by_user_reference = ?,

                            updated_at =
                                UTC_TIMESTAMP()

                        WHERE
                            support_project_id = ?
                    ");

                $statement->execute([
                    $isEnabled
                        ? 1
                        : 0,

                    $delayHours,
                    $actorReference,
                    $projectId,
                ]);
            }

            $this->db->commit();

        } catch (Throwable $exception) {

            if (
                $this->db
                    ->inTransaction()
            ) {
                $this->db
                    ->rollBack();
            }

            throw $exception;
        }

        return
            $this->policyForProject(
                $projectId
            );
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
