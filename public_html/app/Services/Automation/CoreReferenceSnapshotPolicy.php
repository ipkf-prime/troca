<?php

namespace App\Services\Automation;

class CoreReferenceSnapshotPolicy
{
    public function requiredFields(): array
    {
        return [
            CoreReferenceType::ORGANIZATION => ['title'],
            CoreReferenceType::ORG_UNIT => ['title'],
            CoreReferenceType::PERSON => ['display_name'],
            CoreReferenceType::POSITION => ['title'],
            CoreReferenceType::USER => ['display_name'],
        ];
    }

    public function documented(): bool
    {
        return true;
    }
}
