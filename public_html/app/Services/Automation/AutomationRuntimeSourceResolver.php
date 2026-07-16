<?php

namespace App\Services\Automation;

class AutomationRuntimeSourceResolver
{
    public function dedicatedActive(AutomationRuntimeMode $mode, bool $cutoverGuardPassed): bool
    {
        return $mode->dedicatedRequested() && $mode->valid() && $cutoverGuardPassed;
    }

    public function source(AutomationRuntimeMode $mode, bool $cutoverGuardPassed): string
    {
        return $this->dedicatedActive($mode, $cutoverGuardPassed)
            ? AutomationRuntimeMode::DEDICATED
            : AutomationRuntimeMode::FALLBACK;
    }
}
