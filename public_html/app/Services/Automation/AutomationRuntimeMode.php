<?php

namespace App\Services\Automation;

use IPKF\Database\Connections\ConnectionDefinition;
use IPKF\Database\Connections\ConnectionRegistry;
use IPKF\Support\Env;

class AutomationRuntimeMode
{
    public const FALLBACK = 'fallback';
    public const PROVISIONING = 'provisioning';
    public const DEDICATED = 'dedicated';
    public const INVALID = 'invalid';

    public function __construct(private ?ConnectionRegistry $registry = null)
    {
        $this->registry ??= new ConnectionRegistry();
    }

    public function value(): string
    {
        $configured = trim((string) Env::get('AUTOMATION_DB_MODE', ''));

        if ($configured === '') {
            return $this->defaultMode();
        }

        return in_array($configured, self::allowed(), true)
            ? $configured
            : self::INVALID;
    }

    public function valid(): bool
    {
        return $this->value() !== self::INVALID;
    }

    public function dedicatedRequested(): bool
    {
        return $this->value() === self::DEDICATED;
    }

    public function provisioningAllowed(): bool
    {
        return in_array($this->value(), [self::PROVISIONING, self::DEDICATED], true);
    }

    public static function allowed(): array
    {
        return [
            self::FALLBACK,
            self::PROVISIONING,
            self::DEDICATED,
        ];
    }

    private function defaultMode(): string
    {
        $definition = $this->registry->get('automation.primary');

        if ($definition instanceof ConnectionDefinition
            && !$definition->usesFallback()
            && $definition->configured()
        ) {
            return self::PROVISIONING;
        }

        return self::FALLBACK;
    }
}
