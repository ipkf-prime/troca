<?php

namespace IPKF\Core;

use PDO;

class Auth
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function attempt(string $username, string $password): bool
    {
        $stmt = $this->db->prepare("
            SELECT * FROM users WHERE username = ? LIMIT 1
        ");

        $stmt->execute([$username]);

        $user = $stmt->fetch();

        if (!$user) return false;

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        $_SESSION['user'] = $user;

        return true;
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
        session_destroy();
    }
}