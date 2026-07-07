<?php

namespace IPKF\Database;

use PDO;

class Schema
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::connection()->pdo();
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);

        $callback($blueprint);

        $sql = $blueprint->buildCreate();

        $this->pdo->exec($sql);
    }

    public function drop(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS $table");
    }
}