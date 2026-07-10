<?php

namespace App\Repositories;

class IdentityChangeRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $statement = $this->connection()->prepare("
            INSERT INTO identity_change_requests (
                user_id, field_name, old_value, new_value, normalized_new_value,
                token_hash, channel, expires_at, created_ip, created_user_agent,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $statement->execute([
            $data['user_id'],
            $data['field_name'],
            $data['old_value'] ?? null,
            $data['new_value'],
            $data['normalized_new_value'],
            $data['token_hash'],
            $data['channel'],
            $data['expires_at'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    public function findPending(int $id, int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM identity_change_requests
            WHERE id = ?
              AND user_id = ?
              AND applied_at IS NULL
              AND cancelled_at IS NULL
            LIMIT 1
        ");
        $statement->execute([$id, $userId]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    public function findActivePending(int $userId, string $field, string $normalizedValue, string $nowUtc): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT id, expires_at
            FROM identity_change_requests
            WHERE user_id = ?
              AND field_name = ?
              AND normalized_new_value = ?
              AND applied_at IS NULL
              AND cancelled_at IS NULL
              AND expires_at >= ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute([$userId, $field, $normalizedValue, $nowUtc]);
        $request = $statement->fetch();

        return $request ?: null;
    }

    public function markAttempt(int $id): void
    {
        $this->connection()->prepare('UPDATE identity_change_requests SET attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$id]);
    }

    public function markApplied(int $id): void
    {
        $statement = $this->connection()->prepare("
            UPDATE identity_change_requests
            SET verified_at = CURRENT_TIMESTAMP,
                applied_at = CURRENT_TIMESTAMP,
                verified_ip = ?,
                verified_user_agent = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $id,
        ]);
    }
}
