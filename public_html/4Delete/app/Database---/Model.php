<?php

namespace IPKF\Database;

use IPKF\Database\DBHelper;

abstract class Model
{
    protected string $table;

    protected array $fillable = [];

    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public static function query(): QueryBuilder
    {
        return DBHelper::table((new static)->table);
    }

    public static function all(): array
    {
        return self::query()->get();
    }

    public static function find(int $id): ?array
    {
        $result = self::query()
            ->where('id', '=', $id)
            ->get();

        return $result[0] ?? null;
    }

    public static function create(array $data): bool
    {
        $instance = new static;

        $filtered = array_intersect_key($data, array_flip($instance->fillable));

        $columns = implode(',', array_keys($filtered));
        $values  = array_values($filtered);

        $placeholders = implode(',', array_fill(0, count($values), '?'));

        $sql = "INSERT INTO {$instance->table} ($columns) VALUES ($placeholders)";

        $stmt = DB::connection()->pdo()->prepare($sql);

        return $stmt->execute($values);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}