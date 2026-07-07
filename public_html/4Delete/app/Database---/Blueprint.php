<?php

namespace IPKF\Database;

class Blueprint
{
    protected string $table;
    protected array $columns = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function id(): void
    {
        $this->columns[] = "id INT AUTO_INCREMENT PRIMARY KEY";
    }

    public function string(string $name, int $length = 255): void
    {
        $this->columns[] = "$name VARCHAR($length)";
    }

    public function text(string $name): void
    {
        $this->columns[] = "$name TEXT";
    }

    public function timestamps(): void
    {
        $this->columns[] = "created_at TIMESTAMP NULL";
        $this->columns[] = "updated_at TIMESTAMP NULL";
    }

    public function buildCreate(): string
    {
        $cols = implode(',', $this->columns);

        return "CREATE TABLE {$this->table} ($cols)";
    }
}