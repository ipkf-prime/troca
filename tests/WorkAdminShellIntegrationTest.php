<?php

$root = dirname(__DIR__);
$read = static fn (string $path): string => file_get_contents($root . '/' . $path);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};
$fa = static fn (string $entities): string => html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');

require_once $root . '/public_html/system/Core/Container.php';
require_once $root . '/public_html/system/Http/Request.php';
require_once $root . '/public_html/system/Http/Response.php';
require_once $root . '/public_html/system/Http/Pipeline.php';
require_once $root . '/public_html/system/Routing/ControllerResolver.php';
require_once $root . '/public_html/system/Routing/Router.php';
require_once $root . '/public_html/app/Services/AdminModuleUiContract.php';

class InspectableWorkShellResponse extends \IPKF\Http\Response
{
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function content(): string
    {
        return $this->content;
    }
}

$dispatch = static function (string $method, string $uri, callable $register): InspectableWorkShellResponse {
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_HOST'] = 'work-dev.local';

    $router = new \IPKF\Routing\Router(new \IPKF\Core\Container());
    $register($router);
    $response = new InspectableWorkShellResponse();

    $result = $router->dispatch(\IPKF\Http\Request::capture(), $response);

    return $result instanceof InspectableWorkShellResponse ? $result : $response;
};

$persianTitle = $fa('&#x062F;&#x0627;&#x0634;&#x0628;&#x0648;&#x0631;&#x062F; &#x0645;&#x062F;&#x06CC;&#x0631;&#x06CC;&#x062A; &#x06A9;&#x0627;&#x0631;');
$registerWorkRoute = static function (\IPKF\Routing\Router $router) use ($persianTitle): void {
    $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">'
        . '<link rel="stylesheet" href="/assets/admin/css/icons.css">'
        . '<link rel="stylesheet" href="/assets/admin/css/admin.css">'
        . '<script src="/assets/admin/js/admin.js" defer></script>'
        . '</head><body data-admin-shell-kind="work" data-admin-module-ui-contract="shared-admin-shell">'
        . $persianTitle
        . '</body></html>';

    $router->get('/admin/work', static fn (\IPKF\Http\Request $request, \IPKF\Http\Response $response): \IPKF\Http\Response => $response
        ->status(200)
        ->header('Content-Type', 'text/html; charset=UTF-8')
        ->send($html));
};

$get = $dispatch('GET', '/admin/work', $registerWorkRoute);
$expect($get->statusCode() === 200, 'Authenticated GET /admin/work dispatch must return the successful route status.');
$expect(($get->headers()['Content-Type'] ?? '') === 'text/html; charset=UTF-8', 'GET /admin/work must preserve the HTML UTF-8 content type.');
$expect(str_contains($get->content(), $persianTitle), 'GET /admin/work rendered content must contain correct Persian text.');
$expect(str_contains($get->content(), '<meta charset="UTF-8">'), 'GET /admin/work rendered HTML must include UTF-8 meta charset.');
$expect(str_contains($get->content(), '<html lang="fa" dir="rtl">'), 'GET /admin/work rendered HTML must include Persian RTL attributes.');
$expect(str_contains($get->content(), '/assets/admin/css/admin.css'), 'GET /admin/work rendered HTML must include shared admin.css.');
$expect(str_contains($get->content(), '/assets/admin/css/icons.css'), 'GET /admin/work rendered HTML must include shared icons.css.');
$expect(str_contains($get->content(), '/assets/admin/js/admin.js'), 'GET /admin/work rendered HTML must include shared admin.js.');
$expect(!str_contains($get->content(), 'work-dev.troca.ir') && !str_contains($get->content(), 'dev.troca.ir'), 'Shared asset URLs must not hardcode deployment domains.');

$head = $dispatch('HEAD', '/admin/work', $registerWorkRoute);
$expect($head->statusCode() === $get->statusCode(), 'HEAD /admin/work must preserve the GET status.');
$expect($head->headers() === $get->headers(), 'HEAD /admin/work must preserve the GET headers.');
ob_start();
$head->emit();
$headBody = ob_get_clean();
$expect($headBody === '', 'HEAD /admin/work emit must not output a response body.');

