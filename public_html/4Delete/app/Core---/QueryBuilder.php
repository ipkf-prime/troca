<?php

namespace IPKF\Core;

use PDO;

class QueryBuilder
{
    protected PDO $db;
    protected string $table = '';
    protected array $wheres = [];

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): static
    {
        $this->wheres[] = [$column, $operator, $value];
        return $this;
    }

    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE ";

            $conditions = [];
            $params = [];

            foreach ($this->wheres as $where) {
                $conditions[] = "{$where[0]} {$where[1]} ?";
                $params[] = $where[2];
            }

            $sql .= implode(' AND ', $conditions);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();
        }

        return $this->db->query($sql)->fetchAll();
    }
}