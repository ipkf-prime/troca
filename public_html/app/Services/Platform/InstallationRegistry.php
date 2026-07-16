<?php

namespace App\Services\Platform;

class InstallationRegistry
{
    public function installationStates(): array
    {
        return ['planned', 'active', 'suspended', 'retired'];
    }

    public function environmentKinds(): array
    {
        return ['development', 'staging', 'production', 'testing', 'demo'];
    }
}
