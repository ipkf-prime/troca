<?php

namespace IPKF\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    protected array $bindings = [];

    protected array $instances = [];

    public function bind(string $key, callable|string $resolver): void
    {
        $this->bindings[$key] = $resolver;
    }

    public function singleton(string $key, callable|string $resolver): void
    {
        $this->bindings[$key] = function (Container $container) use ($key, $resolver) {
            if (!isset($this->instances[$key])) {
                $this->instances[$key] = is_string($resolver)
                    ? $container->make($resolver)
                    : $resolver($container);
            }

            return $this->instances[$key];
        };
    }

    public function instance(string $key, object $instance): void
    {
        $this->instances[$key] = $instance;
    }

    public function make(string $key)
    {
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (isset($this->bindings[$key])) {
            $resolver = $this->bindings[$key];

            return is_string($resolver) ? $this->make($resolver) : $resolver($this);
        }

        if (!class_exists($key)) {
            throw new RuntimeException("Class not found: {$key}");
        }

        $reflection = new ReflectionClass($key);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class is not instantiable: {$key}");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return new $key();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Unable to resolve {$parameter->getName()} for {$key}");
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
