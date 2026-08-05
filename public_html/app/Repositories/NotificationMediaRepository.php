<?php

namespace App\Repositories;

use RuntimeException;

class NotificationMediaRepository extends BaseRepository
{
    public function tableReady(): bool
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name IN (
                'notification_media_assets',
                'notification_media_links'
              )
        ");
        $statement->execute();

        return (int) $statement->fetchColumn() === 2;
    }

    public function create(
        int $actorUserId,
        array $asset
    ): array {
        if (!$this->tableReady()) {
            throw new RuntimeException(
                'notification_send_media_storage_unavailable'
            );
        }

        $reference =
            'nma_' . bin2hex(random_bytes(12));
        $statement = $this->connection()->prepare("
            INSERT INTO notification_media_assets (
                public_reference,
                actor_user_id,
                original_name,
                stored_name,
                storage_path,
                mime_type,
                extension,
                media_kind,
                size_bytes,
                checksum_sha256,
                status_code,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                'active',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");
        $statement->execute([
            $reference,
            $actorUserId,
            (string) $asset['original_name'],
            (string) $asset['stored_name'],
            (string) $asset['storage_path'],
            (string) $asset['mime_type'],
            (string) $asset['extension'],
            (string) $asset['media_kind'],
            (int) $asset['size_bytes'],
            (string) $asset['checksum_sha256'],
        ]);

        return $asset + [
            'id' => (int) $this->connection()
                ->lastInsertId(),
            'public_reference' => $reference,
        ];
    }

    public function remove(array $assetIds): void
    {
        $assetIds = array_values(array_unique(
            array_filter(
                array_map('intval', $assetIds),
                static fn (int $id): bool => $id > 0
            )
        ));

        if ($assetIds === []) {
            return;
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($assetIds), '?')
        );
        $statement = $this->connection()->prepare("
            DELETE FROM notification_media_assets
            WHERE id IN ({$placeholders})
              AND NOT EXISTS (
                SELECT 1
                FROM notification_media_links
                WHERE notification_media_links.asset_id =
                    notification_media_assets.id
              )
        ");
        $statement->execute($assetIds);
    }
}
