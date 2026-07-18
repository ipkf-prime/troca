<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceAttachmentRepository
{
    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function add(int $correspondenceId, array $file, string $role, ?string $title, int $userId, string $now): void
    {
        $pdo = $this->runtime->connection();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare("INSERT INTO private_files (public_reference, storage_provider_code, storage_key, original_filename, mime_type, size_bytes, sha256_checksum, uploaded_by_user_id, uploaded_at, scan_status_code, status, created_at, updated_at) VALUES (?, 'local_private', ?, ?, ?, ?, ?, ?, ?, 'not_required', 'active', ?, ?)");
            $statement->execute([$file['public_reference'], $file['storage_key'], $file['original_filename'], $file['mime_type'], $file['size_bytes'], $file['sha256_checksum'], $userId, $now, $now, $now]);
            $fileId = (int) $pdo->lastInsertId();
            $link = $pdo->prepare('INSERT INTO correspondence_attachments (correspondence_id, correspondence_version_id, file_id, attachment_role_code, title, description, display_order, linked_by_user_id, linked_at) VALUES (?, NULL, ?, ?, ?, NULL, 0, ?, ?)');
            $link->execute([$correspondenceId, $fileId, $role, $title, $userId, $now]);
            $event = $pdo->prepare("INSERT INTO correspondence_events (correspondence_id, referral_id, event_type_code, actor_user_id, actor_org_unit_id, occurred_at, previous_status_code, resulting_status_code, safe_metadata_json, created_at) VALUES (?, NULL, 'attachment_linked', ?, NULL, ?, NULL, NULL, ?, ?)");
            $event->execute([$correspondenceId, $userId, $now, json_encode(['file_reference' => $file['public_reference'], 'role' => $role], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function listFor(int $correspondenceId): array
    {
        $statement = $this->runtime->connection()->prepare("SELECT a.id, a.attachment_role_code, a.title, a.description, a.linked_at, f.public_reference AS file_reference, f.original_filename, f.mime_type, f.size_bytes, f.storage_key FROM correspondence_attachments a INNER JOIN private_files f ON f.id = a.file_id AND f.status = 'active' WHERE a.correspondence_id = ? ORDER BY a.display_order, a.id");
        $statement->execute([$correspondenceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findForCorrespondence(string $correspondenceReference, string $fileReference): ?array
    {
        $statement = $this->runtime->connection()->prepare("SELECT f.* FROM private_files f INNER JOIN correspondence_attachments a ON a.file_id = f.id INNER JOIN correspondences c ON c.id = a.correspondence_id WHERE c.public_reference = ? AND f.public_reference = ? AND f.status = 'active' LIMIT 1");
        $statement->execute([$correspondenceReference, $fileReference]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
