<?php

namespace IPKF\Database\Connections;

use IPKF\Database\Database;
use IPKF\Support\Env;
use IPKF\Support\ModuleRuntimeConfig;

class ConnectionRegistry
{
    private array $definitions = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    public function get(string $name): ?ConnectionDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function all(): array
    {
        return $this->definitions;
    }

    private function registerDefaults(): void
    {
        $coreConfig = Database::config();
        $this->definitions['core.primary'] = new ConnectionDefinition(
            'core.primary',
            $coreConfig,
            Database::configured()
        );

        $automationConfig = $this->automationConfig();

        if ($automationConfig === null) {
            $this->definitions['automation.primary'] = new ConnectionDefinition(
                'automation.primary',
                [],
                true,
                'core.primary'
            );
        } else {
            $this->definitions['automation.primary'] = new ConnectionDefinition(
                'automation.primary',
                $automationConfig,
                $this->completeConfig($automationConfig)
            );
        }

        $workConfig = $this->moduleConfig('work', 'WORK');
        $this->definitions['work.primary'] = $workConfig === null
            ? new ConnectionDefinition('work.primary', [], false)
            : new ConnectionDefinition('work.primary', $workConfig, $this->completeConfig($workConfig));
    }

    private function moduleConfig(string $moduleKey, string $prefix): ?array
    {
        $runtime = new ModuleRuntimeConfig();
        $module = $runtime->active($moduleKey);
        if ($module !== null) {
            return [
                'driver' => 'mysql',
                'host' => trim((string) ($module['database_host'] ?? '')),
                'database' => trim((string) ($module['database_name'] ?? '')),
                'username' => trim((string) ($module['database_username'] ?? '')),
                'password' => $runtime->secret($module, "{$prefix}_DB_PASSWORD"),
                'port' => (int) ($module['database_port'] ?? 3306),
                'charset' => trim((string) ($module['database_charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
                'ssl_mode' => trim((string) ($module['database_ssl_mode'] ?? '')),
                'connection_timeout' => max(1, min(60, (int) ($module['connection_timeout'] ?? 5))),
            ];
        }

        $present = false;
        foreach (["{$prefix}_DB_HOST", "{$prefix}_DB_DATABASE", "{$prefix}_DB_USERNAME", "{$prefix}_DB_PASSWORD"] as $key) {
            if (trim((string) Env::get($key, '')) !== '') {
                $present = true;
                break;
            }
        }
        if (!$present) {
            return null;
        }

        return [
            'driver' => 'mysql',
            'host' => Env::get("{$prefix}_DB_HOST", ''),
            'database' => Env::get("{$prefix}_DB_DATABASE", ''),
            'username' => Env::get("{$prefix}_DB_USERNAME", ''),
            'password' => Env::get("{$prefix}_DB_PASSWORD", ''),
            'port' => (int) Env::get("{$prefix}_DB_PORT", 3306),
            'charset' => Env::get("{$prefix}_DB_CHARSET", 'utf8mb4') ?: 'utf8mb4',
            'ssl_mode' => Env::get("{$prefix}_DB_SSL_MODE", ''),
            'connection_timeout' => (int) Env::get("{$prefix}_DB_CONNECTION_TIMEOUT", 5),
        ];
    }

    private function automationConfig(): ?array
    {
        $runtime = new ModuleRuntimeConfig();
        $module = $runtime->active('automation');

        if ($module !== null) {
            return [
                'driver' => 'mysql',
                'host' => trim((string) ($module['database_host'] ?? '')),
                'database' => trim((string) ($module['database_name'] ?? '')),
                'username' => trim((string) ($module['database_username'] ?? '')),
                'password' => $runtime->secret($module, 'AUTOMATION_DB_PASSWORD'),
                'port' => (int) ($module['database_port'] ?? 3306),
                'charset' => trim((string) ($module['database_charset'] ?? 'utf8mb4')) ?: 'utf8mb4',
                'ssl_mode' => trim((string) ($module['database_ssl_mode'] ?? '')),
                'connection_timeout' => max(1, min(60, (int) ($module['connection_timeout'] ?? 5))),
            ];
        }

        $keys = [
            'AUTOMATION_DB_HOST',
            'AUTOMATION_DB_DATABASE',
            'AUTOMATION_DB_USERNAME',
            'AUTOMATION_DB_PASSWORD',
        ];

        $present = false;
        foreach ($keys as $key) {
            $value = trim((string) Env::get($key, ''));
            if ($value !== '') {
                $present = true;
                break;
            }
        }

        if (!$present) {
            return null;
        }

        return [
            'driver' => 'mysql',
            'host' => Env::get('AUTOMATION_DB_HOST', ''),
            'database' => Env::get('AUTOMATION_DB_DATABASE', ''),
            'username' => Env::get('AUTOMATION_DB_USERNAME', ''),
            'password' => Env::get('AUTOMATION_DB_PASSWORD', ''),
            'port' => (int) Env::get('AUTOMATION_DB_PORT', 3306),
            'charset' => Env::get('AUTOMATION_DB_CHARSET', 'utf8mb4') ?: 'utf8mb4',
            'ssl_mode' => Env::get('AUTOMATION_DB_SSL_MODE', ''),
            'connection_timeout' => (int) Env::get('AUTOMATION_DB_CONNECTION_TIMEOUT', 5),
        ];
    }

    private function completeConfig(array $config): bool
    {
        return ($config['driver'] ?? 'mysql') === 'mysql'
            && trim((string) ($config['host'] ?? '')) !== ''
            && trim((string) ($config['database'] ?? '')) !== ''
            && trim((string) ($config['username'] ?? '')) !== ''
            && trim((string) ($config['charset'] ?? 'utf8mb4')) === 'utf8mb4';
    }
}
