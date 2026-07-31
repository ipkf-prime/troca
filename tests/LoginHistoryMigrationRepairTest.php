<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = $read(
    'public_html/system/Database/Migrations/CreateAuthenticationLoginHistoryTable.php'
);
$diagnostic = $read(
    'public_html/scripts/check-login-history-schema.php'
);

$createStart = strpos(
    $migration,
    'CREATE TABLE IF NOT EXISTS auth_login_history'
);
$createEnd = strpos(
    $migration,
    '");',
    $createStart
);
$createSql = $createStart === false
    || $createEnd === false
        ? ''
        : substr(
            $migration,
            $createStart,
            $createEnd - $createStart
        );

$expect(
    str_contains($migration, 'referenceColumnType'),
    'Reference column types are not resolved dynamically.'
);

$expect(
    $createSql !== ''
    && !str_contains($createSql, 'CONSTRAINT')
    && !str_contains($createSql, 'FOREIGN KEY'),
    'Foreign keys are still created inline.'
);

$expect(
    str_contains($migration, 'addForeignKeyIfPossible')
    && str_contains($migration, 'supportsForeignKeys')
    && str_contains($migration, 'columnType'),
    'Conditional foreign-key creation is incomplete.'
);

$expect(
    str_contains($migration, "'SET NULL'")
    && str_contains($migration, "'CASCADE'"),
    'Required delete actions are missing.'
);

$expect(
    str_contains($diagnostic, 'auth_login_history')
    && str_contains($diagnostic, 'user_role_assignments'),
    'Schema diagnostic is incomplete.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration
    ),
    'Destructive SQL is present.'
);

echo "Login history migration repair checks passed.\n";
