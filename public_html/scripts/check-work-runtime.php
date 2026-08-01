<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$result = [
    'base_path' => BASE_PATH,
    'host_env' => (string) (
        $_SERVER['HTTP_HOST'] ?? ''
    ),
    'module_env' => (string) (
        \IPKF\Support\Env::get(
            'IPKF_MODULE',
            ''
        )
    ),
];

try {
    $registry =
        new \IPKF\Database\Connections\ConnectionRegistry();
    $definition = $registry->get('work.primary');

    $result['configured'] =
        $definition !== null
        && $definition->configured();
    $result['fallback'] =
        $definition !== null
        && $definition->usesFallback();

    if ($definition !== null) {
        $config = $definition->config();
        $result['database_config'] =
            (string) ($config['database'] ?? '');
        $result['username_config'] =
            (string) ($config['username'] ?? '');
        $result['password_present'] =
            trim((string) (
                $config['password'] ?? ''
            )) !== '';
    }

    $pdo = (
        new \IPKF\Database\Connections\ConnectionResolver(
            $registry
        )
    )->resolve('work.primary');

    $result['connected_database'] =
        (string) $pdo->query(
            'SELECT DATABASE()'
        )->fetchColumn();
    $result['work_projects'] =
        (int) $pdo->query(
            'SELECT COUNT(*) FROM work_projects'
        )->fetchColumn();
    $result['work_items'] =
        (int) $pdo->query(
            'SELECT COUNT(*) FROM work_items'
        )->fetchColumn();

    $service = (
        new \App\Services\Work\WorkProjectService()
    )->index();

    $result['service_status'] = 'ok';
    $result['service_projects'] =
        count($service['items'] ?? []);
} catch (\Throwable $exception) {
    $result['service_status'] = 'error';
    $result['error_class'] =
        get_class($exception);
    $result['error_message'] =
        $exception->getMessage();
}

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
) . PHP_EOL;
