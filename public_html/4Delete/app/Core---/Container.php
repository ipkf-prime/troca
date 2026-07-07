<?php

namespace IPKF\Core;

class Container
{
    protected array $instances = [];

    public function singleton(string $key, object $object): void
    {
        $this->instances[$key] = $object;
    }

    public function get(string $key): ?object
    {
        return $this->instances[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->instances[$key]);
    }
}