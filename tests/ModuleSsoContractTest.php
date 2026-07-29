<?php

$root = dirname(__DIR__);
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
$expect(str_contains($service, "moduleForPath") && str_contains($service, "'work'") && str_contains($service, "'automation'"), 'Module SSO must derive and bind the module audience.');
$expect(str_contains($service, "'automation.correspondence.view'") && str_contains($service, "'work.project.view'"), 'Core must authorize module access before issuing a code.');
$expect(str_contains($service, "parse_url(\$path, PHP_URL_HOST)") && str_contains($service, "str_starts_with(\$path, '//')"), 'Return paths must reject external and scheme-relative URLs.');
$expect(str_contains($tokens, 'password_hash($plain, PASSWORD_DEFAULT)'), 'Only a password hash of the authorization code may be stored.');
$expect(str_contains($tokens, '$ttlSeconds = max(30, min(300, $ttlSeconds))'), 'Authorization-code lifetime must be bounded.');
$expect(str_contains($repository, 'AND used_at IS NULL') && str_contains($repository, 'rowCount() === 1'), 'Authorization codes must be claimed atomically exactly once.');
$expect(str_contains($routes, "get('/auth/module-sso/start'") && str_contains($routes, "get('/auth/module-sso/resume'") && str_contains($routes, "get('/auth/module-sso/callback'"), 'The complete central module handoff route set is required.');
$expect(str_contains($routes, '$pending = (new \\App\\Services\\ModuleSsoService())->pendingResumeUrl()') && str_contains($routes, 'return $pending;'), 'Central login must preserve an explicit module destination through password and MFA completion.');
$expect(!str_contains($routes, "if ((\$issued['ok'] ?? false) !== true) {\n        (new \\App\\Services\\ModuleSsoService())->forgetPendingIntent();") && substr_count($routes, 'pendingResumeUrl()') >= 3, 'A denied default role must preserve the module destination and resume it after a successful role switch.');
$expect(str_contains($routes, "return \$response->redirect(\$urls->core('/admin/dashboard'));"), 'An invalid or unauthorized pending module resume must safely fall back to the central dashboard.');
$expect(str_contains($routes, "header('Cache-Control', 'no-store')") && str_contains($routes, "header('Referrer-Policy', 'no-referrer')"), 'Authorization-code responses must prevent caching and referrer leakage.');
$expect(str_contains($routes, 'User is no longer eligible to sign in') && str_contains($routes, '$auth->logout()'), 'Modules must reject users that became ineligible before code consumption.');
$expect(str_contains($routes, "isAutomationHost((string) (\$_SERVER['HTTP_HOST'] ?? ''))") && str_contains($routes, "isWorkHost((string) (\$_SERVER['HTTP_HOST'] ?? ''))"), 'Unauthenticated module routes must go directly to central SSO.');
$expect(str_contains($routes, "return_module=automation") && str_contains($routes, "return_module=work") && str_contains($routes, "input('return_module', '') === 'work'"), 'Federated logout must preserve the originating module for the next central login.');
$urls = file_get_contents($root . '/public_html/system/Support/ApplicationUrlRegistry.php');
$expect(str_contains($urls, "\$path === '/' && \$this->isAutomationHost(\$requestHost)"), 'The Automation host root must open the Automation dashboard instead of the central landing page.');
$expect(str_contains($docs, 'AUTH_SESSION_NAME=ipkf_dev_core') && str_contains($docs, 'AUTH_SESSION_NAME=ipkf_dev_automation'), 'Core and Automation must use independent host sessions.');

echo "Module SSO contract checks passed.\n";
