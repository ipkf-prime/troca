<?php

namespace App\Services\Automation\Correspondence;

class CorrespondenceDraftService
{
    public function __construct(private ?CorrespondenceCommandService $commands = null)
    {
        $this->commands ??= new CorrespondenceCommandService();
    }

    public function create(array $input, int $userId, array $context): array
    {
        return $this->commands->createDraft($input, $userId, $context);
    }

    public function update(string $publicReference, array $input, int $userId, array $context): array
    {
        return $this->commands->updateDraft($publicReference, $input, $userId, $context);
    }
}
