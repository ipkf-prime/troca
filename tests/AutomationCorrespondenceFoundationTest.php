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

function compositeForeignKeyFragment(string $migration, string $constraint): string
{
    $constraintPosition = strpos($migration, "'{$constraint}'");
    expectAutomation($constraintPosition !== false, "Missing composite foreign key: {$constraint}");
    $prefix = substr($migration, 0, $constraintPosition);
    $start = strrpos($prefix, '$this->addCompositeForeignKeyIfPossible(');
    expectAutomation($start !== false, "Composite helper not used for: {$constraint}");
    $end = strpos($migration, ');', $constraintPosition);
    expectAutomation($end !== false, "Incomplete composite foreign key: {$constraint}");

    return substr($migration, $start, $end - $start + 2);
}

function partyTargetFragment(string $parties, string $kind): string
{
    $start = strpos($parties, "target_kind_code = '{$kind}'");
    expectAutomation($start !== false, "Missing party target kind: {$kind}");
    $fragment = substr($parties, $start);
    $nextArm = strpos($fragment, "\n                    OR (");
    $end = $nextArm === false ? strpos($fragment, "\n                )") : $nextArm;
    expectAutomation($end !== false, "Incomplete party target kind: {$kind}");

    return substr($fragment, 0, $end);
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
expectAutomation(
    str_contains($versions, 'UNIQUE KEY corr_versions_corr_id_unique (correspondence_id, id)'),
    'Correspondence versions must expose the aggregate candidate key in correspondence/id order.'
);
expectAutomation(
    str_contains($versions, 'UNIQUE KEY corr_versions_current_selection_unique (correspondence_id, id, version_number)'),
    'Current-version selection must expose an exact aggregate/id/number candidate key.'
);
expectAutomation(!str_contains($versions, 'updated_at'), 'Immutable versions must not have updated_at.');

$correspondences = tableFragment($migration, 'correspondences');
expectAutomation(
    str_contains($correspondences, 'current_version_id BIGINT UNSIGNED NULL')
        && str_contains($correspondences, 'current_version_id IS NULL AND current_version_number = 0')
        && str_contains($correspondences, 'current_version_id IS NOT NULL AND current_version_number > 0')
        && str_contains($correspondences, 'INDEX correspondences_current_version_index (id, current_version_id, current_version_number)'),
    'Initial drafts and selected current versions must use a consistent nullable current-version contract.'
);
$currentVersionForeignKey = compositeForeignKeyFragment($migration, 'corr_current_version_fk');
expectAutomation(
    str_contains($currentVersionForeignKey, "['id', 'current_version_id', 'current_version_number']")
        && str_contains($currentVersionForeignKey, "['correspondence_id', 'id', 'version_number']"),
    'The selected current version must belong to the same correspondence aggregate.'
);

$registryBooks = tableFragment($migration, 'registry_books');
expectAutomation(
    str_contains($registryBooks, 'fiscal_year_scope_key {$fiscalYearType} GENERATED ALWAYS AS')
        && str_contains($registryBooks, 'COALESCE(fiscal_year_id, 0)')
        && str_contains($registryBooks, 'org_unit_scope_key {$orgUnitType} GENERATED ALWAYS AS')
        && str_contains($registryBooks, 'COALESCE(org_unit_id, 0)'),
    'Nullable registry-book scopes must be normalized with type-compatible generated columns.'
);
expectAutomation(
    preg_match(
        '/UNIQUE KEY registry_books_scope_code_unique\s*\(\s*organization_id,\s*fiscal_year_scope_key,\s*org_unit_scope_key,\s*code\s*\)/s',
        $registryBooks
    ) === 1,
    'Registry books must reject duplicate codes even when fiscal year or unit scope is null.'
);

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
$personParty = partyTargetFragment($parties, 'person');
$organizationParty = partyTargetFragment($parties, 'organization');
$orgUnitParty = partyTargetFragment($parties, 'org_unit');
$externalParty = partyTargetFragment($parties, 'external');
expectAutomation(
    str_contains($personParty, 'external_display_name IS NULL')
        && str_contains($personParty, 'external_organization_name IS NULL')
        && str_contains($personParty, 'external_contact_or_address IS NULL')
        && str_contains($organizationParty, 'external_display_name IS NULL')
        && str_contains($organizationParty, 'external_organization_name IS NULL')
        && str_contains($organizationParty, 'external_contact_or_address IS NULL')
        && str_contains($orgUnitParty, 'external_display_name IS NULL')
        && str_contains($orgUnitParty, 'external_organization_name IS NULL')
        && str_contains($orgUnitParty, 'external_contact_or_address IS NULL'),
    'Internal parties must not carry external snapshot data.'
);
expectAutomation(
    str_contains($externalParty, 'person_id IS NULL')
        && str_contains($externalParty, 'organization_id IS NULL')
        && str_contains($externalParty, 'org_unit_id IS NULL')
        && str_contains($externalParty, 'CHAR_LENGTH(TRIM(external_display_name)) > 0'),
    'External parties must reject internal targets and blank display names.'
);

$referrals = tableFragment($migration, 'correspondence_referrals');
expectAutomation(
    str_contains($referrals, 'corr_referrals_one_target_check'),
    'A referral must select exactly one primary target.'
);
expectAutomation(
    str_contains($referrals, 'parent_referral_id BIGINT UNSIGNED NULL')
        && str_contains($referrals, 'UNIQUE KEY corr_referrals_corr_id_unique (correspondence_id, id)')
        && str_contains($referrals, 'INDEX corr_referrals_corr_parent_index (correspondence_id, parent_referral_id)'),
    'Forwarding must create a preserved child referral.'
);
$parentReferralForeignKey = compositeForeignKeyFragment($migration, 'corr_ref_parent_fk');
expectAutomation(
    str_contains($parentReferralForeignKey, "['correspondence_id', 'parent_referral_id']")
        && str_contains($parentReferralForeignKey, "['correspondence_id', 'id']"),
    'Referral parent foreign-key columns must match the aggregate candidate-key order.'
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
$eventReferralForeignKey = compositeForeignKeyFragment($migration, 'corr_events_referral_fk');
expectAutomation(
    str_contains($events, 'INDEX corr_events_corr_referral_index (correspondence_id, referral_id)')
        && str_contains($eventReferralForeignKey, "['correspondence_id', 'referral_id']")
        && str_contains($eventReferralForeignKey, "['correspondence_id', 'id']"),
    'Referral events must reference a referral from the same correspondence.'
);

$files = tableFragment($migration, 'private_files');
expectAutomation(str_contains($files, 'storage_key VARCHAR(1000)'), 'Private file storage keys are required.');
expectAutomation(str_contains($files, 'sha256_checksum CHAR(64)'), 'Private file checksums are required.');
expectAutomation(!preg_match('/\b(public_url|download_url|file_blob|LONGBLOB|MEDIUMBLOB|BLOB)\b/i', $files), 'Private files must not expose URLs or store binaries.');

$attachments = tableFragment($migration, 'correspondence_attachments');
$attachmentVersionForeignKey = compositeForeignKeyFragment($migration, 'corr_attach_version_fk');
expectAutomation(
    str_contains($attachments, 'INDEX corr_attachments_corr_version_index (correspondence_id, correspondence_version_id)')
        && str_contains($attachmentVersionForeignKey, "['correspondence_id', 'correspondence_version_id']")
        && str_contains($attachmentVersionForeignKey, "['correspondence_id', 'id']"),
    'Version attachments must reference a version from the same correspondence.'
);
expectAutomation(
    str_contains($migration, 'private function addCompositeForeignKeyIfPossible(')
        && str_contains($migration, 'ON UPDATE RESTRICT ON DELETE {$onDelete}'),
    'Composite aggregate foreign keys must use the idempotent migration helper.'
);

expectAutomation(
    !str_contains($migration, 'ON UPDATE CASCADE')
        && substr_count($migration, 'ON UPDATE RESTRICT ON DELETE {$onDelete}') === 2,
    'Every single-column and composite automation foreign key must use ON UPDATE RESTRICT.'
);
expectAutomation(
    str_contains($migration, 'FROM information_schema.referential_constraints')
        && str_contains($migration, 'SELECT UPPER(UPDATE_RULE), UPPER(DELETE_RULE)')
        && str_contains($migration, 'private function foreignKeyRules('),
    'Existing automation foreign-key update and delete rules must be inspected privately.'
);
expectAutomation(
    preg_match('/namespace IPKF\\\\Database\\\\Migrations;\s+use PDO;/', $migration) === 1
        || str_contains($migration, '\\PDO::FETCH_NUM'),
    'PDO::FETCH_NUM must resolve to the global PDO class inside the migration namespace.'
);
expectAutomation(
    str_contains($migration, 'private function reconcileForeignKeyRules(')
        && str_contains($migration, "if (\$this->foreignKeyRulesMatch(\$rules, 'RESTRICT', \$expectedDeleteRule))")
        && str_contains($migration, 'DROP FOREIGN KEY {$constraint}')
        && substr_count($migration, 'if (!$this->reconcileForeignKeyRules($table, $constraint, $onDelete))') === 2,
    'Matching constraints must be preserved while mismatched named constraints are recreated.'
);
expectAutomation(
    str_contains($migration, "'corr_rel_source_fk', 'source_correspondence_id', 'correspondences', 'id', 'RESTRICT'")
        && str_contains($migration, "'corr_rel_target_fk', 'target_correspondence_id', 'correspondences', 'id', 'RESTRICT'")
        && str_contains($relations, 'CONSTRAINT corr_relations_no_self_check CHECK (source_correspondence_id <> target_correspondence_id)'),
    'Correspondence relation source and target FKs must retain the MariaDB-safe self-relation check.'
);
expectAutomation(
    !preg_match('/\bDROP\s+(?:TABLE|COLUMN|CHECK|CONSTRAINT|INDEX|KEY)\b/i', $migration)
        && !preg_match('/\b(?:DELETE\s+FROM|TRUNCATE\s+TABLE)\b/i', $migration)
        && !str_contains($migration, 'FOREIGN_KEY_CHECKS'),
    'Foreign-key reconciliation must not remove schema objects or data or disable FK enforcement.'
);

$shouldRecreateForeignKey = static function (?array $rules, string $onDelete): bool {
    if ($rules === null) {
        return true;
    }

    return ($rules['update_rule'] ?? '') !== 'RESTRICT'
        || ($rules['delete_rule'] ?? '') !== strtoupper($onDelete);
};

expectAutomation($shouldRecreateForeignKey(null, 'RESTRICT'), 'A missing FK must be created.');
expectAutomation(
    !$shouldRecreateForeignKey(['update_rule' => 'RESTRICT', 'delete_rule' => 'RESTRICT'], 'RESTRICT'),
    'A matching FK must not be recreated.'
);
expectAutomation(
    $shouldRecreateForeignKey(['update_rule' => 'CASCADE', 'delete_rule' => 'RESTRICT'], 'RESTRICT'),
    'A legacy ON UPDATE CASCADE FK must be recreated.'
);
expectAutomation(
    $shouldRecreateForeignKey(['update_rule' => 'RESTRICT', 'delete_rule' => 'CASCADE'], 'RESTRICT'),
    'A mismatched ON DELETE rule must be recreated without changing the requested rule.'
);

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
