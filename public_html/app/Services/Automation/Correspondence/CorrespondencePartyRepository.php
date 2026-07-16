<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondencePartyRepository
{
    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function replaceForDraft(int $correspondenceId, array $parties, string $now): void
    {
        $delete = $this->connection()->prepare('DELETE FROM correspondence_parties WHERE correspondence_id = ?');
        $delete->execute([$correspondenceId]);
        $this->insertMany($correspondenceId, $parties, $now);
    }

    public function insertMany(int $correspondenceId, array $parties, string $now): void
    {
        $statement = $this->connection()->prepare('
            INSERT INTO correspondence_parties (
                correspondence_id, party_role_code, target_kind_code, person_id, organization_id,
                org_unit_id, external_display_name, external_organization_name,
                external_contact_or_address, sort_order, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($parties as $index => $party) {
            $statement->execute([
                $correspondenceId,
                $party['party_role_code'],
                $party['target_kind_code'],
                $party['person_id'],
                $party['organization_id'],
                $party['org_unit_id'],
                $party['external_display_name'],
                $party['external_organization_name'],
                $party['external_contact_or_address'],
                ($index + 1) * 10,
                $now,
            ]);
        }
    }

    public function listFor(int $correspondenceId): array
    {
        $statement = $this->connection()->prepare('SELECT * FROM correspondence_parties WHERE correspondence_id = ? ORDER BY sort_order ASC, id ASC');
        $statement->execute([$correspondenceId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function connection(): PDO
    {
        return $this->runtime->connection();
    }
}
