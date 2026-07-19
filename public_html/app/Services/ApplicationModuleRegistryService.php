<?php

namespace App\Services;

use App\Repositories\ApplicationModuleRepository;

class ApplicationModuleRegistryService extends BaseService
{
    public function __construct(private ?ApplicationModuleRepository $modules = null)
    {
        $this->modules ??= new ApplicationModuleRepository();
    }

    public function index(): array
    {
        return ['available' => $this->modules->available(), 'items' => $this->modules->all()];
    }

    public function save(array $input): array
    {
        if (!$this->modules->available()) {
            return ['ok' => false, 'error' => 'ابتدا Migration را اجرا کنید.'];
        }

        $key = strtolower(trim((string) ($input['module_key'] ?? '')));
        $name = trim((string) ($input['display_name'] ?? ''));
        $baseUrl = rtrim(trim((string) ($input['base_url'] ?? '')), '/');
        $callback = trim((string) ($input['sso_callback_url'] ?? ''));
        if (preg_match('/^[a-z][a-z0-9_-]{1,99}$/', $key) !== 1 || $name === '' || !$this->safeHttpsUrl($baseUrl)) {
            return ['ok' => false, 'error' => 'کلید، نام یا آدرس ماژول معتبر نیست.'];
        }
        if ($callback !== '' && !$this->safeHttpsUrl($callback)) {
            return ['ok' => false, 'error' => 'آدرس بازگشت SSO معتبر نیست.'];
        }

        $port = max(1, min(65535, (int) ($input['database_port'] ?? 3306)));
        $this->modules->save([
            'module_key' => $key,
            'display_name' => mb_substr($name, 0, 190),
            'base_url' => mb_substr($baseUrl, 0, 500),
            'sso_callback_url' => $callback !== '' ? mb_substr($callback, 0, 500) : null,
            'database_connection_name' => $this->nullable($input['database_connection_name'] ?? null, 150),
            'database_host' => $this->nullable($input['database_host'] ?? null, 255),
            'database_port' => $port,
            'database_name' => $this->nullable($input['database_name'] ?? null, 190),
            'secret_reference' => $this->nullable($input['secret_reference'] ?? null, 255),
            'is_active' => isset($input['is_active']) ? 1 : 0,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
        ]);

        return ['ok' => true, 'error' => null];
    }

    private function safeHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function nullable(mixed $value, int $length): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
