<?php

namespace IPKF\Installer;

use IPKF\Support\Config;
use IPKF\Support\Env;

class EnvironmentChecker
{
    public function check(): array
    {
        return [
            'storage_writable' => is_writable(BASE_PATH . '/storage'),
            'env_loaded' => Env::loaded(),
            'config_loaded' => Config::loaded(),
        ];
    }
}
