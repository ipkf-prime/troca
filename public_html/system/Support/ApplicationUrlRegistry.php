<?php

namespace IPKF\Support;

class ApplicationUrlRegistry
{
    public function core(string $path = ''): string
    {
        return $this->url('CORE_APP_URL', $path);
    }

    public function automation(string $path = ''): string
    {
        return $this->moduleUrl('automation', 'AUTOMATION_APP_URL', $path);
    }

    public function work(string $path = ''): string
    {
        return $this->moduleUrl('work', 'WORK_APP_URL', $path);
    }

    public function ticketing(string $path = ''): string
    {
        return $this->moduleUrl('ticketing', 'TICKETING_APP_URL', $path);
    }

    public function automationLaunch(string $path = '/admin/automation', ?string $requestHost = null): string
    {
        $host = $requestHost ?? (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($this->isAutomationHost($host)) {
            return $this->automation($path);
        }

        return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($path));
    }

    public function workLaunch(string $path = '/admin/work', ?string $requestHost = null): string
    {
        $host = $requestHost ?? (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($this->isWorkHost($host)) {
            return $this->work($path);
        }

        return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($path));
    }

    public function ticketingLaunch(string $path = '/admin/ticketing', ?string $requestHost = null): string
    {
        $host = $requestHost ?? (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($this->isTicketingHost($host)) {
            return $this->ticketing($path);
        }

        return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($path));
    }

    public function applicationModule(
        string $moduleKey,
        string $path = ''
    ): string {
        $base = $this->moduleBaseUrl(
            $moduleKey,
            ''
        );

        $normalizedPath =
            $path === ''
                ? ''
                : '/' . ltrim($path, '/');

        return $base !== ''
            ? $base . $normalizedPath
            : (
                $normalizedPath !== ''
                    ? $normalizedPath
                    : '/'
            );
    }


    public function applicationModuleHost(
        string $moduleKey
    ): ?string {
        $base = $this->moduleBaseUrl(
            $moduleKey,
            ''
        );

        $host = $base !== ''
            ? parse_url(
                $base,
                PHP_URL_HOST
            )
            : null;

        return is_string($host)
            && $host !== ''
                ? $this->normalizeHost($host)
                : null;
    }


    public function applicationModuleKeyForHost(
        string $requestHost
    ): ?string {
        $requestHost =
            $this->normalizeHost(
                $requestHost
            );

        if ($requestHost === '') {
            return null;
        }

        foreach (
            (new ModuleRuntimeConfig())->allActive()
            as $module
        ) {
            $moduleKey = trim(
                (string) (
                    $module['module_key']
                    ?? ''
                )
            );

            $baseUrl = trim(
                (string) (
                    $module['base_url']
                    ?? ''
                )
            );

            if (
                $moduleKey === ''
                || $baseUrl === ''
            ) {
                continue;
            }

            $moduleHost =
                parse_url(
                    $baseUrl,
                    PHP_URL_HOST
                );

            if (
                is_string($moduleHost)
                && $moduleHost !== ''
                && hash_equals(
                    $this->normalizeHost(
                        $moduleHost
                    ),
                    $requestHost
                )
            ) {
                return $moduleKey;
            }
        }

        return null;
    }


    public function isApplicationModuleHost(
        string $requestHost
    ): bool {
        return
            $this->applicationModuleKeyForHost(
                $requestHost
            ) !== null;
    }


    public function coreHost(): ?string
    {
        return $this->configuredHost('CORE_APP_URL');
    }

    public function automationHost(): ?string
    {
        $url = $this->moduleBaseUrl('automation', 'AUTOMATION_APP_URL');
        $host = $url !== '' ? parse_url($url, PHP_URL_HOST) : null;
        return is_string($host) && $host !== '' ? $this->normalizeHost($host) : null;
    }

    public function workHost(): ?string
    {
        return $this->moduleHost('work', 'WORK_APP_URL');
    }

    public function ticketingHost(): ?string
    {
        return $this->moduleHost('ticketing', 'TICKETING_APP_URL');
    }

    public function guardEnabled(): bool
    {
        return filter_var(Env::get('APP_HOST_GUARD_ENABLED', false), FILTER_VALIDATE_BOOL);
    }

    public function allowed(string $requestHost): bool
    {
        $host = $this->normalizeHost($requestHost);
        $allowed = array_filter(array_unique(array_merge(
            [$this->coreHost(), $this->automationHost(), $this->workHost(), $this->ticketingHost()],
            array_map(fn (string $item): string => $this->normalizeHost($item), explode(',', (string) Env::get('ALLOWED_APP_HOSTS', '')))
        )));

        return $allowed === [] || in_array($host, $allowed, true);
    }

    public function isAutomationHost(string $requestHost): bool
    {
        $configured = $this->automationHost();
        return $configured !== null && hash_equals($configured, $this->normalizeHost($requestHost));
    }

    public function isCoreHost(string $requestHost): bool
    {
        $configured = $this->coreHost();
        return $configured !== null && hash_equals($configured, $this->normalizeHost($requestHost));
    }

    public function isWorkHost(string $requestHost): bool
    {
        $configured = $this->workHost();
        return $configured !== null && hash_equals($configured, $this->normalizeHost($requestHost));
    }

