<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;

class IdentityVerificationRepository extends BaseRepository
{
    public function account(int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                users.email,
                users.mobile,
                users.email_verified_at,
                users.mobile_verified_at
            FROM users
            WHERE users.id = ?
              AND users.deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }

    public function recentChallengeCount(
        int $userId,
        string $method,
        string $purpose
    ): int {
        if (!Database::tableExists(
            'mfa_delivery_challenges'
        )) {
            return 0;
        }

        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM mfa_delivery_challenges
            WHERE user_id = ?
              AND method = ?
              AND purpose = ?
              AND created_at >= DATE_SUB(
                  CURRENT_TIMESTAMP,
                  INTERVAL 10 MINUTE
              )
        ");
        $statement->execute([
            $userId,
            $method,
            $purpose,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function createChallenge(array $data): int
    {
        $statement = $this->connection()->prepare("
            INSERT INTO mfa_delivery_challenges (
                user_id,
                method,
                purpose,
                code_hash,
                expires_at,
                created_ip,
                created_user_agent,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?,
                DATE_ADD(
                    CURRENT_TIMESTAMP,
                    INTERVAL 5 MINUTE
                ),
                ?, ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");
        $statement->execute([
            $data['user_id'],
            $data['method'],
            $data['purpose'],
            $data['code_hash'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    public function latestChallenge(
        int $userId,
        string $method,
        string $purpose
    ): ?array {
        $statement = $this->connection()->prepare("
            SELECT
                id,
                code_hash,
                attempts,
                expires_at
            FROM mfa_delivery_challenges
            WHERE user_id = ?
              AND method = ?
              AND purpose = ?
              AND consumed_at IS NULL
              AND expires_at >= CURRENT_TIMESTAMP
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute([
            $userId,
            $method,
            $purpose,
        ]);
        $challenge = $statement->fetch(PDO::FETCH_ASSOC);

        return $challenge ?: null;
    }

    public function markAttempt(int $challengeId): void
    {
        $this->connection()->prepare("
            UPDATE mfa_delivery_challenges
            SET attempts = attempts + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$challengeId]);
    }

    public function consume(int $challengeId): void
    {
        $this->connection()->prepare("
            UPDATE mfa_delivery_challenges
            SET consumed_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([$challengeId]);
    }

    public function markVerified(
        int $userId,
        string $field
    ): void {
        $column = $field === 'email'
            ? 'email_verified_at'
            : 'mobile_verified_at';
        $typeCodes = $field === 'email'
            ? ['email', 'mail']
            : ['mobile', 'cellphone', 'phone'];

        $this->connection()->prepare("
            UPDATE users
            SET {$column} = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
              AND deleted_at IS NULL
        ")->execute([$userId]);

        if (
            !Database::tableExists('person_contacts')
            || !Database::tableExists('contact_types')
        ) {
            return;
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($typeCodes), '?')
        );
        $statement = $this->connection()->prepare("
            UPDATE person_contacts
            INNER JOIN users
              ON users.person_id = person_contacts.person_id
            INNER JOIN contact_types
              ON contact_types.id =
                 person_contacts.contact_type_id
            SET person_contacts.is_verified = 1,
                person_contacts.updated_at =
                    CURRENT_TIMESTAMP
            WHERE users.id = ?
              AND LOWER(contact_types.code)
                  IN ({$placeholders})
              AND person_contacts.status = 'active'
        ");
        $statement->execute(array_merge(
            [$userId],
            $typeCodes
        ));
    }
}
