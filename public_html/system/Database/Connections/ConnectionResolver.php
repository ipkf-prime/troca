<?php

namespace IPKF\Database\Connections;

use IPKF\Database\Database;
use PDO;
use RuntimeException;

class ConnectionResolver
{
    private array $connections = [];

    public function __construct(
        private ?ConnectionRegistry $registry = null,
        private ?ConnectionFactory $factory = null
    ) {
        $this->registry ??= new ConnectionRegistry();
        $this->factory ??= new ConnectionFactory();
    }

    public function registry(): ConnectionRegistry
    {
        return $this->registry;
    }

    public function resolve(string $name): PDO
    {
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $definition = $this->registry->get($name);

        if (!$definition instanceof ConnectionDefinition) {
            throw new RuntimeException('Unknown named connection.');
        }

        if ($definition->usesFallback()) {
            $fallback = $definition->fallbackConnectionName();
            $connection = $fallback === 'core.primary'
                ? Database::connect()
                : $this->resolve((string) $fallback);

            return $this->connections[$name] = $connection;
        }

        if ($name === 'core.primary') {
            return $this->connections[$name] = Database::connect();
        }

        return $this->connections[$name] = $this->factory->make($definition);
    }
}
