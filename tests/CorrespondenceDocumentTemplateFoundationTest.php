<?php

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/public_html/system/Database/Migrations/CreateCorrespondenceDocumentTemplateTables.php');
$seeder = file_get_contents($root . '/public_html/system/Database/Seeds/CorrespondenceDocumentTemplateSeeder.php');
$repository = file_get_contents($root . '/public_html/app/Services/Automation/Correspondence/CorrespondenceDocumentTemplateRepository.php');
$commands = file_get_contents($root . '/public_html/app/Services/Automation/Correspondence/CorrespondenceCommandService.php');
$form = file_get_contents($root . '/public_html/resources/views/admin/automation-correspondence-form.php');

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(str_contains($migration, 'correspondence_document_templates'), 'Document template catalog table is required.');
$expect(str_contains($migration, 'correspondence_document_template_versions'), 'Versioned document template table is required.');
$expect(str_contains($migration, 'document_template_snapshot_json'), 'Correspondence versions must retain an immutable template snapshot.');
$expect(str_contains($seeder, "['a4' => [210, 297], 'a5' => [148, 210]]"), 'A4 and A5 page standards must be seeded.');
$expect(str_contains($seeder, "'fa' => ['فارسی', 'rtl']") && str_contains($seeder, "'en' => ['English', 'ltr']"), 'Persian and English layouts must be seeded.');
$expect(str_contains($seeder, 'foreach ([1, 2] as $slots)'), 'Single and dual signature layouts must be seeded.');
$expect(str_contains($repository, 'activeVersion') && str_contains($repository, 'snapshot'), 'Template resolution and snapshot building are required.');
$expect(str_contains($commands, 'document_template_required'), 'Draft commands must require an active document template.');
$expect(str_contains($form, 'automation-template-picker') && str_contains($form, 'document_template_reference'), 'Draft UI must provide a template picker.');

echo "Correspondence document template foundation checks passed.\n";
