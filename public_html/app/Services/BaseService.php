<?php

namespace IPKF\Services;

class BaseService
{
    protected array $data = [];

    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function all(): array
    {
        return $this->data;
    }
}