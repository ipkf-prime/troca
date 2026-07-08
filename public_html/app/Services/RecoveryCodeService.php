<?php

namespace App\Services;

use IPKF\Database\Database;

class RecoveryCodeService extends BaseService
{
    public function regenerate(int $userId, int $count = 10): array
    {
        $db = Database::connect();
        $db->prepare('
            UPDATE recovery_codes
            SET used_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
              AND used_at IS NULL
        ')->execute([$userId]);

        $codes = [];
        $statement = $db->prepare("
            INSERT INTO recovery_codes (user_id, code_hash, created_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");

        for ($i = 0; $i < $count; $i++) {
            $code = $this->plainCode();
            $codes[] = $code;
            $statement->execute([$userId, password_hash($code, PASSWORD_DEFAULT)]);
        }

        return $codes;
    }

    public function ensureForUser(int $userId, int $count = 10): array
    {
        if ($this->availableCount($userId) > 0) {
            return [];
        }

        return $this->regenerate($userId, $count);
    }

    public function availableCount(int $userId): int
    {
        $statement = Database::connect()->prepare("
            SELECT COUNT(*)
            FROM recovery_codes
            WHERE user_id = ?
              AND used_at IS NULL
        ");
        $statement->execute([$userId]);

        return (int) $statement->fetchColumn();
    }

    public function consume(int $userId, string $code): bool
    {
        $db = Database::connect();
        $code = strtoupper(trim($code));

        $statement = $db->prepare("SELECT id, code_hash FROM recovery_codes WHERE user_id = ? AND used_at IS NULL");
        $statement->execute([$userId]);

        foreach ($statement->fetchAll() as $row) {
            if (password_verify($code, $row['code_hash'])) {
                $update = $db->prepare("UPDATE recovery_codes SET used_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update->execute([(int) $row['id']]);
                return true;
            }
        }

        return false;
    }

    private function plainCode(): string
    {
        $value = strtoupper(bin2hex(random_bytes(8)));

        return substr($value, 0, 4) . '-' . substr($value, 4, 4) . '-' . substr($value, 8, 4) . '-' . substr($value, 12, 4);
    }
}
