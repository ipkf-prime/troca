<?php

namespace IPKF\Core;

class Container
{
    protected array $bindings = [];

    public function bind(string $key, callable $resolver): void
    {
        $this->bindings[$key] = $resolver;
    }

    public function make(string $key)
    {
        if (isset($this->bindings[$key])) {
            return ($this->bindings[$key])($this);
        }

        return new $key;
    }
}