<?php

namespace App\Services\Automation;

class CoreReferenceValidator
{
    public function supported(string $type): bool
    {
        return in_array($type, CoreReferenceType::all(), true);
    }

    public function validate(CoreReference $reference): bool
    {
        return $this->supported($reference->type())
            && trim((string) $reference->id()) !== '';
    }
}