    public function isTicketingHost(string $requestHost): bool
    {
        $configured = $this->ticketingHost();
        return $configured !== null && hash_equals($configured, $this->normalizeHost($requestHost));
    }

    public function redirectTarget(string $requestHost, string $requestUri): ?string
    {
        if (!$this->guardEnabled()) {
            return null;
        }

        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $automationPath = $path === '/admin/automation' || str_starts_with($path, '/admin/automation/');
        $workPath = $path === '/admin/work' || str_starts_with($path, '/admin/work/');
        $ticketingPath = $path === '/admin/ticketing' || str_starts_with($path, '/admin/ticketing/');
        $modulePath = $automationPath || $workPath || $ticketingPath;
        $requestIsAutomation = $this->isAutomationHost($requestHost);
        $requestIsWork = $this->isWorkHost($requestHost);
        $requestIsTicketing = $this->isTicketingHost($requestHost);
        $requestIsModule = $requestIsAutomation || $requestIsWork || $requestIsTicketing;

        if ($path === '/' && $requestIsAutomation) {
            return $this->automation('/admin/automation');
        }

        if ($path === '/' && $requestIsWork) {
            return $this->work('/admin/work');
        }

        if ($path === '/' && $requestIsTicketing) {
            return $this->ticketing('/admin/ticketing');
        }

        if ($automationPath && $this->isCoreHost($requestHost)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($requestUri));
        }

        if ($workPath && $this->isCoreHost($requestHost)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($requestUri));
        }

        if ($ticketingPath && $this->isCoreHost($requestHost)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($requestUri));
        }

        if ($automationPath && !$requestIsAutomation && $this->automationHost() !== null) {
            return $this->automation($requestUri);
        }

        if ($workPath && !$requestIsWork && $this->workHost() !== null) {
            return $this->work($requestUri);
        }

        if ($ticketingPath && !$requestIsTicketing && $this->ticketingHost() !== null) {
            return $this->ticketing($requestUri);
        }

        if ($requestIsAutomation && $this->isCentralAuthenticationPath($path)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode('/admin/automation'));
        }

        if ($requestIsWork && $this->isCentralAuthenticationPath($path)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode('/admin/work'));
        }

        if ($requestIsTicketing && $this->isCentralAuthenticationPath($path)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode('/admin/ticketing'));
        }

        if ($requestIsModule
            && str_starts_with($path, '/admin')
            && !$modulePath
            && $path !== '/admin/logout'
            && $this->coreHost() !== null
        ) {
            return $this->core($requestUri);
        }

        return null;
    }

    public function adminHome(string $requestHost): string
    {
        if ($this->isAutomationHost($requestHost)) {
            return $this->automation('/admin/automation');
        }

        if ($this->isWorkHost($requestHost)) {
            return $this->work('/admin/work');
        }

        if ($this->isTicketingHost($requestHost)) {
            return $this->ticketing('/admin/ticketing');
        }

        return $this->core('/admin/dashboard');
    }

    private function url(string $key, string $path): string
    {
        $base = rtrim(trim((string) Env::get($key, '')), '/');
        $normalizedPath = $path === '' ? '' : '/' . ltrim($path, '/');

        return $base !== '' ? $base . $normalizedPath : ($normalizedPath !== '' ? $normalizedPath : '/');
    }

    private function moduleUrl(string $moduleKey, string $fallbackKey, string $path): string
    {
        $base = $this->moduleBaseUrl($moduleKey, $fallbackKey);
        $normalizedPath = $path === '' ? '' : '/' . ltrim($path, '/');

        return $base !== '' ? $base . $normalizedPath : ($normalizedPath !== '' ? $normalizedPath : '/');
    }

    private function moduleBaseUrl(string $moduleKey, string $fallbackKey): string
    {
        $module =
            Env::moduleRuntimeOverrideEnabled(
                $moduleKey
            )
                ? null
                : (
                    new ModuleRuntimeConfig()
                )->active(
                    $moduleKey
                );

        $base =
            $module !== null
                ? trim(
                    (string) (
                        $module[
                            'base_url'
                        ]
                        ?? ''
                    )
                )
                : trim(
                    (string) Env::get(
                        $fallbackKey,
                        ''
                    )
                );

        if ($base !== '' && filter_var($base, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return rtrim($base, '/');
    }

    private function configuredHost(string $key): ?string
    {
        $url = trim((string) Env::get($key, ''));
        $host = $url !== '' ? parse_url($url, PHP_URL_HOST) : null;
        return is_string($host) && $host !== '' ? $this->normalizeHost($host) : null;
    }

    private function moduleHost(string $moduleKey, string $fallbackKey): ?string
    {
        $url = $this->moduleBaseUrl($moduleKey, $fallbackKey);
        $host = $url !== '' ? parse_url($url, PHP_URL_HOST) : null;
        return is_string($host) && $host !== '' ? $this->normalizeHost($host) : null;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        return preg_replace('/:\d+$/', '', $host) ?: '';
    }

    private function isCentralAuthenticationPath(string $path): bool
    {
        return in_array($path, [
            '/admin', '/admin/login', '/admin/forgot-password',
            '/admin/mfa', '/admin/mfa/recovery',
        ], true);
    }
}
