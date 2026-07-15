<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migrationPath = $root . '/public_html/system/Database/Migrations/CreateAutomationCorrespondenceFoundationTables.php';
$seederPath = $root . '/public_html/system/Database/Seeds/AutomationCorrespondenceSeeder.php';
$migrateRunnerPath = $root . '/public_html/public/migrate.php';
$seedRunnerPath = $root . '/public_html/public/seed.php';
$routesPath = $root . '/public_html/routes/web.php';

$migration = file_get_contents($migrationPath);
$seeder = file_get_contents($seederPath);
$migrateRunner = file_get_contents($migrateRunnerPath);
$seedRunner = file_get_contents($seedRunnerPath);
$routes = file_get_contents($routesPath);

function expectAutomation(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tableFragment(string $migration, string $table): string
{
    $start = strpos($migration, "CREATE TABLE IF NOT EXISTS {$table} (");
    expectAutomation($start !== false, "Missing table definition: {$table}");
    $end = strpos($migration, 'ENGINE=InnoDB', $start);
    expectAutomation($end !== false, "Incomplete table definition: {$table}");

    return substr($migration, $start, $end - $start);
}

expectAutomation(is_string($migration), 'Automation migration source must be readable.');
expectAutomation(is_string($seeder), 'Automation seeder source must be readable.');
expectAutomation(is_string($migrateRunner), 'Migration runner source must be readable.');
expectAutomation(is_string($seedRunner), 'Seeder runner source must be readable.');
expectAutomation(is_string($routes), 'Diagnostics route source must be readable.');

$tables = [
    'lookup_domains',
    'lookup_values',
    'correspondences',
    'correspondence_versions',
    'correspondence_parties',
    'registry_books',
    'correspondence_registrations',
    'correspondence_relations',
    'correspondence_referrals',
    'correspondence_events',
    'private_files',
    'correspondence_attachments',
];

foreach ($tables as $table) {
    expectAutomation(
        substr_count($migration, "CREATE TABLE IF NOT EXISTS {$table} (") === 1,
        "Table {$table} must be created idempotently exactly once."
    );
}

expectAutomation(
    substr_count($migrateRunner, 'CreateAutomationCorrespondenceFoundationTables()') === 1,
    'Automation migration must be registered exactly once.'
);
expectAutomation(
    substr_count($seedRunner, 'AutomationCorrespondenceSeeder()') === 1,
    'Automation seeder must be registered exactly once.'
);

$versions = tableFragment($migration, 'correspondence_versions');
expectAutomation(
    str_contains($versions, 'UNIQUE KEY corr_versions_number_unique (correspondence_id, version_number)'),
    'Correspondence version numbers must be unique per correspondence.'
);
expectAutomation(!str_contains($versions, 'updated_at'), 'Immutable versions must not have updated_at.');

$registrations = tableFragment($migration, 'correspondence_registrations');
expectAutomation(
    str_contains($registrations, 'UNIQUE KEY corr_reg_book_sequence_unique (registry_book_id, sequential_number)'),
    'Registry sequence numbers must be unique per book.'
);
expectAutomation(
    str_contains($registrations, 'UNIQUE KEY corr_reg_book_formatted_unique (registry_book_id, formatted_number)'),
    'Formatted registration numbers must be unique per book.'
);
expectAutomation(
    str_contains($registrations, 'active_registration_slot')
        && str_contains($registrations, 'corr_reg_active_role_unique'),
    'Only one uncancelled registration per correspondence role is allowed.'
);

$relations = tableFragment($migration, 'correspondence_relations');
expectAutomation(
    str_contains($relations, 'corr_relations_no_self_check')
        && str_contains($relations, 'source_correspondence_id <> target_correspondence_id'),
    'Correspondence relations must reject self-reference.'
);
expectAutomation(
    str_contains($relations, 'UNIQUE KEY corr_relations_exact_unique'),
    'Duplicate identical correspondence relations must be rejected.'
);

$parties = tableFragment($migration, 'correspondence_parties');
expectAutomation(
    str_contains($parties, 'corr_parties_target_check')
        && str_contains($parties, "target_kind_code = 'external'"),
    'Correspondence party targets must match their target kind.'
);

$referrals = tableFragment($migration, 'correspondence_referrals');
expectAutomation(
    str_contains($referrals, 'corr_referrals_one_target_check'),
    'A referral must select exactly one primary target.'
);
expectAutomation(
    str_contains($referrals, 'parent_referral_id BIGINT UNSIGNED NULL')
        && str_contains($migration, "'corr_ref_parent_fk'")
        && str_contains($migration, "'parent_referral_id', 'correspondence_referrals', 'id', 'RESTRICT'"),
    'Forwarding must create a preserved child referral.'
);
expectAutomation(
    str_contains($referrals, 'completed_by_user_id')
        && str_contains($referrals, 'completed_at')
        && !str_contains($referrals, 'deleted_at'),
    'Completed referrals must remain historical records.'
);

$events = tableFragment($migration, 'correspondence_events');
expectAutomation(str_contains($events, 'safe_metadata_json LONGTEXT NULL'), 'Event metadata must remain safe JSON metadata.');
expectAutomation(!str_contains($events, 'updated_at'), 'Append-only events must not have updated_at.');

$files = tableFragment($migration, 'private_files');
expectAutomation(str_contains($files, 'storage_key VARCHAR(1000)'), 'Private file storage keys are required.');
expectAutomation(str_contains($files, 'sha256_checksum CHAR(64)'), 'Private file checksums are required.');
expectAutomation(!preg_match('/\b(public_url|download_url|file_blob|LONGBLOB|MEDIUMBLOB|BLOB)\b/i', $files), 'Private files must not expose URLs or store binaries.');

$domains = [
    'correspondence_direction',
    'correspondence_status',
    'correspondence_priority',
    'correspondence_confidentiality',
    'correspondence_channel',
    'correspondence_party_role',
    'correspondence_party_kind',
    'registry_book_scope',
    'registration_role',
    'registration_status',
    'correspondence_relation_type',
    'referral_requested_action',
    'referral_status',
    'correspondence_event_type',
    'attachment_role',
    'file_scan_status',
];

foreach ($domains as $domain) {
    expectAutomation(str_contains($seeder, "'{$domain}'"), "Missing lookup domain: {$domain}");
}

expectAutomation(preg_match('/[\x{0600}-\x{06FF}]/u', $seeder) === 1, 'Lookup labels must preserve Persian UTF-8 text.');
expectAutomation(str_contains($seeder, 'ON DUPLICATE KEY UPDATE'), 'Lookup seeds must be idempotent.');

$permissions = [
    'automation.correspondence.view',
    'automation.correspondence.create',
    'automation.correspondence.edit_draft',
    'automation.correspondence.register',
    'automation.correspondence.route',
    'automation.correspondence.cartable.view',
    'automation.correspondence.close',
    'automation.registry.manage',
    'automation.audit.view',
];

foreach ($permissions as $permission) {
    expectAutomation(str_contains($seeder, "'{$permission}'"), "Missing permission: {$permission}");
}

expectAutomation(
    str_contains($seeder, "WHERE code = 'super_admin'")
        && str_contains($seeder, 'INSERT IGNORE INTO role_permissions'),
    'Automation permissions must be granted idempotently to super_admin only.'
);

$diagnostics = [
    'automation_foundation_available',
    'correspondence_schema_available',
    'correspondence_versions_available',
    'correspondence_parties_available',
    'correspondence_registry_books_available',
    'correspondence_registrations_available',
    'correspondence_relations_available',
    'correspondence_referrals_available',
    'correspondence_event_history_available',
    'correspondence_attachment_metadata_available',
    'correspondence_permissions_available',
    'correspondence_no_operational_ui',
];

foreach ($diagnostics as $diagnostic) {
    expectAutomation(str_contains($routes, "'{$diagnostic}'"), "Missing diagnostic: {$diagnostic}");
}

$foundationSources = $migration . "\n" . $seeder;
expectAutomation(
    preg_match('/\b(?:FROM|JOIN|UPDATE|INTO)\s+[^\s]+\s+rows\b/i', $foundationSources) === 0,
    'Automation SQL must not use ROWS as a table alias.'
);
expectAutomation(
    preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+(?:geographic_|data_source|external_code)/i', $foundationSources) === 0,
    'Automation foundation must not write geography or SCI infrastructure.'
);
expectAutomation(
    preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+[^\s]*(?:bot|rural_cooperation)/i', $foundationSources) === 0,
    'Automation foundation must not write bot or Rural Cooperation data.'
);
expectAutomation(!str_contains($seeder, 'INSERT INTO registry_books'), 'No real registry book or number may be seeded.');

echo "Automation correspondence foundation structural tests passed.\n";
