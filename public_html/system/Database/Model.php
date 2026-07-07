<?php

namespace IPKF\Database;

abstract class Model
{
    protected string $table = '';

    protected array $fillable = [];

    public function getTable(): string
    {
        return $this->table;
    }

    public function getFillable(): array
    {
        return $this->fillable;
    }
}
