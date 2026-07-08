<?php

namespace App\Services;

use IPKF\Database\Database;

class RecoveryCodeService extends BaseService
{
    public function regenerate(int $userId, int $count = 8): array
    {
        $db = Database::connect();
        $db->prepare('DELETE FROM recovery_codes WHERE user_id = ?')->execute([$userId]);

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

    public function consume(int $userId, string $code): bool
    {
        $db = Database::connect();
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
        return bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4));
    }
}