$unknown = $dispatch('GET', '/unknown-work-shell-test', $registerWorkRoute);
$expect($unknown->statusCode() === 404, 'Unknown routes must return 404.');
$expect(str_contains($unknown->content(), '404 - Route not found: /unknown-work-shell-test'), 'Unknown routes must keep the clean not-found body.');

$routes = $read('public_html/routes/web.php');
$router = $read('public_html/system/Routing/Router.php');
$response = $read('public_html/system/Http/Response.php');
$layout = $read('public_html/resources/views/admin/layout.php');
$workView = $read('public_html/resources/views/admin/work-dashboard.php');
$panel = $read('public_html/app/Services/AdminPanelService.php');
$contract = $read('public_html/app/Services/AdminModuleUiContract.php');
$repository = $read('public_html/app/Repositories/WorkDashboardRepository.php');
$adminCss = $read('public_html/public/assets/admin/css/admin.css');
$rbac = $read('public_html/app/Services/AdminNavigationRbacService.php');

$workRouteNeedle = '$' . "router->get('/admin/work'";
$expect(str_contains($routes, $workRouteNeedle), 'GET /admin/work route must be registered.');
$expect(str_contains($routes, "'work-dashboard'"), '/admin/work must render the Work admin view.');
$expect(str_contains($routes, "adminGuard(\$response, '/admin/work')"), '/admin/work must keep the existing admin guard.');
$expect(str_contains($rbac, "'/admin/work' => 'work.project.view'"), '/admin/work must keep RBAC route protection.');
$expect(str_contains($routes, 'isWorkHost') && str_contains($routes, 'module-sso/start'), 'Guest Work host access must keep the existing module SSO redirect behavior.');
$expect(str_contains($router, '$lookupMethod = $method === \'HEAD\' ? \'GET\' : $method;'), 'Router must dispatch HEAD requests through GET route lookup.');
$expect(str_contains($response, "REQUEST_METHOD") && str_contains($response, "HEAD") && str_contains($response, "return;") && str_contains($response, 'echo $this->content;'), 'Response must suppress the body for HEAD requests.');

$expect(str_contains($workView, 'ob_start();'), 'Work view must capture content instead of rendering a standalone document.');
$expect(str_contains($workView, "require __DIR__ . '/layout.php';"), 'Work view must render through the shared admin layout.');
$expect(!preg_match('/<!doctype html|<html\b|<head\b|<body\b/i', $workView), 'Work view must not contain a standalone HTML document.');
$expect(str_contains($workView, 'data-admin-module-page="work"'), 'Work view must identify itself as a Work module page.');
$expect(str_contains($repository, "'statuses' =>") && str_contains($repository, 'FROM work_statuses'), 'Work dashboard status summary must be available.');

$expect(str_contains($layout, '<meta charset="UTF-8">'), 'Shared admin layout must declare UTF-8.');
$expect(str_contains($layout, '<html lang="fa" dir="rtl">'), 'Shared admin layout must declare Persian RTL document direction.');
$expect(str_contains($layout, 'AdminModuleUiContract::safeAssets'), 'Shared admin layout must defensively enforce module asset validation.');
$expect(str_contains($layout, "data-admin-shell-kind"), 'Shared admin layout must expose the active shell kind.');
$expect(str_contains($layout, 'data-admin-module-ui-contract="shared-admin-shell"'), 'Shared admin layout must expose the module UI contract marker.');
$expect(str_contains($layout, "admin-module-shell") && str_contains($layout, "core-shell"), 'Shared admin layout must support both module and core shells.');
$expect(str_contains($layout, "data-module-asset=\"css\"") && str_contains($layout, "data-module-asset=\"js\""), 'Shared admin layout must load safe module CSS and JS assets after the base assets.');
$expect(str_contains($layout, "icons_css") && str_contains($layout, "admin_css") && str_contains($layout, "admin_js"), 'Shared admin layout must load Admin theme asset URLs.');

$expect(str_contains($adminCss, 'Vazirmatn-Arabic.woff2') && str_contains($adminCss, 'Vazirmatn-Latin.woff2'), 'Admin CSS must load Vazirmatn webfonts.');
$expect(is_file($root . '/public_html/public/assets/admin/css/admin.css'), 'admin.css must exist.');
$expect(is_file($root . '/public_html/public/assets/admin/css/icons.css'), 'icons.css must exist.');
$expect(is_file($root . '/public_html/public/assets/admin/js/admin.js'), 'admin.js must exist.');
$expect(is_file($root . '/public_html/public/assets/admin/webfonts/Vazirmatn-Arabic.woff2'), 'Vazirmatn Arabic font must exist.');
$expect(is_file($root . '/public_html/public/assets/admin/webfonts/Vazirmatn-Latin.woff2'), 'Vazirmatn Latin font must exist.');

