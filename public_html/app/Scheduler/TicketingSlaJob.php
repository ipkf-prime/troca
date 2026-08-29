<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Services\Ticketing\TicketingSlaRuntimeService;
use App\Services\Ticketing\TicketingSlaService;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Scheduler\SchedulerJobInterface;
use PDO;
use RuntimeException;

final class TicketingSlaJob
    implements SchedulerJobInterface
{
    private PDO $db;


    public function __construct()
    {
        $this->db =
            (new ConnectionResolver())
                ->resolve(
                    'ticketing.primary'
                );
    }


    public function key(): string
    {
        return
            'ticketing.sla.evaluate';
    }


    public function applicationKey(): string
    {
        return
            'ticketing';
    }


    public function title(): string
    {
        return
            'بررسی SLA تیکت‌ها';
    }


    public function description(): string
    {
        return
            'کنترل SLA و Escalation خودکار به تفکیک پروژه.';
    }


    public function scopeModel(): string
    {
        return
            'project';
    }


    public function defaultIntervalMinutes(): int
    {
        return
            5;
    }


    public function scopes(): array
    {
        $rows =
            $this->db->query("
                SELECT
                    id,
                    public_reference,
                    code,
                    title

                FROM ticketing_support_projects

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
                    (string) $row['public_reference']
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
                    (string) $row['title'],

                'context' => [
                    'project_id' =>
                        (int) $row['id'],

                    'project_code' =>
                        (string) $row['code'],
                ],
            ];
        }

        return
            $scopes;
    }


    public function run(
        array $context
    ): array {
        $scope =
            is_array(
                $context['scope_context']
                ?? null
            )
                ? $context['scope_context']
                : [];

        $projectId =
            (int) (
                $scope['project_id']
                ?? 0
            );

        if ($projectId < 1) {
            throw new RuntimeException(
                'ticketing_scheduler_project_missing'
            );
        }

        $initialization =
            (new TicketingSlaService())
                ->initializeEligible(
                    100,
                    true,
                    $projectId
                );

        $runtime =
            (new TicketingSlaRuntimeService())
                ->process(
                    200,
                    true,
                    null,
                    $projectId
                );

        return [
            'project_id' =>
                $projectId,

            'project_code' =>
                (string) (
                    $scope['project_code']
                    ?? ''
                ),

            'initialization' =>
                $initialization,

            'runtime' =>
                $runtime,
        ];
    }
}
