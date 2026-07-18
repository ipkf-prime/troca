<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceRelationRepository
{
    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function replaceForDraft(int $sourceId, array $relations, int $userId, string $createdAt): void
    {
        $pdo = $this->runtime->connection();
        $pdo->prepare('DELETE FROM correspondence_relations WHERE source_correspondence_id = ?')->execute([$sourceId]);
        $statement = $pdo->prepare('INSERT INTO correspondence_relations (source_correspondence_id, target_correspondence_id, relation_type_code, note, created_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($relations as $relation) {
            $statement->execute([$sourceId, $relation['target_correspondence_id'], $relation['relation_type_code'], $relation['note'], $userId, $createdAt]);
        }
    }

    public function listFor(int $sourceId): array
    {
        $statement = $this->runtime->connection()->prepare("SELECT r.*, c.public_reference AS target_public_reference, c.subject AS target_subject, c.external_number AS target_external_number, c.external_date AS target_external_date FROM correspondence_relations r INNER JOIN correspondences c ON c.id = r.target_correspondence_id WHERE r.source_correspondence_id = ? ORDER BY r.id");
        $statement->execute([$sourceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function options(?int $excludeId = null): array
    {
        $sql = 'SELECT id, public_reference, subject, external_number, external_date FROM correspondences';
        $params = [];
        if ($excludeId !== null) {
            $sql .= ' WHERE id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT 200';
        $statement = $this->runtime->connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function targetId(string $publicReference): ?int
    {
        $statement = $this->runtime->connection()->prepare('SELECT id FROM correspondences WHERE public_reference = ? LIMIT 1');
        $statement->execute([$publicReference]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
