<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Services\Ticketing\TicketAutoCloseService;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Scheduler\SchedulerJobInterface;
use PDO;
use RuntimeException;

final class TicketAutoCloseJob
    implements SchedulerJobInterface
{
    private PDO $db;


    public function __construct()
    {
        $this->db =
            (
                new ConnectionResolver()
            )->resolve(
                'ticketing.primary'
            );
    }


    public function key(): string
    {
        return
            'ticketing.ticket.auto_close';
    }


    public function applicationKey(): string
    {
        return
            'ticketing';
    }


    public function title(): string
    {
        return
            'بستن خودکار تیکت‌های حل‌شده';
    }


    public function description(): string
    {
        return
            'بستن کنترل‌شده تیکت‌های حل‌شده '
            . 'بر اساس سیاست مستقل هر پروژه.';
    }


    public function scopeModel(): string
    {
        return
            'project';
    }


    /*
     * Polling cadence only.
     *
     * Business close delay is never derived
     * from this Scheduler interval.
     */
    public function defaultIntervalMinutes(): int
    {
        return
            15;
    }


    public function scopes(): array
    {
        /*
         * All active projects are exposed to the
         * generic Scheduler control plane.
         *
         * Business execution still remains a no-op
         * unless the project's Auto-close policy is
         * explicitly enabled.
         */
        $rows =
            $this->db->query("
                SELECT
                    id,
                    public_reference,
                    code,
                    title

                FROM
                    ticketing_support_projects

                WHERE is_active = 1
                  AND archived_at IS NULL

                ORDER BY
                    sort_order,
                    id
            ")->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];

        $scopes = [];

        foreach ($rows as $row) {
            $reference =
                trim(
                    (string) (
                        $row[
                            'public_reference'
                        ]
                        ?? ''
                    )
                );

            if ($reference === '') {
                continue;
            }

            $scopes[] = [
                'type' =>
                    'project',

                'reference' =>
                    $reference,

                'title' =>
                    (string) (
                        $row['title']
                        ?? $reference
                    ),

                'context' => [
                    'project_id' =>
                        (int) (
                            $row['id']
                            ?? 0
                        ),

                    'project_code' =>
                        (string) (
                            $row['code']
                            ?? ''
                        ),
                ],
            ];
        }

        return $scopes;
    }


    public function run(
        array $context
    ): array {
        $scope =
            is_array(
                $context[
                    'scope_context'
                ]
                ?? null
            )
                ? $context[
                    'scope_context'
                ]
                : [];

        $projectId =
            (int) (
                $scope[
                    'project_id'
                ]
                ?? 0
            );

        if ($projectId < 1) {
            throw new RuntimeException(
                'ticketing_auto_close_project_missing'
            );
        }

        $runtime =
            (
                new TicketAutoCloseService()
            )->process(
                $projectId,
                100
            );

        return [
            'project_id' =>
                $projectId,

            'project_code' =>
                (string) (
                    $scope[
                        'project_code'
                    ]
                    ?? ''
                ),

            'auto_close' =>
                $runtime,
        ];
    }
}
