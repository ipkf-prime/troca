<?php

namespace IPKF\Installer;

use IPKF\Support\Env;

class InstallationState
{
    public function installed(): bool
    {
        return filter_var(Env::get('IPKF_INSTALLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function canAccess(): bool
    {
        return Env::isDebug() || !$this->installed();
    }
}
