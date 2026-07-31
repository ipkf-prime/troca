<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/app.php';

$db = \IPKF\Database\Database::connect();

$column = static function (
    string $table,
    string $name
) use ($db): array {
    $statement = $db->prepare("
        SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
        LIMIT 1
    ");
    $statement->execute([$table, $name]);

    return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
};

$engine = static function (
    string $table
) use ($db): ?string {
    $statement = $db->prepare("
        SELECT ENGINE
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
        LIMIT 1
    ");
    $statement->execute([$table]);
    $value = $statement->fetchColumn();

    return $value === false ? null : (string) $value;
};

$result = [
    'users' => [
        'engine' => $engine('users'),
        'id' => $column('users', 'id'),
    ],
    'user_role_assignments' => [
        'engine' => $engine('user_role_assignments'),
        'id' => $column('user_role_assignments', 'id'),
    ],
    'auth_login_history' => [
        'engine' => $engine('auth_login_history'),
        'user_id' => $column('auth_login_history', 'user_id'),
        'role_assignment_id' => $column(
            'auth_login_history',
            'role_assignment_id'
        ),
    ],
];

echo json_encode(
    $result,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_PRETTY_PRINT
) . PHP_EOL;
