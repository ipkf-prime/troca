<?php

namespace App\Services;

use App\Repositories\ApplicationModuleRepository;
use IPKF\Support\ApplicationUrlRegistry;
use IPKF\Support\Env;
use IPKF\Support\EnvironmentSecretWriter;
use Throwable;

class ApplicationModuleRegistryService extends BaseService
{
    public function __construct(
        private ?ApplicationModuleRepository $modules = null,
        private ?EnvironmentSecretWriter $secrets = null
    ) {
        $this->modules ??= new ApplicationModuleRepository();
        $this->secrets ??= new EnvironmentSecretWriter();
    }

    public function index(): array
    {
        return [
            'available' => $this->modules->available(),
            'items' => $this->modules->all(),
            'catalog' => $this->catalog(),
        ];
    }

    public function catalog(): array
    {
        $urls = new ApplicationUrlRegistry();

        return [
            'work' => [
                'name' => 'مدیریت کار و پروژه', 'base_url' => $urls->work(),
                'callback_url' => $urls->work('/auth/module-sso/callback'),
                'connection' => 'work.primary',
                'host' => (string) Env::get('WORK_DB_HOST', 'localhost'),
                'port' => (int) Env::get('WORK_DB_PORT', 3306),
                'database' => (string) Env::get('WORK_DB_DATABASE', 'troca_work'),
                'username' => (string) Env::get('WORK_DB_USERNAME', ''),
                'charset' => (string) Env::get('WORK_DB_CHARSET', 'utf8mb4'),
                'ssl_mode' => (string) Env::get('WORK_DB_SSL_MODE', ''),
                'timeout' => (int) Env::get('WORK_DB_CONNECTION_TIMEOUT', 5),
                'runtime_mode' => (string) Env::get('WORK_DB_MODE', 'fallback'),
                'secret' => 'WORK_DB_PASSWORD',
                'secret_configured' => trim((string) Env::get('WORK_DB_PASSWORD', '')) !== '',
            ],
            'ticketing' => [
               'name' => 'پشتیبانی و تیکتینگ',
               'base_url' => $urls->ticketing(),
               'callback_url' => $urls->ticketing('/auth/module-sso/callback'),
               'connection' => 'ticketing.primary',
               'host' => (string) Env::get('TICKETING_DB_HOST', 'localhost'),
               'port' => (int) Env::get('TICKETING_DB_PORT', 3306),
               'database' => (string) Env::get('TICKETING_DB_DATABASE', ''),
               'username' => (string) Env::get('TICKETING_DB_USERNAME', ''),
               'charset' => (string) Env::get('TICKETING_DB_CHARSET', 'utf8mb4'),
               'ssl_mode' => (string) Env::get('TICKETING_DB_SSL_MODE', ''),
               'timeout' => (int) Env::get('TICKETING_DB_CONNECTION_TIMEOUT', 5),
               'runtime_mode' => (string) Env::get('TICKETING_DB_MODE', 'dedicated'),
               'secret' => 'TICKETING_DB_PASSWORD',
               'secret_configured' => trim((string) Env::get('TICKETING_DB_PASSWORD', '')) !== '',
           ],
           'automation' => [
                'name' => 'اتوماسیون اداری', 'base_url' => $urls->automation(),
                'callback_url' => $urls->automation('/auth/module-sso/callback'),
                'connection' => 'automation.primary',
                'host' => (string) Env::get('AUTOMATION_DB_HOST', 'localhost'),
                'port' => (int) Env::get('AUTOMATION_DB_PORT', 3306),
                'database' => (string) Env::get('AUTOMATION_DB_DATABASE', ''),
                'username' => (string) Env::get('AUTOMATION_DB_USERNAME', ''),
                'charset' => (string) Env::get('AUTOMATION_DB_CHARSET', 'utf8mb4'),
                'ssl_mode' => (string) Env::get('AUTOMATION_DB_SSL_MODE', ''),
                'timeout' => (int) Env::get('AUTOMATION_DB_CONNECTION_TIMEOUT', 5),
                'runtime_mode' => (string) Env::get('AUTOMATION_DB_MODE', 'fallback'),
                'secret' => 'AUTOMATION_DB_PASSWORD',
                'secret_configured' => trim((string) Env::get('AUTOMATION_DB_PASSWORD', '')) !== '',
            ],
            'commerce' => ['name' => 'مدیریت بازرگانی', 'base_url' => '', 'callback_url' => '', 'connection' => 'commerce', 'database' => '', 'secret' => 'COMMERCE_DB_PASSWORD'],
            'accounting' => ['name' => 'حسابداری و مالی', 'base_url' => '', 'callback_url' => '', 'connection' => 'accounting', 'database' => '', 'secret' => 'ACCOUNTING_DB_PASSWORD'],
            'inventory' => ['name' => 'انبار و کالا', 'base_url' => '', 'callback_url' => '', 'connection' => 'inventory', 'database' => '', 'secret' => 'INVENTORY_DB_PASSWORD'],
            'crm' => ['name' => 'مدیریت ارتباط با مشتری', 'base_url' => '', 'callback_url' => '', 'connection' => 'crm', 'database' => '', 'secret' => 'CRM_DB_PASSWORD'],
            'hr' => ['name' => 'منابع انسانی', 'base_url' => '', 'callback_url' => '', 'connection' => 'hr', 'database' => '', 'secret' => 'HR_DB_PASSWORD'],
            'procurement' => ['name' => 'تدارکات و خرید', 'base_url' => '', 'callback_url' => '', 'connection' => 'procurement', 'database' => '', 'secret' => 'PROCUREMENT_DB_PASSWORD'],
            'sales' => ['name' => 'فروش و سفارشات', 'base_url' => '', 'callback_url' => '', 'connection' => 'sales', 'database' => '', 'secret' => 'SALES_DB_PASSWORD'],
            'production' => ['name' => 'تولید', 'base_url' => '', 'callback_url' => '', 'connection' => 'production', 'database' => '', 'secret' => 'PRODUCTION_DB_PASSWORD'],
            'logistics' => ['name' => 'لجستیک و حمل‌ونقل', 'base_url' => '', 'callback_url' => '', 'connection' => 'logistics', 'database' => '', 'secret' => 'LOGISTICS_DB_PASSWORD'],
        ];
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
        $charset = strtolower(trim((string) ($input['database_charset'] ?? 'utf8mb4')));
        $runtimeMode = strtolower(trim((string) ($input['runtime_mode'] ?? 'fallback')));
        if ($charset !== 'utf8mb4' || !in_array($runtimeMode, ['fallback', 'provisioning', 'dedicated'], true)) {
            return ['ok' => false, 'error' => 'Charset یا حالت اجرای ماژول معتبر نیست.'];
        }

        $catalog = $this->catalog();
        $secretReference = (string) ($catalog[$key]['secret'] ?? '');
        if ($secretReference === '' || preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $secretReference) !== 1) {
            return ['ok' => false, 'error' => 'کلید رمز این ماژول تعریف نشده است.'];
        }

