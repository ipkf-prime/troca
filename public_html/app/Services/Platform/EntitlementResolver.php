<?php

namespace App\Services\Platform;

class EntitlementResolver
{
    public function licenseStatuses(): array
    {
        return ['draft', 'active', 'expired', 'suspended', 'revoked'];
    }

    public function entitlementStatuses(): array
    {
        return ['active', 'disabled', 'expired', 'revoked'];
    }
}
