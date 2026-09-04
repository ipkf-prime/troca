<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;
use Throwable;

class IdentityVerificationRepository extends BaseRepository
{
    public function account(int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                users.email,
                users.email_norm,
                users.mobile,
                users.mobile_norm,
                users.status,
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

    public function recentChallengeCountByPurposePrefix(
        int $userId,
        string $method,
        string $purposePrefix,
        int $minutes = 10
    ): int {
        $minutes =
            max(
                1,
                min(
                    60,
                    $minutes
                )
            );

        $statement =
            $this->connection()->prepare("
                SELECT COUNT(*)
                FROM mfa_delivery_challenges
                WHERE user_id = ?
                  AND method = ?
                  AND purpose LIKE CONCAT(?, '%')
                  AND created_at >=
                      DATE_SUB(
                          CURRENT_TIMESTAMP,
                          INTERVAL {$minutes} MINUTE
                      )
            ");

        $statement->execute([
            $userId,
            $method,
            $purposePrefix,
        ]);

        return
            (int) $statement->fetchColumn();
    }

    public function recentChallengeCountByIp(
        string $ip,
        string $method,
        string $purposePrefix,
        int $minutes = 10
    ): int {
        $ip =
            trim($ip);

        if ($ip === '') {
            return 0;
        }

        $minutes =
            max(
                1,
                min(
                    60,
                    $minutes
                )
            );

        $statement =
            $this->connection()->prepare("
                SELECT COUNT(*)
                FROM mfa_delivery_challenges
                WHERE created_ip = ?
                  AND method = ?
                  AND purpose LIKE CONCAT(?, '%')
                  AND created_at >=
                      DATE_SUB(
                          CURRENT_TIMESTAMP,
                          INTERVAL {$minutes} MINUTE
                      )
            ");

        $statement->execute([
            $ip,
            $method,
            $purposePrefix,
        ]);

        return
            (int) $statement->fetchColumn();
    }

    public function latestChallengeRecord(
        int $userId,
        string $method,
        string $purpose
    ): ?array {
        $statement =
            $this->connection()->prepare("
                SELECT
                    id,
                    user_id,
                    method,
                    purpose,
                    code_hash,
                    attempts,
                    expires_at,
                    consumed_at,
                    created_at
                FROM mfa_delivery_challenges
                WHERE user_id = ?
                  AND method = ?
                  AND purpose = ?
                ORDER BY id DESC
                LIMIT 1
            ");

        $statement->execute([
            $userId,
            $method,
            $purpose,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
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
            $data['created_ip']
                ?? $_SERVER['REMOTE_ADDR']
                ?? null,
            $data['created_user_agent']
                ?? $_SERVER['HTTP_USER_AGENT']
                ?? null,
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
                user_id,
                method,
                purpose,
                code_hash,
                attempts,
                expires_at,
                created_at
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

    public function claimVerifiedEmail(
        int $userId,
        string $expectedEmail
    ): bool {
        $expectedEmail =
            strtolower(
                trim(
                    $expectedEmail
                )
            );

        if (
            $userId < 1
            || $expectedEmail === ''
            || filter_var(
                $expectedEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return false;
        }

        $db = $this->connection();

        $lookup =
            $db->prepare("
                SELECT
                    email,
                    email_norm
                FROM users
                WHERE id = ?
                  AND deleted_at IS NULL
                  AND status = 'active'
                  AND mobile_verified_at
                        IS NOT NULL
                LIMIT 1
            ");

        $lookup->execute([
            $userId,
        ]);

        $row =
            $lookup->fetch(
                PDO::FETCH_ASSOC
            );

        if (!is_array($row)) {
            return false;
        }

        $email =
            strtolower(
                trim(
                    (string) (
                        $row['email_norm']
                        ?: $row['email']
                        ?: ''
                    )
                )
            );

        if (
            $email === ''
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
            || $email !== $expectedEmail
        ) {
            return false;
        }

        $lockName =
            'ipkf_email_verify_'
            . substr(
                hash(
                    'sha256',
                    $email
                ),
                0,
                40
            );

        $locked = false;

        try {
            $lock =
                $db->prepare("
                    SELECT GET_LOCK(?, 5)
                ");

            $lock->execute([
                $lockName,
            ]);

            $locked =
                (int) $lock->fetchColumn()
                === 1;

            if (!$locked) {
                return false;
            }

            $current =
                $db->prepare("
                    SELECT
                        email,
                        email_norm,
                        email_verified_at
                    FROM users
                    WHERE id = ?
                      AND deleted_at IS NULL
                      AND status = 'active'
                      AND mobile_verified_at
                            IS NOT NULL
                    LIMIT 1
                ");

            $current->execute([
                $userId,
            ]);

            $account =
                $current->fetch(
                    PDO::FETCH_ASSOC
                );

            if (!is_array($account)) {
                return false;
            }

            $currentEmail =
                strtolower(
                    trim(
                        (string) (
                            $account['email_norm']
                            ?: $account['email']
                            ?: ''
                        )
                    )
                );

            if (
                $currentEmail !== $email
                || $currentEmail !== $expectedEmail
            ) {
                return false;
            }

            if (
                !empty(
                    $account[
                        'email_verified_at'
                    ]
                )
            ) {
                return true;
            }

            $conflict =
                $db->prepare("
                    SELECT users.id
                    FROM users
                    LEFT JOIN persons
                        ON persons.id =
                            users.person_id
                    WHERE users.id <> ?
                      AND users.deleted_at
                            IS NULL
                      AND users.email_verified_at
                            IS NOT NULL
                      AND (
                        users.email_norm = ?
                        OR persons.email_norm = ?
                        OR LOWER(users.email) = ?
                        OR LOWER(persons.email) = ?
                      )
                    LIMIT 1
                ");

            $conflict->execute([
                $userId,
                $email,
                $email,
                $email,
                $email,
            ]);

            if (
                $conflict->fetchColumn()
                !== false
            ) {
                return false;
            }

            $update =
                $db->prepare("
                    UPDATE users
                    SET email_verified_at =
                            CURRENT_TIMESTAMP,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND deleted_at IS NULL
                      AND status = 'active'
                      AND mobile_verified_at
                            IS NOT NULL
                      AND email_verified_at
                            IS NULL
                      AND (
                        email_norm = ?
                        OR LOWER(email) = ?
                      )
                ");

            $update->execute([
                $userId,
                $email,
                $email,
            ]);

            return
                $update->rowCount() === 1;

        } catch (Throwable) {
            return false;

        } finally {
            if ($locked) {
                try {
                    $release =
                        $db->prepare("
                            SELECT RELEASE_LOCK(?)
                        ");

                    $release->execute([
                        $lockName,
                    ]);
                } catch (Throwable) {
                    /*
                     * Named locks are connection-scoped.
                     */
                }
            }
        }
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
