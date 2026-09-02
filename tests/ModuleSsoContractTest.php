<?php

$root = dirname(__DIR__);
require_once $root . '/public_html/system/Support/Env.php';
require_once $root . '/public_html/system/Support/ModuleRuntimeConfig.php';
require_once $root . '/public_html/system/Support/ApplicationUrlRegistry.php';
$service = file_get_contents($root . '/public_html/app/Services/ModuleSsoService.php');
$tokens = file_get_contents($root . '/public_html/app/Services/LoginTokenService.php');
$repository = file_get_contents($root . '/public_html/app/Repositories/LoginTokenRepository.php');
$routes = file_get_contents($root . '/public_html/routes/web.php');
$docs = file_get_contents($root . '/docs/MULTI_HOST_MODULE_ROUTING.md');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(str_contains($service, "private const PURPOSE = 'module_sso'"), 'Module SSO must use a dedicated token purpose.');
$expect(str_contains($service, 'moduleForPath') && str_contains($service, 'moduleForHost') && str_contains($service, 'allActive()') && str_contains($service, "'audience' =>"), 'Module SSO audience must resolve through the dynamic module registry.');
$expect(str_contains($service, "['permission_key']"), 'Module authorization must use the registered permission key.');
$expect(str_contains($service, 'private function returnPath') && str_contains($service, 'parse_url($path)') && str_contains($service, "isset(\$parsed['scheme'])") && str_contains($service, "isset(\$parsed['host'])") && str_contains($service, "return '/admin/dashboard'"), 'Return paths must reject external and scheme-relative URLs.');
$expect(str_contains($tokens, 'password_hash($plain, PASSWORD_DEFAULT)'), 'Only a password hash of the authorization code may be stored.');
$expect(str_contains($tokens, '$ttlSeconds = max(30, min(300, $ttlSeconds))'), 'Authorization-code lifetime must be bounded.');
$expect(str_contains($repository, 'AND used_at IS NULL') && str_contains($repository, 'rowCount() === 1'), 'Authorization codes must be claimed atomically exactly once.');
$expect(str_contains($routes, "get('/auth/module-sso/start'") && str_contains($routes, "get('/auth/module-sso/resume'") && str_contains($routes, "get('/auth/module-sso/callback'"), 'The complete central module handoff route set is required.');
$expect(str_contains($routes, '$pending = (new \\App\\Services\\ModuleSsoService())->pendingResumeUrl()') && str_contains($routes, 'return $pending;'), 'Central login must preserve an explicit module destination through password and MFA completion.');
$expect(!str_contains($routes, "if ((\$issued['ok'] ?? false) !== true) {\n        (new \\App\\Services\\ModuleSsoService())->forgetPendingIntent();") && substr_count($routes, 'pendingResumeUrl()') >= 3, 'A denied default role must preserve the module destination and resume it after a successful role switch.');
$expect(str_contains($routes, "return \$response->redirect(\$urls->core('/admin/dashboard'));"), 'An invalid or unauthorized pending module resume must safely fall back to the central dashboard.');
$expect(str_contains($routes, "header('Cache-Control', 'no-store')") && str_contains($routes, "header('Referrer-Policy', 'no-referrer')"), 'Authorization-code responses must prevent caching and referrer leakage.');
$expect(str_contains($routes, 'User is no longer eligible to sign in') && str_contains($routes, '$auth->logout()'), 'Modules must reject users that became ineligible before code consumption.');
$expect(str_contains($routes, '/auth/module-sso/start') && str_contains($routes, 'return_path') && !str_contains($service, 'isAutomationHost(') && !str_contains($service, 'isWorkHost(') && !str_contains($service, 'isTicketingHost('), 'Unauthenticated modules must use generic central SSO.');
$expect(str_contains($routes, 'applicationModuleKeyForHost(') && str_contains($routes, 'return_module') && str_contains($routes, 'ModuleRuntimeConfig') && str_contains($routes, "'route_path'"), 'Federated logout must preserve the originating dynamic module.');
$previousEnv = [];
foreach (['APP_HOST_GUARD_ENABLED', 'CORE_APP_URL', 'AUTOMATION_APP_URL', 'WORK_APP_URL'] as $key) {
    $previousEnv[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
}

$_ENV['APP_HOST_GUARD_ENABLED'] = $_SERVER['APP_HOST_GUARD_ENABLED'] = 'true';
$_ENV['CORE_APP_URL'] = $_SERVER['CORE_APP_URL'] = 'https://core.example.test';
$_ENV['AUTOMATION_APP_URL'] = $_SERVER['AUTOMATION_APP_URL'] = 'https://automation.example.test';
$_ENV['WORK_APP_URL'] = $_SERVER['WORK_APP_URL'] = 'https://work.example.test';

$urls = new \IPKF\Support\ApplicationUrlRegistry();
$expect($urls->redirectTarget('automation.example.test', '/') === 'https://automation.example.test/admin/automation', 'Automation host root must redirect to the Automation dashboard URL.');
$expect($urls->redirectTarget('work.example.test', '/') === 'https://work.example.test/admin/work', 'Work host root must redirect to the Work dashboard URL.');
$coreRootRedirect = $urls->redirectTarget('core.example.test', '/');
$expect($coreRootRedirect === null || !str_contains($coreRootRedirect, '/admin/automation') && !str_contains($coreRootRedirect, '/admin/work'), 'Core host root must not be redirected to a module dashboard.');

foreach ($previousEnv as $key => [$envValue, $serverValue]) {
    if ($envValue === null) {
        unset($_ENV[$key]);
    } else {
        $_ENV[$key] = $envValue;
    }

    if ($serverValue === null) {
        unset($_SERVER[$key]);
    } else {
        $_SERVER[$key] = $serverValue;
    }
}
$expect(str_contains($docs, 'ipkf_dev_core') && str_contains($docs, 'ipkf_dev_automation') && str_contains($docs, 'ipkf_dev_work') && str_contains($docs, 'ipkf_dev_ticketing') && str_contains($docs, 'generic module SSO'), 'Independent host sessions and generic module SSO must be documented.');

echo "Module SSO contract checks passed.\n";
