<?php

namespace IPKF\Installer;

use IPKF\Core\Database;
use IPKF\Database\DatabaseManager;

class InstallerController
{
    public function step1(): void
    {
        echo "INSTALLER STEP 1 - DATABASE CONFIG";
    }

    public function saveDatabase(): void
    {
        file_put_contents(
            __DIR__ . '/../storage/install.lock',
            json_encode($_POST)
        );

        echo "DB CONFIG SAVED";
    }

    public function createAdmin(): void
    {
        $db = Database::connect();

        $stmt = $db->prepare("
            INSERT INTO users (name, username, password, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['username'],
            password_hash($_POST['password'], PASSWORD_BCRYPT),
            'super_admin'
        ]);

        echo "ADMIN CREATED";
    }

    public function finish(): void
    {
        file_put_contents(__DIR__ . '/../storage/installed.lock', 'done');

        echo "INSTALLATION COMPLETE";
    }
}