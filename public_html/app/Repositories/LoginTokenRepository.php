<?php

namespace App\Repositories;

class LoginTokenRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $statement = $this->connection()->prepare("
            INSERT INTO auth_login_tokens (
                user_id, token_hash, purpose, source, redirect_path, expires_at,
                created_by_user_id, created_ip, created_user_agent, metadata_json,
                created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $statement->execute([
            $data['user_id'],
            $data['token_hash'],
            $data['purpose'],
            $data['source'] ?? null,
            $data['redirect_path'] ?? null,
            $data['expires_at'],
            $data['created_by_user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $data['metadata_json'] ?? null,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    public function findConsumable(string $tokenHash): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM auth_login_tokens
            WHERE token_hash = ?
              AND used_at IS NULL
              AND revoked_at IS NULL
              AND expires_at >= CURRENT_TIMESTAMP
            LIMIT 1
        ");
        $statement->execute([$tokenHash]);
        $token = $statement->fetch();

        return $token ?: null;
    }

    public function markConsumed(int $id): void
    {
        $statement = $this->connection()->prepare("
            UPDATE auth_login_tokens
            SET used_at = CURRENT_TIMESTAMP,
                consumed_ip = ?,
                consumed_user_agent = ?,
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