        $password = (string) ($input['database_password'] ?? '');
        if ($password !== '') {
            try {
                $this->secrets->write($secretReference, $password);
            } catch (Throwable) {
                return ['ok' => false, 'error' => 'ذخیره امن رمز در ENV مشترک انجام نشد. دسترسی فایل و IPKF_SHARED_ENV را بررسی کنید.'];
            }
        }

        $this->modules->save([
            'module_key' => $key,
            'display_name' => mb_substr($name, 0, 190),
            'base_url' => mb_substr($baseUrl, 0, 500),
            'sso_callback_url' => $callback !== '' ? mb_substr($callback, 0, 500) : null,
            'database_connection_name' => $this->nullable($input['database_connection_name'] ?? null, 150),
            'database_host' => $this->nullable($input['database_host'] ?? null, 255),
            'database_port' => $port,
            'database_name' => $this->nullable($input['database_name'] ?? null, 190),
            'database_username' => $this->nullable($input['database_username'] ?? null, 190),
            'database_charset' => $charset,
            'database_ssl_mode' => $this->nullable($input['database_ssl_mode'] ?? null, 40),
            'connection_timeout' => max(1, min(60, (int) ($input['connection_timeout'] ?? 5))),
            'runtime_mode' => $runtimeMode,
            'secret_reference' => $secretReference,
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
