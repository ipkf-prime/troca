<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceVersionRepository
{
    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function create(int $correspondenceId, int $versionNumber, array $data): int
    {
        $statement = $this->connection()->prepare('
            INSERT INTO correspondence_versions (
                correspondence_id, version_number, subject_snapshot, content_snapshot,
                summary_snapshot, change_note, content_checksum, created_by_user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $statement->execute([
            $correspondenceId,
            $versionNumber,
            $data['subject'],
            $data['content'],
            $data['summary'],
            $data['change_note'],
            hash('sha256', $data['content']),
            $data['created_by_user_id'],
            $data['created_at'],
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    public function listFor(int $correspondenceId): array
    {
        $statement = $this->connection()->prepare('SELECT * FROM correspondence_versions WHERE correspondence_id = ? ORDER BY version_number DESC');
        $statement->execute([$correspondenceId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function connection(): PDO
    {
        return $this->runtime->connection();
    }
}
