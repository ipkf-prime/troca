<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceEventRepository
{
    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function append(int $correspondenceId, string $eventType, int $actorUserId, ?string $previousStatus, ?string $resultingStatus, array $metadata, string $now): void
    {
        $statement = $this->connection()->prepare('
            INSERT INTO correspondence_events (
                correspondence_id, referral_id, event_type_code, actor_user_id, actor_org_unit_id,
                occurred_at, previous_status_code, resulting_status_code, safe_metadata_json, created_at
            ) VALUES (?, NULL, ?, ?, NULL, ?, ?, ?, ?, ?)
        ');
        $statement->execute([
            $correspondenceId,
            $eventType,
            $actorUserId,
            $now,
            $previousStatus,
            $resultingStatus,
            json_encode($metadata, JSON_UNESCAPED_UNICODE) ?: '{}',
            $now,
        ]);
    }

    public function listFor(int $correspondenceId): array
    {
        $statement = $this->connection()->prepare('SELECT * FROM correspondence_events WHERE correspondence_id = ? ORDER BY occurred_at DESC, id DESC');
        $statement->execute([$correspondenceId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function connection(): PDO
    {
        return $this->runtime->connection();
    }
}
