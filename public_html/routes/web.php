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
        'autoloader_loaded' => class_exists(\Composer\Autoload\ClassLoader::class),
        'timestamp' => date(DATE_ATOM),
    ]);
});

$router->get('/test', function ($req, $res) {
    return $res->send("Test Route OK");
});
