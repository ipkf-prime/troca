<?php

namespace IPKF\Database;

class QueryBuilder
{
    protected string $table;
    protected array $where = [];

    public function first(): ?array
    {
        $result = $this->get();
    
        return $result[0] ?? null;
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->where[] = [$column, $operator, $value];
        return $this;
    }

    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if ($this->where) {
            $sql .= " WHERE ";

            $parts = [];

            foreach ($this->where as $w) {
                $parts[] = "{$w[0]} {$w[1]} ?";
            }

            $sql .= implode(' AND ', $parts);
        }

        $stmt = DB::connection()->pdo()->prepare($sql);

        $values = array_map(fn($w) => $w[2], $this->where);

        $stmt->execute($values);

        return $stmt->fetchAll();
    }
}