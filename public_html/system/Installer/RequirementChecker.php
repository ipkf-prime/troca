<?php

namespace IPKF\Installer;

class RequirementChecker
{
    public function check(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_supported' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'pdo_available' => extension_loaded('pdo'),
            'pdo_mysql_available' => extension_loaded('pdo_mysql'),
        ];
    }
}
