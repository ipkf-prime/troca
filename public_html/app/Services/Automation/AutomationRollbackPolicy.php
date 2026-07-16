<?php

namespace App\Services\Automation;

class AutomationRollbackPolicy
{
    public function automaticCutoverEnabled(): bool
    {
        return false;
    }

    public function automaticRollbackEnabled(): bool
    {
        return false;
    }

    public function explicitRollbackAvailable(): bool
    {
        return true;
    }
}
