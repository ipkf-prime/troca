<?php

namespace IPKF\Database\Connections;

class ConnectionDefinition
{
    public function __construct(
        private string $name,
        private array $config,
        private bool $configured,
        private ?string $fallbackConnectionName = null
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function configured(): bool
    {
        return $this->configured;
    }

    public function fallbackConnectionName(): ?string
    {
        return $this->fallbackConnectionName;
    }

    public function usesFallback(): bool
    {
        return $this->fallbackConnectionName !== null;
    }

    public function charset(): string
    {
        return (string) ($this->config['charset'] ?? 'utf8mb4');
    }
}
