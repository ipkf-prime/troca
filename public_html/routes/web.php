<?php

/** @var \IPKF\Routing\Router $router */

$router->get('/', function ($request, $response) {
    return $response->send('IPKF Framework Genesis OK');
});

$router->get('/health', function ($request, $response) {
    return $response->json([
        'status' => 'ok',
        'framework' => 'IPKF',
        'version' => '0.1.0-genesis',
    ]);
});

$router->get('/_diagnostics', function ($request, $response) use ($router) {
    $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (!$debug) {
        return $response->status(404)->send('404 - Route not found: /_diagnostics');
    }

    return $response->json([
        'php_version' => PHP_VERSION,
        'base_path' => BASE_PATH,
        'app_env' => $_ENV['APP_ENV'] ?? 'production',
        'app_debug' => $debug,
        'storage_writable' => is_writable(BASE_PATH . '/storage'),
        'routes_loaded_count' => $router->count(),
        'autoload' => [
            'vendor_autoload_exists' => is_readable(BASE_PATH . '/vendor/autoload.php'),
            'custom_loader_exists' => is_readable(BASE_PATH . '/vendor/ipkf_loader.php'),
            'application_class_loaded' => class_exists(\IPKF\Core\Application::class),
            'router_class_loaded' => class_exists(\IPKF\Routing\Router::class),
            'request_class_loaded' => class_exists(\IPKF\Http\Request::class),
        ],
        'timestamp' => date(DATE_ATOM),
    ]);
});

$router->get('/test', function ($req, $res) {
    return $res->send("Test Route OK");
});
