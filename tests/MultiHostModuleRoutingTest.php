<?php

$root = dirname(__DIR__);
$registry = file_get_contents($root . '/public_html/system/Support/ApplicationUrlRegistry.php');
$middleware = file_get_contents($root . '/public_html/system/Http/Middleware/ModuleHostMiddleware.php');
$kernel = file_get_contents($root . '/public_html/system/Http/Kernel.php');
$session = file_get_contents($root . '/public_html/system/Support/Session.php');
$panel = file_get_contents($root . '/public_html/app/Services/AdminPanelService.php');
$routes = file_get_contents($root . '/public_html/routes/web.php');
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
$expect(str_contains($session, 'AUTH_COOKIE_DOMAIN') && str_contains($session, 'AUTH_COOKIE_SECURE'), 'Shared subdomain sessions must have explicit cookie controls.');
$expect(str_contains($panel, 'ApplicationUrlRegistry') && str_contains($panel, 'automation($path)'), 'Admin module links must use the URL registry.');
$expect(str_contains($routes, 'adminHomeUrl') && str_contains($routes, 'adminHome('), 'Authentication redirects must return users to the correct host.');
$expect(str_contains($env, 'AUTOMATION_APP_URL=') && str_contains($env, 'AUTH_COOKIE_DOMAIN='), 'Environment samples must document multi-host settings.');
$expect(str_contains($deploy, '/home/troca/oa-dev.troca.ir/'), 'cPanel deployment must publish the Automation development host.');

echo "Multi-host module routing checks passed.\n";
