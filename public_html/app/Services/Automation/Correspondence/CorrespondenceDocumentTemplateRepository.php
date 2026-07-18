<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class CorrespondenceDocumentTemplateRepository
{
    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function options(): array
    {
        $statement = $this->connection()->query("
            SELECT t.public_reference, t.code, t.title_fa, t.title_en, t.language_code,
                   t.page_size_code, t.orientation_code, t.signature_slots,
                   v.version_number
            FROM correspondence_document_templates t
            INNER JOIN correspondence_document_template_versions v ON v.id = t.current_version_id
            WHERE t.status = 'active' AND v.status = 'active'
            ORDER BY t.sort_order, t.id
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function activeVersion(string $publicReference): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT v.id AS version_id, v.version_number, v.header_schema_json, v.footer_schema_json,
                   v.page_schema_json, v.signature_schema_json, v.content_checksum,
                   t.public_reference, t.code, t.title_fa, t.title_en, t.language_code,
                   t.page_size_code, t.orientation_code, t.signature_slots
            FROM correspondence_document_templates t
            INNER JOIN correspondence_document_template_versions v ON v.id = t.current_version_id
            WHERE t.public_reference = ? AND t.status = 'active' AND v.status = 'active'
            LIMIT 1
        ");
        $statement->execute([$publicReference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function snapshot(array $version): array
    {
        return [
            'template_reference' => $version['public_reference'],
            'template_code' => $version['code'],
            'template_title_fa' => $version['title_fa'],
            'template_title_en' => $version['title_en'],
            'language_code' => $version['language_code'],
            'page_size_code' => $version['page_size_code'],
            'orientation_code' => $version['orientation_code'],
            'signature_slots' => (int) $version['signature_slots'],
            'version_number' => (int) $version['version_number'],
            'content_checksum' => $version['content_checksum'],
            'header' => $this->json($version['header_schema_json']),
            'footer' => $this->json($version['footer_schema_json']),
            'page' => $this->json($version['page_schema_json']),
            'signature' => $this->json($version['signature_schema_json']),
        ];
    }

    private function json(?string $json): array
    {
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function connection(): PDO
    {
        return $this->runtime->connection();
    }
}
