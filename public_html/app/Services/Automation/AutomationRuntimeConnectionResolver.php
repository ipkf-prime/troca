<?php

namespace App\Services\Automation;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;

class AutomationRuntimeConnectionResolver
{
    public function __construct(
        private ?ConnectionResolver $connections = null,
        private ?AutomationRuntimeMode $mode = null,
        private ?AutomationRuntimeSourceResolver $sourceResolver = null
    ) {
        $this->connections ??= new ConnectionResolver();
        $this->mode ??= new AutomationRuntimeMode($this->connections->registry());
        $this->sourceResolver ??= new AutomationRuntimeSourceResolver();
    }

    public function resolve(bool $cutoverGuardPassed): PDO
    {
        if ($this->mode->value() === AutomationRuntimeMode::INVALID) {
            throw new RuntimeException('Automation runtime mode is invalid.');
        }

        if ($this->mode->dedicatedRequested() && !$cutoverGuardPassed) {
            throw new RuntimeException('Automation runtime is unavailable.');
        }

        if ($this->sourceResolver->dedicatedActive($this->mode, $cutoverGuardPassed)) {
            return $this->connections->resolve('automation.primary');
        }

        return $this->connections->resolve('core.primary');
    }
}
