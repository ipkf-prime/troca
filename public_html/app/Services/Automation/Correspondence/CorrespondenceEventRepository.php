<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceEventRepository
{
    public function __construct(
        private ?AutomationOperationalRuntime $runtime = null
    ) {
        $this->runtime ??=
            new AutomationOperationalRuntime();
    }

    public function append(
        int $correspondenceId,
        string $eventType,
        int $actorUserId,
        ?string $previousStatus,
        ?string $resultingStatus,
        array $metadata,
        string $now,
        ?array $actorContext = null
    ): void {
        $statement =
            $this->connection()->prepare("
                INSERT INTO correspondence_events (
                    correspondence_id,
                    referral_id,

                    root_organization_id,
                    organization_id,
                    secretariat_desk_id,

                    event_type_code,

                    actor_user_id,
                    actor_org_unit_id,
                    actor_appointment_reference,
                    actor_context_snapshot_json,

                    occurred_at,

                    previous_status_code,
                    resulting_status_code,

                    safe_metadata_json,

                    created_at
                )
                VALUES (
                    ?,
                    NULL,

                    ?,
                    ?,
                    ?,

                    ?,

                    ?,
                    ?,
                    ?,
                    ?,

                    ?,

                    ?,
                    ?,

                    ?,

                    ?
                )
            ");

        $statement->execute([
            $correspondenceId,

            $actorContext[
                'root_organization_id'
            ] ?? null,

            $actorContext[
                'organization_id'
            ] ?? null,

            $actorContext[
                'secretariat_desk_id'
            ] ?? null,

            $eventType,

            $actorUserId,

            $actorContext[
                'org_unit_id'
            ] ?? null,

            $actorContext[
                'appointment_reference'
            ] ?? null,

            $actorContext[
                'snapshot_json'
            ] ?? null,

            $now,

            $previousStatus,
            $resultingStatus,

            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ) ?: '{}',

            $now,
        ]);
    }

    public function listFor(
        int $correspondenceId
    ): array {
        $statement =
            $this->connection()->prepare("
                SELECT *
                FROM correspondence_events
                WHERE correspondence_id = ?
                ORDER BY
                    occurred_at DESC,
                    id DESC
            ");

        $statement->execute([
            $correspondenceId,
        ]);

        return
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            ) ?: [];
    }

    private function connection(): PDO
    {
        return
            $this->runtime
                ->connection();
    }
}
