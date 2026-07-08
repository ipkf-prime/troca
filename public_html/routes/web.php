<?php

/** @var \IPKF\Routing\Router $router */

$router->get('/', function ($request, $response) {
    return $response->send('IPKF Framework Genesis OK');
});

$router->get('/health', function ($request, $response) {
    return $response->json([
        'status' => 'ok',
        'framework' => 'IPKF',
        'version' => '0.2.0-foundation',
    ]);
});

$router->get('/_diagnostics', function ($request, $response) use ($router) {
    $debug = \IPKF\Support\Env::isDebug();

    if (!$debug) {
        return $response->status(404)->send('404 - Route not found: /_diagnostics');
    }

    $databaseConnectionAvailable = false;
    $databaseConnectionMessage = 'not configured';

    if (\IPKF\Database\Database::configured()) {
        try {
            \IPKF\Database\Database::connect();
            $databaseConnectionAvailable = true;
            $databaseConnectionMessage = 'available';
        } catch (\Throwable $exception) {
            $databaseConnectionMessage = 'unavailable';
        }
    }

    $runtimeCheckTableExists = $databaseConnectionAvailable
        ? \IPKF\Database\Database::tableExists('ipkf_runtime_checks')
        : false;

    $runtimeCheckFound = false;
    $runtimeCheckValue = null;

    if ($runtimeCheckTableExists) {
        try {
            $statement = \IPKF\Database\Database::connect()->prepare("
                SELECT check_value
                FROM ipkf_runtime_checks
                WHERE check_key = ?
                LIMIT 1
            ");
            $statement->execute(['foundation_v0_2']);
            $value = $statement->fetchColumn();

            if ($value !== false) {
                $runtimeCheckFound = true;
                $runtimeCheckValue = $value;
            }
        } catch (\Throwable $exception) {
            $runtimeCheckFound = false;
            $runtimeCheckValue = null;
        }
    }

    return $response->json([
        'php_version' => PHP_VERSION,
        'base_path' => BASE_PATH,
        'app_env' => \IPKF\Support\Env::get('APP_ENV', 'production'),
        'app_debug' => $debug,
        'env_loaded' => \IPKF\Support\Env::loaded(),
        'config_loaded' => \IPKF\Support\Config::loaded(),
        'database_config_loaded' => \IPKF\Support\Config::has('database.connections.mysql'),
        'database_connection_available' => $databaseConnectionAvailable,
        'database_connection_message' => $databaseConnectionMessage,
        'migration_system_available' => class_exists(\IPKF\Database\Migrations\MigrationRunner::class)
            && class_exists(\IPKF\Database\Migrations\CreateRuntimeChecksTable::class),
        'seeder_system_available' => class_exists(\IPKF\Database\Seeds\SeederRunner::class)
            && class_exists(\IPKF\Database\Seeds\RuntimeCheckSeeder::class),
        'runtime_check_table_exists' => $runtimeCheckTableExists,
        'runtime_check_found' => $runtimeCheckFound,
        'runtime_check_value' => $runtimeCheckValue,
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
