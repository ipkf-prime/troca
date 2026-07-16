<?php

namespace App\Services\Automation;

class CoreReference
{
    public function __construct(
        private string $type,
        private int|string $id,
        private array $snapshot = []
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function id(): int|string
    {
        return $this->id;
    }

    public function snapshot(): array
    {
        return $this->snapshot;
    }
}
