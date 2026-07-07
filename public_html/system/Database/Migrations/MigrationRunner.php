<?php

namespace IPKF\Database\Migrations;

use IPKF\Core\Database;

class MigrationRunner
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function run(array $migrations): void
    {
        foreach ($migrations as $migration) {

            $name = get_class($migration);

            $stmt = $this->db->prepare("SELECT id FROM migrations WHERE migration = ?");
            $stmt->execute([$name]);

            if ($stmt->fetch()) {
                continue;
            }

            $migration->up();

            $insert = $this->db->prepare("INSERT INTO migrations (migration) VALUES (?)");
            $insert->execute([$name]);
        }
    }

    public function rollback(array $migrations): void
    {
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
    }
}