<?php

namespace App\Repositories;

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
            $matches[] = 'LOWER(users.email) = :email_user';
            $matches[] = 'LOWER(persons.email) = :email_person';
        }

        if ($identity['mobile'] !== null) {
            $matches[] = 'users.mobile IN (:user_mobile, :user_mobile_no_zero, :user_mobile_98, :user_mobile_plus_98)';
            $matches[] = 'persons.mobile IN (:person_mobile, :person_mobile_no_zero, :person_mobile_98, :person_mobile_plus_98)';
        }

        if ($identity['username'] !== null) {
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

    public function updateLoginSuccess(int $userId): void
    {
        $statement = $this->connection()->prepare("
            UPDATE users
            SET failed_login_attempts = 0,
                locked_until = NULL,
                last_login_at = CURRENT_TIMESTAMP,
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

    public function createOrUpdateAdminFromEnv(array $data): ?array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '' || $password === 'change-me-securely') {
            return null;
        }

        $name = trim((string) ($data['name'] ?? 'Super Admin')) ?: 'Super Admin';
        $username = trim((string) ($data['username'] ?? ''));
        $username = $username !== '' ? $username : strtok($email, '@');
        $mobile = $this->normalizeMobile((string) ($data['mobile'] ?? ''));

        $personId = $this->findPersonIdByEmailOrMobile($email, $mobile);

        if ($personId === null) {
            $statement = $this->connection()->prepare("
                INSERT INTO persons (person_type, full_name, email, mobile, status, created_at, updated_at)
                VALUES ('individual', ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $statement->execute([$name, $email, $mobile ?: null]);
            $personId = (int) $this->connection()->lastInsertId();
        } else {
            $statement = $this->connection()->prepare("
                UPDATE persons
                SET full_name = ?, mobile = ?, status = 'active', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([$name, $mobile ?: null, $personId]);
        }

        $user = $this->findByLoginIdentifier($email) ?? $this->findByLoginIdentifier($username);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($user === null) {
            $statement = $this->connection()->prepare("
                INSERT INTO users (
                    person_id, username, email, mobile, password_hash, status,
                    email_verified_at, created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $statement->execute([$personId, $username, $email, $mobile ?: null, $passwordHash]);
            $userId = (int) $this->connection()->lastInsertId();
        } else {
            $userId = (int) $user['id'];
            $statement = $this->connection()->prepare("
                UPDATE users
                SET person_id = ?, username = ?, email = ?, mobile = ?,
                    password_hash = ?, status = 'active', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([$personId, $username, $email, $mobile ?: null, $passwordHash, $userId]);
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
        $value = trim($this->englishDigits($identifier));
        $compact = preg_replace('/[\s\-\(\)]+/', '', $value) ?? $value;
        $email = str_contains($value, '@') ? strtolower($value) : null;
        $mobile = $this->normalizeMobile($compact);
        $username = $email === null && $mobile === null ? strtolower($value) : null;

        return [
            'email' => $email,
            'mobile' => $mobile,
            'username' => $username,
        ];
    }

    private function bindLoginIdentifier(PDOStatement $statement, array $identity): void
    {
        if ($identity['email'] !== null) {
            $statement->bindValue(':email_user', $identity['email']);
            $statement->bindValue(':email_person', $identity['email']);
        }

        if ($identity['mobile'] !== null) {
            $mobile = $identity['mobile'];
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
            $statement->bindValue(':username', $identity['username']);
        }
    }

    private function normalizeMobile(string $mobile): ?string
    {
        $value = preg_replace('/[\s\-\(\)]+/', '', trim($this->englishDigits($mobile))) ?? '';
        $value = ltrim($value, '+');

        if (preg_match('/^09\d{9}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^9\d{9}$/', $value) === 1) {
            return '0' . $value;
        }

        if (preg_match('/^989\d{9}$/', $value) === 1) {
            return '0' . substr($value, 2);
        }

        return null;
    }

    private function englishDigits(string $value): string
    {
        return strtr($value, [
            "\u{06F0}" => '0',
            "\u{06F1}" => '1',
            "\u{06F2}" => '2',
            "\u{06F3}" => '3',
            "\u{06F4}" => '4',
            "\u{06F5}" => '5',
            "\u{06F6}" => '6',
            "\u{06F7}" => '7',
            "\u{06F8}" => '8',
            "\u{06F9}" => '9',
            "\u{0660}" => '0',
            "\u{0661}" => '1',
            "\u{0662}" => '2',
            "\u{0663}" => '3',
            "\u{0664}" => '4',
            "\u{0665}" => '5',
            "\u{0666}" => '6',
            "\u{0667}" => '7',
            "\u{0668}" => '8',
            "\u{0669}" => '9',
        ]);
    }
}
