<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;
use Throwable;

class LoginHistoryRepository extends BaseRepository
{
    public function record(array $data): void
    {
        if (!Database::tableExists('auth_login_history')) {
            return;
        }

        $statement = $this->connection()->prepare("
            INSERT INTO auth_login_history (
                user_id,
                role_assignment_id,
                role_code_snapshot,
                role_title_snapshot,
                auth_method,
                mfa_verified,
                session_hash,
                ip_address,
                user_agent,
                browser_label,
                logged_in_at,
                created_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $statement->execute([
            (int) $data['user_id'],
            $data['role_assignment_id'] ?? null,
            $data['role_code_snapshot'] ?? null,
            $data['role_title_snapshot'] ?? null,
            $data['auth_method'] ?? 'session',
            !empty($data['mfa_verified']) ? 1 : 0,
            $data['session_hash'] ?? null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
            $data['browser_label'] ?? null,
        ]);
    }

    public function recent(
        int $userId,
        int $limit = 10
    ): array {
        $limit = max(1, min(50, $limit));

        if (!Database::tableExists('auth_login_history')) {
            return $this->legacyFallback($userId);
        }

        $statement = $this->connection()->prepare("
            SELECT
                id,
                role_assignment_id,
                role_code_snapshot,
                role_title_snapshot,
                auth_method,
                mfa_verified,
                ip_address,
                browser_label,
                logged_in_at
            FROM auth_login_history
            WHERE user_id = ?
            ORDER BY logged_in_at DESC, id DESC
            LIMIT {$limit}
        ");
        $statement->execute([$userId]);
        $items = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $items !== []
            ? $items
            : $this->legacyFallback($userId);
    }

    private function legacyFallback(int $userId): array
    {
        try {
            $statement = $this->connection()->prepare("
                SELECT last_login_at
                FROM users
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $statement->execute([$userId]);
            $lastLoginAt = $statement->fetchColumn();

            if (
                $lastLoginAt === false
                || $lastLoginAt === null
                || trim((string) $lastLoginAt) === ''
            ) {
                return [];
            }

            return [[
                'id' => 0,
                'role_assignment_id' => null,
                'role_code_snapshot' => null,
                'role_title_snapshot' => null,
                'auth_method' => 'legacy',
                'mfa_verified' => 0,
                'ip_address' => null,
                'browser_label' => null,
                'logged_in_at' => (string) $lastLoginAt,
            ]];
        } catch (Throwable) {
            return [];
        }
    }
}
