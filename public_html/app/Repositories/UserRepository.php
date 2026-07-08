<?php

namespace App\Repositories;

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
        $statement = $this->connection()->prepare("
            SELECT users.id, users.person_id, users.username, users.email, users.mobile,
                   users.password_hash, users.status, users.locked_until,
                   users.failed_login_attempts, persons.full_name
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.username = ?
               OR users.email = ?
               OR users.mobile = ?
            LIMIT 1
        ");
        $statement->execute([$identifier, $identifier, $identifier]);
        $user = $statement->fetch();

        return $user ?: null;
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
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '' || $password === 'change-me-securely') {
            return null;
        }

        $name = trim((string) ($data['name'] ?? 'Super Admin')) ?: 'Super Admin';
        $mobile = trim((string) ($data['mobile'] ?? ''));

        $personId = $this->findPersonIdByEmail($email);

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

        $user = $this->findByLoginIdentifier($email);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($user === null) {
            $statement = $this->connection()->prepare("
                INSERT INTO users (
                    person_id, username, email, mobile, password_hash, status,
                    email_verified_at, created_at, updated_at
                )
                VALUES (?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $statement->execute([$personId, $email, $email, $mobile ?: null, $passwordHash]);
            $userId = (int) $this->connection()->lastInsertId();
        } else {
            $userId = (int) $user['id'];
            $statement = $this->connection()->prepare("
                UPDATE users
                SET person_id = ?, username = ?, email = ?, mobile = ?,
                    password_hash = ?, status = 'active', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([$personId, $email, $email, $mobile ?: null, $passwordHash, $userId]);
        }

        return $this->findById($userId);
    }

    private function findPersonIdByEmail(string $email): ?int
    {
        $statement = $this->connection()->prepare("SELECT id FROM persons WHERE email = ? LIMIT 1");
        $statement->execute([$email]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
