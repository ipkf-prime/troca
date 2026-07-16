<?php

namespace App\Services\Platform;

class ApplicationCatalog
{
    public const OWNERSHIP_PLATFORM_CORE = 'platform_core';
    public const OWNERSHIP_SPECIALIZED = 'specialized';

    public const PLATFORM_CORE_APPLICATION = 'core';
    public const FIRST_SPECIALIZED_APPLICATION = 'automation';

    public function requiredApplicationCodes(): array
    {
        return [
            self::PLATFORM_CORE_APPLICATION,
            self::FIRST_SPECIALIZED_APPLICATION,
        ];
    }

    public function futureApplicationCodes(): array
    {
        return [
            'crm',
            'erp',
            'hr',
            'finance',
            'marketplace',
            'integration',
            'reporting',
        ];
    }
}
