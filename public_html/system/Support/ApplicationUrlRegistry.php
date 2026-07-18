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
        return $this->url('AUTOMATION_APP_URL', $path);
    }

    public function automationLaunch(string $path = '/admin/automation', ?string $requestHost = null): string
    {
        $host = $requestHost ?? (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($this->isAutomationHost($host)) {
            return $this->automation($path);
        }

        return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($path));
    }

    public function coreHost(): ?string
    {
        return $this->configuredHost('CORE_APP_URL');
    }

    public function automationHost(): ?string
    {
        return $this->configuredHost('AUTOMATION_APP_URL');
    }

    public function guardEnabled(): bool
    {
        return filter_var(Env::get('APP_HOST_GUARD_ENABLED', false), FILTER_VALIDATE_BOOL);
    }

    public function allowed(string $requestHost): bool
    {
        $host = $this->normalizeHost($requestHost);
        $allowed = array_filter(array_unique(array_merge(
            [$this->coreHost(), $this->automationHost()],
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

    public function redirectTarget(string $requestHost, string $requestUri): ?string
    {
        if (!$this->guardEnabled()) {
            return null;
        }

        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $automationPath = $path === '/admin/automation' || str_starts_with($path, '/admin/automation/');

        if ($automationPath && $this->isCoreHost($requestHost)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode($requestUri));
        }

        if ($automationPath && !$this->isAutomationHost($requestHost) && $this->automationHost() !== null) {
            return $this->automation($requestUri);
        }

        if ($this->isAutomationHost($requestHost) && $this->isCentralAuthenticationPath($path)) {
            return $this->core('/auth/module-sso/start?return_path=' . rawurlencode('/admin/automation'));
        }

        if ($this->isAutomationHost($requestHost)
            && str_starts_with($path, '/admin')
            && !$automationPath
            && $path !== '/admin/logout'
            && $this->coreHost() !== null
        ) {
            return $this->core($requestUri);
        }

        return null;
    }

    public function adminHome(string $requestHost): string
    {
        return $this->isAutomationHost($requestHost)
            ? $this->automation('/admin/automation')
            : $this->core('/admin/dashboard');
    }

    private function url(string $key, string $path): string
    {
        $base = rtrim(trim((string) Env::get($key, '')), '/');
        $normalizedPath = $path === '' ? '' : '/' . ltrim($path, '/');

        return $base !== '' ? $base . $normalizedPath : ($normalizedPath !== '' ? $normalizedPath : '/');
    }

    private function configuredHost(string $key): ?string
    {
        $url = trim((string) Env::get($key, ''));
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
