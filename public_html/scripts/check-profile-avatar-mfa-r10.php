<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/app.php';

use IPKF\Database\Connections\ConnectionRegistry;
use IPKF\Database\Connections\ConnectionResolver;
use IPKF\Support\Env;

$registry = new ConnectionRegistry();
$resolver = new ConnectionResolver($registry);
$pdo = $resolver->resolve('core.primary');

$tableExists = static function (
    PDO $pdo,
    string $table
): bool {
    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    $statement->execute([$table]);

    return (int) $statement->fetchColumn() > 0;
};

$columnExists = static function (
    PDO $pdo,
    string $table,
    string $column
): bool {
    $statement = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    $statement->execute([$table, $column]);

    return (int) $statement->fetchColumn() > 0;
};

$requiredTables = [
    'user_mfa_methods',
    'mfa_challenges',
    'recovery_codes',
];

$failures = [];

foreach ($requiredTables as $table) {
    if (!$tableExists($pdo, $table)) {
        $failures[] = 'missing_table:' . $table;
    }
}

if (!$columnExists($pdo, 'persons', 'avatar')) {
    $failures[] = 'missing_column:persons.avatar';
}

$avatarDirectory =
    BASE_PATH . '/public/uploads/admin/avatars';

if (
    !is_dir($avatarDirectory)
    && !mkdir($avatarDirectory, 0755, true)
    && !is_dir($avatarDirectory)
) {
    $failures[] = 'avatar_directory_create_failed';
} else {
    @chmod(BASE_PATH . '/public/uploads', 0755);
    @chmod(BASE_PATH . '/public/uploads/admin', 0755);
    @chmod($avatarDirectory, 0755);

    if (!is_writable($avatarDirectory)) {
        $failures[] = 'avatar_directory_not_writable';
    }
}

$mfaEnabled = filter_var(
    Env::get('MFA_ENABLED', true),
    FILTER_VALIDATE_BOOLEAN
);

$mfaEnforcement = (string) Env::get(
    'MFA_ENFORCEMENT',
    'optional'
);

echo "PROFILE AVATAR AND MFA R10 CHECK\n";
echo 'mfa_enabled='
    . ($mfaEnabled ? 'true' : 'false')
    . PHP_EOL;
echo 'mfa_enforcement='
    . $mfaEnforcement
    . PHP_EOL;
echo 'avatar_directory='
    . $avatarDirectory
    . PHP_EOL;
echo 'avatar_directory_writable='
    . (
        is_writable($avatarDirectory)
            ? 'yes'
            : 'no'
    )
    . PHP_EOL;

if ($failures !== []) {
    echo 'status=failed' . PHP_EOL;

    foreach ($failures as $failure) {
        echo 'failure=' . $failure . PHP_EOL;
    }

    exit(1);
}

echo 'status=ready' . PHP_EOL;
