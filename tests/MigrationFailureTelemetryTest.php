<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$loggerPath = $root . '/public_html/system/Database/Migrations/MigrationFailureLogger.php';
$exceptionPath = $root . '/public_html/system/Database/Migrations/MigrationExecutionException.php';
$runnerPath = $root . '/public_html/system/Database/Migrations/MigrationRunner.php';
$endpointPath = $root . '/public_html/public/migrate.php';

$logger = file_get_contents($loggerPath);
$exception = file_get_contents($exceptionPath);
$runner = file_get_contents($runnerPath);
$endpoint = file_get_contents($endpointPath);

function expectMigrationTelemetry(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

expectMigrationTelemetry(is_string($logger), 'Migration failure logger source must be readable.');
expectMigrationTelemetry(is_string($exception), 'Migration execution exception source must be readable.');
expectMigrationTelemetry(is_string($runner), 'Migration runner source must be readable.');
expectMigrationTelemetry(is_string($endpoint), 'Migration endpoint source must be readable.');

expectMigrationTelemetry(
    str_contains($logger, "\$path = BASE_PATH . '/storage/logs/migration-failures.log';")
        && !str_contains($logger, "BASE_PATH . '/public/"),
    'Migration failures must be written only below the private storage log directory.'
);

$privateFields = [
    'timestamp',
    'failure_reference',
    'failing_migration_class',
    'exception_class',
    'exception_message',
    'exception_code',
    'file',
    'line',
    'previous_exception_chain',
    'stack_trace',
];

foreach ($privateFields as $field) {
    expectMigrationTelemetry(str_contains($logger, "'{$field}'"), "Missing private telemetry field: {$field}");
}

$forbiddenLoggerInputs = [
    '$_GET',
    '$_POST',
    '$_REQUEST',
    '$_SERVER',
    '$_COOKIE',
    'getallheaders',
    'DEV_MAINTENANCE_KEY',
    'DB_PASSWORD',
    'session_id',
];

foreach ($forbiddenLoggerInputs as $input) {
    expectMigrationTelemetry(!str_contains($logger, $input), "Private logger must not read sensitive input: {$input}");
}

expectMigrationTelemetry(
    str_contains($logger, "return 'MIG-' . strtoupper(bin2hex(random_bytes(8)));")
        && str_contains($logger, 'catch (Throwable)')
        && strpos($logger, 'return $failureReference;') > strpos($logger, 'catch (Throwable)'),
    'Logging failure must not replace the original migration failure or suppress its opaque reference.'
);

expectMigrationTelemetry(
    str_contains($exception, 'private readonly string $migrationClass')
        && str_contains($exception, 'Throwable $previous')
        && str_contains($exception, "parent::__construct('Migration execution failed.', 0, $previous);")
        && str_contains($exception, 'public function migrationClass(): string')
        && str_contains($exception, 'public function migrationBasename(): string'),
    'The migration wrapper must retain the failing class and preserve the original exception as previous.'
);

$upPosition = strpos($runner, '$migration->up();');
$insertPosition = strpos($runner, 'INSERT INTO migrations (migration) VALUES (?)');
expectMigrationTelemetry($upPosition !== false && $insertPosition !== false, 'Migration execution and record insert must remain present.');
expectMigrationTelemetry($upPosition < $insertPosition, 'Migration records must be inserted only after up() completes.');
expectMigrationTelemetry(
    str_contains($runner, 'if ($stmt->fetch())')
        && str_contains($runner, 'continue;')
        && substr_count($runner, 'new MigrationExecutionException($name, $exception)') === 2,
    'Successful and previously-recorded migration behavior must remain unchanged while failures retain context.'
);

expectMigrationTelemetry(
    str_contains($endpoint, 'echo "MIGRATION FAILED\\n";')
        && str_contains($endpoint, 'echo "failure_reference={$failureReference}\\n";')
        && str_contains($endpoint, 'echo "failed_migration={$failedMigrationName}";'),
    'Public migration failures must expose only the safe three-line response.'
);

$forbiddenPublicOutput = [
    '->getMessage()',
    '->getTraceAsString()',
    '->getFile()',
    '->getLine()',
    'print_r(',
    'var_dump(',
    'SQLSTATE',
];

foreach ($forbiddenPublicOutput as $output) {
    expectMigrationTelemetry(!str_contains($endpoint, $output), "Public migration response may expose private failure data: {$output}");
}

require_once $root . '/public_html/vendor/autoload.php';

$original = new RuntimeException('synthetic private migration error');
$wrapped = new \IPKF\Database\Migrations\MigrationExecutionException(
    'IPKF\\Database\\Migrations\\SyntheticMigration',
    $original
);

expectMigrationTelemetry($wrapped->getPrevious() === $original, 'Original migration exception must remain available as previous.');
expectMigrationTelemetry(
    $wrapped->migrationClass() === 'IPKF\\Database\\Migrations\\SyntheticMigration'
        && $wrapped->migrationBasename() === 'SyntheticMigration',
    'Failing migration class and safe basename must remain available to migrate.php.'
);

echo "Migration failure telemetry structural tests passed.\n";
