<?php

namespace IPKF\Database\Connections;

use IPKF\Database\Database;
use IPKF\Support\Env;

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
            return;
        }

        $this->definitions['automation.primary'] = new ConnectionDefinition(
            'automation.primary',
            $automationConfig,
            $this->completeConfig($automationConfig)
        );
    }

    private function automationConfig(): ?array
    {
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
