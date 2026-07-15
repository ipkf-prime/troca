<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;
use PDO;
use Throwable;

class MigrationRunner
{
    protected ?PDO $db = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    protected function connection(): PDO
    {
        if (!$this->db instanceof PDO) {
            $this->db = Database::connect();
        }

        return $this->db;
    }

    private function createTableIfNotExists(): void
    {
        $this->connection()->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function run(array $migrations): void
    {
        if ($migrations === []) {
            return;
        }

        $this->createTableIfNotExists();

        foreach ($migrations as $migration) {

            $name = get_class($migration);

            $stmt = $this->connection()->prepare("SELECT id FROM migrations WHERE migration = ?");
            $stmt->execute([$name]);

            if ($stmt->fetch()) {
                continue;
            }

            try {
                $migration->up();
            } catch (Throwable $exception) {
                throw new MigrationExecutionException($name, $exception);
            }

            try {
                $insert = $this->connection()->prepare("INSERT INTO migrations (migration) VALUES (?)");
                $insert->execute([$name]);
            } catch (Throwable $exception) {
                throw new MigrationExecutionException($name, $exception);
            }
        }
    }

    public function rollback(array $migrations): void
    {
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }
    }
}