$expect(\App\Services\AdminModuleUiContract::safeAdminPath('/admin') === '/admin', 'Module contract must accept /admin.');
$expect(\App\Services\AdminModuleUiContract::safeAdminPath('/admin/work') === '/admin/work', 'Module contract must accept /admin/... paths.');
foreach (['/administrator', '/adminXYZ', '//evil.test/admin', 'https://evil.test/admin', '/admin/../theme', "/admin/work path", "\\admin\\work"] as $badPath) {
    $expect(\App\Services\AdminModuleUiContract::safeAdminPath($badPath) === '/admin/dashboard', 'Module contract must reject unsafe admin path: ' . $badPath);
}

$assets = \App\Services\AdminModuleUiContract::safeAssets([
    'css' => ['/assets/admin/css/automation.css', '/assets/admin/css/admin.css', 'https://evil.test/x.css', '/assets/admin/../x.css'],
    'js' => ['/assets/admin/js/work.js', '/assets/admin/js/admin.js', '//evil.test/x.js'],
]);
$expect($assets['css'] === ['/assets/admin/css/automation.css'], 'Module CSS assets must be safe, additive, and not duplicate shared CSS.');
$expect($assets['js'] === ['/assets/admin/js/work.js'], 'Module JS assets must be safe, additive, and not duplicate shared JS.');

$expect(str_contains($contract, 'class AdminModuleUiContract'), 'Admin module UI contract must exist.');
$expect(str_contains($contract, 'SHARED_LAYOUT') && str_contains($contract, '/resources/views/admin/layout.php'), 'Module contract must point modules to the shared admin layout.');
$expect(str_contains($panel, 'AdminModuleUiContract::shell(') && str_contains($panel, "'work'") && str_contains($panel, "'/admin/work'"), 'Work module must be registered through the reusable module shell contract.');
$expect(str_contains($panel, 'isWorkHost') && str_contains($panel, 'workNavigation'), 'Admin panel context must select Work navigation on the Work host.');

foreach ([$routes, $layout, $workView, $panel, $contract] as $runtimeFile) {
    $expect(!str_contains($runtimeFile, 'work-dev.troca.ir') && !str_contains($runtimeFile, 'dev.troca.ir'), 'Runtime Work shell files must not hardcode deployment domains.');
}

$mojibakeMarkers = [
    'misdecoded_utf8_A_tilde' => "\xC3\x83",
    'misdecoded_utf8_A_circumflex' => "\xC3\x82",
    'replacement_character' => "\xEF\xBF\xBD",
    'misdecoded_euro_sequence' => "\xC3\xA2\xE2\x82\xAC",
    'common_persian_mojibake_tah' => "\xD8\xB7\xC2\xB7",
    'common_persian_mojibake_zah' => "\xD8\xB8\xC2\xB8",
];
foreach ([$layout, $workView, $contract] as $utf8File) {
    foreach ($mojibakeMarkers as $name => $marker) {
        $expect(!str_contains($utf8File, $marker), 'Shared Work shell files must not contain mojibake marker: ' . $name);
    }
}
$workRouteStart = strpos($routes, $workRouteNeedle);
$automationRouteNeedle = '$' . "router->get('/admin/automation/correspondences'";
$workRouteEnd = strpos($routes, $automationRouteNeedle, $workRouteStart);
$workRouteSource = $workRouteStart !== false && $workRouteEnd !== false ? substr($routes, $workRouteStart, $workRouteEnd - $workRouteStart) : '';
foreach ($mojibakeMarkers as $name => $marker) {
    $expect(!str_contains($workRouteSource, $marker), '/admin/work route must not contain mojibake marker: ' . $name);
}

$expect(str_contains($router, 'Route not found') && str_contains($router, 'status(404)'), 'Unknown routes must still return the clean 404 response.');

echo "Work admin shell integration checks passed.\n";