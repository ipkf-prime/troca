<?php

$root = dirname(__DIR__);
$registry = file_get_contents($root . '/public_html/system/Support/ApplicationUrlRegistry.php');
$middleware = file_get_contents($root . '/public_html/system/Http/Middleware/ModuleHostMiddleware.php');
$kernel = file_get_contents($root . '/public_html/system/Http/Kernel.php');
$panel = file_get_contents($root . '/public_html/app/Services/AdminPanelService.php');
$routes = file_get_contents($root . '/public_html/routes/web.php');
$sso = file_get_contents($root . '/public_html/app/Services/ModuleSsoService.php');
$env = file_get_contents($root . '/public_html/.env.example');
$deploy = file_get_contents($root . '/.cpanel.yml');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(str_contains($registry, "'CORE_APP_URL'") && str_contains($registry, "'AUTOMATION_APP_URL'"), 'Module URLs must be environment driven.');
$expect(!str_contains($registry, 'oa-dev.troca.ir') && !str_contains($panel, 'oa-dev.troca.ir'), 'Runtime code must not hardcode deployment domains.');
$expect(str_contains($registry, 'ALLOWED_APP_HOSTS') && str_contains($registry, 'APP_HOST_GUARD_ENABLED'), 'Allowed-host validation must be explicit and configurable.');
$expect(str_contains($middleware, '421') && str_contains($middleware, 'redirectTarget'), 'Host middleware must reject unknown hosts and redirect misplaced modules.');
$expect(str_contains($kernel, 'ModuleHostMiddleware::class'), 'The host guard must run in the global HTTP kernel.');
$expect(str_contains($panel, 'ApplicationUrlRegistry') && str_contains($panel, 'automationLaunch($path)'), 'Admin module links must use the central SSO launch URL.');
$expect(str_contains($routes, '/auth/module-sso/start') && str_contains($routes, '/auth/module-sso/callback'), 'Central SSO start and Automation callback routes are required.');
$expect(str_contains($sso, "private const AUDIENCE = 'automation'") && str_contains($sso, '60,'), 'Authorization codes must be audience-bound and short-lived.');
$expect(str_contains($sso, "'/admin/automation'") && str_contains($sso, 'safe_redirect_path'), 'SSO return paths must be constrained to Automation.');
$expect(str_contains($env, 'AUTOMATION_APP_URL=') && str_contains($env, 'AUTH_COOKIE_DOMAIN='), 'Environment samples must document multi-host settings.');
$expect(str_contains($deploy, '/home/troca/oa-dev.troca.ir/'), 'cPanel deployment must publish the Automation development host.');

echo "Multi-host module routing checks passed.\n";
