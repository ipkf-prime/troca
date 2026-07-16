<?php

namespace App\Services\Platform;

class ModuleGate
{
    public const MODULE_NOT_INSTALLED = 'module_not_installed';
    public const MODULE_DISABLED = 'module_disabled';
    public const MODULE_UNLICENSED = 'module_unlicensed';
    public const LICENSE_EXPIRED = 'license_expired';
    public const DEPENDENCY_BLOCKED = 'dependency_blocked';
    public const ALLOWED = 'allowed';

    public function outcomes(): array
    {
        return [
            self::MODULE_NOT_INSTALLED,
            self::MODULE_DISABLED,
            self::MODULE_UNLICENSED,
            self::LICENSE_EXPIRED,
            self::DEPENDENCY_BLOCKED,
            self::ALLOWED,
        ];
    }
}
