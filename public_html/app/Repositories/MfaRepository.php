<?php

namespace App\Repositories;

class MfaRepository extends BaseRepository
{
    public function enabledMethodsForUser(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT id, user_id, method, label, destination_masked, is_primary, is_enabled, verified_at
            FROM user_mfa_methods
            WHERE user_id = ?
              AND is_enabled = 1
              AND verified_at IS NOT NULL
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll();
    }

    public function unusedRecoveryCodeCount(int $userId): int
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM recovery_codes
            WHERE user_id = ?
              AND used_at IS NULL
        ");
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    public function totpMethodForUser(int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT *
            FROM user_mfa_methods
            WHERE user_id = ?
              AND method = 'totp'
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $method = $statement->fetch();

        return $method ?: null;
    }

    public function saveTotpSetup(int $userId, string $secret, string $label): array
    {
        $existing = $this->totpMethodForUser($userId);

        if ($existing !== null) {
            $statement = $this->connection()->prepare("
                UPDATE user_mfa_methods
                SET label = ?, secret_encrypted = ?, is_primary = 1, is_enabled = 0,
                    verified_at = NULL, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([$label, $secret, (int) $existing['id']]);

            return $this->totpMethodForUser($userId) ?? $existing;
        }

        $statement = $this->connection()->prepare("
            INSERT INTO user_mfa_methods (
                user_id, method, label, secret_encrypted, is_primary, is_enabled,
                verified_at, created_at, updated_at
            )
            VALUES (?, 'totp', ?, ?, 1, 0, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $statement->execute([$userId, $label, $secret]);

        return $this->totpMethodForUser($userId) ?? [];
    }

    public function enableMethod(int $methodId): void
    {
        $statement = $this->connection()->prepare("
            UPDATE user_mfa_methods
            SET is_enabled = 1, verified_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([$methodId]);
    }
}
