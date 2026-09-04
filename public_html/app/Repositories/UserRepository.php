<?php

namespace App\Repositories;

use App\Services\IdentityNormalizer;
use IPKF\Database\Database;
use PDOStatement;

class UserRepository extends BaseRepository
{
    protected string $table = 'users';

    public function findById(int $id): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT users.id, users.person_id, users.username, users.email, users.mobile,
                   users.password_hash, users.status, users.locked_until,
                   users.failed_login_attempts, persons.full_name
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id = ?
            LIMIT 1
        ");
        $statement->execute([$id]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    public function findByLoginIdentifier(string $identifier): ?array
    {
        $identity = $this->normalizeLoginIdentifier($identifier);
        $matches = [];

        if ($identity['email'] !== null) {
            if (Database::columnExists('users', 'email_norm')) {
                $matches[] = '(users.email_verified_at IS NOT NULL AND users.email_norm = :email_norm_user)';
            }

            if (Database::columnExists('persons', 'email_norm')) {
                $matches[] = '(users.email_verified_at IS NOT NULL AND persons.email_norm = :email_norm_person)';
            }

            $matches[] = '(users.email_verified_at IS NOT NULL AND LOWER(users.email) = :email_user)';
            $matches[] = '(users.email_verified_at IS NOT NULL AND LOWER(persons.email) = :email_person)';
        }

        if ($identity['mobile'] !== null) {
            if (Database::columnExists('users', 'mobile_norm')) {
                $matches[] = 'users.mobile_norm = :mobile_norm_user';
            }

            if (Database::columnExists('persons', 'mobile_norm')) {
                $matches[] = 'persons.mobile_norm = :mobile_norm_person';
            }

            $matches[] = 'users.mobile IN (:user_mobile, :user_mobile_no_zero, :user_mobile_98, :user_mobile_plus_98)';
            $matches[] = 'persons.mobile IN (:person_mobile, :person_mobile_no_zero, :person_mobile_98, :person_mobile_plus_98)';
        }

        if ($identity['username'] !== null) {
            if (Database::columnExists('users', 'username_norm')) {
                $matches[] = 'users.username_norm = :username_norm';
            }

            $matches[] = 'LOWER(users.username) = :username';
        }

        if ($matches === []) {
            return null;
        }

        $statement = $this->connection()->prepare("
            SELECT users.id, users.person_id, users.username, users.email, users.mobile,
                   users.password_hash, users.status, users.locked_until,
                   users.failed_login_attempts, persons.full_name
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE " . implode(' OR ', $matches) . "
            GROUP BY users.id, users.person_id, users.username, users.email, users.mobile,
                     users.password_hash, users.status, users.locked_until,
                     users.failed_login_attempts, persons.full_name
            LIMIT 2
        ");
        $this->bindLoginIdentifier($statement, $identity);
        $statement->execute();
        $users = $statement->fetchAll();

        return count($users) === 1 ? $users[0] : null;
    }

    public function resetLoginFailures(int $userId): void
    {
        $statement = $this->connection()->prepare("
            UPDATE users
            SET failed_login_attempts = 0,
                locked_until = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([$userId]);
    }

    public function updateLastLogin(int $userId): void
    {
        $statement = $this->connection()->prepare("
            UPDATE users
            SET last_login_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([$userId]);
    }

    public function updateLoginFailure(int $userId): void
    {
        $statement = $this->connection()->prepare("
            UPDATE users
            SET failed_login_attempts = failed_login_attempts + 1,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([$userId]);
    }

    public function passwordHashForUser(int $userId): ?string
    {
        $statement = $this->connection()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $hash = $statement->fetchColumn();

        return $hash === false ? null : (string) $hash;
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $statement = $this->connection()->prepare("
            UPDATE users
            SET password_hash = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $statement->execute([$passwordHash, $userId]);
    }

    public function identityValueForUser(int $userId, string $field): ?string
    {
        $column = match ($field) {
            'username' => 'COALESCE(users.username, users.username_norm)',
            'email' => 'COALESCE(users.email, persons.email, users.email_norm, persons.email_norm)',
            'mobile' => 'COALESCE(users.mobile, persons.mobile, users.mobile_norm, persons.mobile_norm)',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $statement = $this->connection()->prepare("
            SELECT {$column} AS value
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id = ?
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $value = $statement->fetchColumn();

        return $value === false || $value === null ? null : (string) $value;
    }

    public function identityValueExists(string $field, string $normalizedValue, int $exceptUserId): bool
    {
        $matches = match ($field) {
            'username' => [
                'users.username_norm = ?',
                'LOWER(users.username) = ?',
            ],
            'email' => [
                'users.email_norm = ?',
                'persons.email_norm = ?',
                'LOWER(users.email) = ?',
                'LOWER(persons.email) = ?',
            ],
            'mobile' => [
                'users.mobile_norm = ?',
                'persons.mobile_norm = ?',
                'users.mobile IN (?, ?, ?, ?)',
                'persons.mobile IN (?, ?, ?, ?)',
            ],
            default => [],
        };

        if ($matches === []) {
            return true;
        }

        $params = [$exceptUserId];

        if ($field === 'mobile') {
            $variants = [
                $normalizedValue,
                substr($normalizedValue, 1),
                '98' . substr($normalizedValue, 1),
                '+98' . substr($normalizedValue, 1),
            ];
            $params = array_merge(
                $params,
                [$normalizedValue, $normalizedValue],
                $variants,
                $variants
            );
        } else {
            $params = array_merge($params, array_fill(0, count($matches), $normalizedValue));
        }

        $statement = $this->connection()->prepare("
            SELECT COUNT(DISTINCT users.id)
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id <> ?
              AND users.status = 'active'
              AND (" . implode(' OR ', $matches) . ")
        ");
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function verifiedEmailExists(
        string $normalizedEmail,
        int $exceptUserId = 0
    ): bool {
        $normalizedEmail =
            strtolower(
                trim(
                    $normalizedEmail
                )
            );

        if (
            $normalizedEmail === ''
            || filter_var(
                $normalizedEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return true;
        }

        $statement =
            $this->connection()->prepare("
                SELECT COUNT(
                    DISTINCT users.id
                )
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
            ");

        $statement->execute([
            $exceptUserId,
            $normalizedEmail,
            $normalizedEmail,
            $normalizedEmail,
            $normalizedEmail,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    public function applyIdentityChange(int $userId, string $field, string $value, string $normalizedValue): void
    {
        if ($field === 'username') {
            $statement = $this->connection()->prepare("
                UPDATE users
                SET username = ?, username_norm = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([$value, $normalizedValue, $userId]);
            return;
        }

        if ($field === 'email') {
            $statement = $this->connection()->prepare("
                UPDATE users
                LEFT JOIN persons ON persons.id = users.person_id
                SET users.email = ?, users.email_norm = ?,
                    persons.email = ?, persons.email_norm = ?,
                    users.email_verified_at = NULL,
                    users.updated_at = CURRENT_TIMESTAMP,
                    persons.updated_at = CURRENT_TIMESTAMP
                WHERE users.id = ?
            ");
            $statement->execute([$value, $normalizedValue, $value, $normalizedValue, $userId]);
            return;
        }

        if ($field === 'mobile') {
            $statement = $this->connection()->prepare("
                UPDATE users
                LEFT JOIN persons ON persons.id = users.person_id
                SET users.mobile = ?, users.mobile_norm = ?,
                    persons.mobile = ?, persons.mobile_norm = ?,
                    users.updated_at = CURRENT_TIMESTAMP,
                    persons.updated_at = CURRENT_TIMESTAMP
                WHERE users.id = ?
            ");
            $statement->execute([$value, $normalizedValue, $value, $normalizedValue, $userId]);
        }
    }

    public function createOrUpdateAdminFromEnv(array $data): ?array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '' || $password === 'change-me-securely') {
            return null;
        }

        $name = trim((string) ($data['name'] ?? 'Super Admin')) ?: 'Super Admin';
        $normalizer = new IdentityNormalizer();
        $username = $normalizer->username((string) ($data['username'] ?? ''))
            ?? $normalizer->username((string) strtok($email, '@'))
            ?? 'admin';
        $mobile = $normalizer->mobile((string) ($data['mobile'] ?? ''));

        $personId = $this->findPersonIdByEmailOrMobile($email, $mobile);

        if ($personId === null) {
            $statement = $this->connection()->prepare("
                INSERT INTO persons (person_type, full_name, email, mobile, email_norm, mobile_norm, status, created_at, updated_at)
                VALUES ('individual', ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $statement->execute([$name, $email, $mobile ?: null, $email, $mobile]);
            $personId = (int) $this->connection()->lastInsertId();
        } else {
            $statement = $this->connection()->prepare("
                UPDATE persons
                SET full_name = ?, email = ?, mobile = ?, email_norm = ?, mobile_norm = ?,
                    status = 'active', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([$name, $email, $mobile ?: null, $email, $mobile, $personId]);
        }

        $user = $this->findByLoginIdentifier($email) ?? $this->findByLoginIdentifier($username);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($user === null) {
            $statement = $this->connection()->prepare("
                INSERT INTO users (
                    person_id, username, username_norm, email, email_norm, mobile, mobile_norm, password_hash, status,
                    email_verified_at, created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $statement->execute([$personId, $username, $username, $email, $email, $mobile ?: null, $mobile, $passwordHash]);
            $userId = (int) $this->connection()->lastInsertId();
        } else {
            $userId = (int) $user['id'];
            $statement = $this->connection()->prepare("
                UPDATE users
                SET person_id = ?, username = ?, username_norm = ?, email = ?, email_norm = ?,
                    mobile = ?, mobile_norm = ?, password_hash = ?, status = 'active', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([$personId, $username, $username, $email, $email, $mobile ?: null, $mobile, $passwordHash, $userId]);
        }

        return $this->findById($userId);
    }

    private function findPersonIdByEmailOrMobile(string $email, ?string $mobile): ?int
    {
        $statement = $this->connection()->prepare("
            SELECT id
            FROM persons
            WHERE LOWER(email) = ?
               OR (? IS NOT NULL AND mobile = ?)
            LIMIT 1
        ");
        $statement->execute([$email, $mobile, $mobile]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function normalizeLoginIdentifier(string $identifier): array
    {
        $normalizer = new IdentityNormalizer();
        $value = trim($normalizer->englishDigits($identifier));
        $compact = preg_replace('/[\s\-\(\)]+/', '', $value) ?? $value;
        $email = str_contains($value, '@') ? $normalizer->email($value) : null;
        $mobile = $normalizer->mobile($compact);
        $username = $email === null && $mobile === null ? $normalizer->username($value) : null;

        return [
            'email' => $email,
            'mobile' => $mobile,
            'username' => $username,
        ];
    }

    private function bindLoginIdentifier(PDOStatement $statement, array $identity): void
    {
        if ($identity['email'] !== null) {
            if (Database::columnExists('users', 'email_norm')) {
                $statement->bindValue(':email_norm_user', $identity['email']);
            }

            if (Database::columnExists('persons', 'email_norm')) {
                $statement->bindValue(':email_norm_person', $identity['email']);
            }

            $statement->bindValue(':email_user', $identity['email']);
            $statement->bindValue(':email_person', $identity['email']);
        }

        if ($identity['mobile'] !== null) {
            $mobile = $identity['mobile'];

            if (Database::columnExists('users', 'mobile_norm')) {
                $statement->bindValue(':mobile_norm_user', $mobile);
            }

            if (Database::columnExists('persons', 'mobile_norm')) {
                $statement->bindValue(':mobile_norm_person', $mobile);
            }
            $variants = [
                $mobile,
                substr($mobile, 1),
                '98' . substr($mobile, 1),
                '+98' . substr($mobile, 1),
            ];

            foreach (['user', 'person'] as $prefix) {
                $statement->bindValue(":{$prefix}_mobile", $variants[0]);
                $statement->bindValue(":{$prefix}_mobile_no_zero", $variants[1]);
                $statement->bindValue(":{$prefix}_mobile_98", $variants[2]);
                $statement->bindValue(":{$prefix}_mobile_plus_98", $variants[3]);
            }
        }

        if ($identity['username'] !== null) {
            if (Database::columnExists('users', 'username_norm')) {
                $statement->bindValue(':username_norm', $identity['username']);
            }

            $statement->bindValue(':username', $identity['username']);
        }
    }
}
