<?php

$root = dirname(__DIR__);
$writer = file_get_contents($root . '/public_html/system/Support/EnvironmentSecretWriter.php');
$service = file_get_contents($root . '/public_html/app/Services/ApplicationModuleRegistryService.php');
$view = file_get_contents($root . '/public_html/resources/views/admin/settings.php');
$env = file_get_contents($root . '/public_html/system/Support/Env.php');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(is_string($writer), 'Environment secret writer is required.');
$expect(str_contains($writer, "Env::get('IPKF_SHARED_ENV'"), 'Shared ENV path must come from IPKF_SHARED_ENV.');
$expect(str_contains($writer, "flock(\$lock, LOCK_EX)"), 'Secret writes must use an exclusive lock.');
$expect(str_contains($writer, 'tempnam(') && str_contains($writer, 'rename('), 'Secret writes must be published atomically.');
$expect(str_contains($writer, 'JSON_THROW_ON_ERROR'), 'Secret values must be safely encoded.');

$expect(is_string($service), 'Module registry service is required.');
$expect(str_contains($service, "['database_password']"), 'The service must accept a write-only password.');
$expect(str_contains($service, "\$catalog[\$key]['secret']"), 'Secret references must come from the trusted catalog.');
$expect(!str_contains($service, "\$input['secret_reference']"), 'Secret references must not be accepted from request input.');

$expect(is_string($view), 'Module settings view is required.');
$expect(str_contains($view, 'database_password'), 'The module settings form must expose the write-only password field.');
$expect(!str_contains($view, 'secret_reference'), 'The module settings form must not expose secret references.');

$expect(is_string($env), 'Environment loader is required.');
$expect(str_contains($env, 'loadLayered('), 'Layered environment loading is required.');
$expect(str_contains($env, 'decodeValue('), 'Environment values must be decoded consistently.');

$expect(substr_count($writer, '{') === substr_count($writer, '}'), 'Environment secret writer braces must be balanced.');
$expect(str_contains($writer, 'private function encode('), 'Environment secret writer encode method is required.');
$expect(str_contains($writer, 'private function isAbsolutePath('), 'Environment secret writer absolute-path validation is required.');
$expect(str_ends_with(rtrim($writer), '}'), 'Environment secret writer class must be complete.');

echo "EnvironmentSecretWriterTest passed.\n";
