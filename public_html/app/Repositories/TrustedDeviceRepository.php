<?php

namespace App\Repositories;

class TrustedDeviceRepository extends BaseRepository
{
    public function listForUser(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT id, device_name, ip_address, expires_at, revoked_at, created_at
            FROM trusted_devices
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function revoke(int $userId, int $deviceId): bool
    {
        $statement = $this->connection()->prepare("
            UPDATE trusted_devices
            SET revoked_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND user_id = ?
              AND revoked_at IS NULL
        ");
        $statement->execute([$deviceId, $userId]);

        return $statement->rowCount() > 0;
    }
}
