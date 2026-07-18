<?php

namespace IPKF\Database\Seeds;

class CorrespondenceDocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (!$this->tableExists('correspondence_document_templates')
            || !$this->tableExists('correspondence_document_template_versions')) {
            return;
        }

        $templates = [];
        foreach (['a4' => [210, 297], 'a5' => [148, 210]] as $page => [$width, $height]) {
            foreach (['fa' => ['فارسی', 'rtl'], 'en' => ['English', 'ltr']] as $language => [$languageTitle, $direction]) {
                foreach ([1, 2] as $slots) {
                    $code = "letter_{$page}_{$language}_{$slots}sig";
                    $templates[] = [
                        'code' => $code,
                        'title_fa' => 'نامه ' . strtoupper($page) . ' ' . ($language === 'fa' ? 'فارسی' : 'انگلیسی') . ' ' . ($slots === 1 ? 'تک‌امضا' : 'دوامضا'),
                        'title_en' => strtoupper($page) . " {$languageTitle} " . ($slots === 1 ? 'Single signature' : 'Dual signature'),
                        'language' => $language,
                        'page' => strtoupper($page),
                        'slots' => $slots,
                        'page_schema' => ['width_mm' => $width, 'height_mm' => $height, 'margin_top_mm' => 28, 'margin_right_mm' => 20, 'margin_bottom_mm' => 24, 'margin_left_mm' => 20, 'direction' => $direction],
                    ];
                }
            }
        }

        foreach ($templates as $index => $template) {
            $this->seedTemplate($template, ($index + 1) * 10);
        }
    }

    private function seedTemplate(array $template, int $sortOrder): void
    {
        $reference = substr(hash('sha256', 'ipkf:' . $template['code']), 0, 32);
        $statement = $this->db->prepare("
            INSERT INTO correspondence_document_templates (
                public_reference, code, title_fa, title_en, language_code, page_size_code,
                orientation_code, signature_slots, status, sort_order, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'portrait', ?, 'active', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE title_fa = VALUES(title_fa), title_en = VALUES(title_en),
                language_code = VALUES(language_code), page_size_code = VALUES(page_size_code),
                signature_slots = VALUES(signature_slots), status = 'active', sort_order = VALUES(sort_order),
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([$reference, $template['code'], $template['title_fa'], $template['title_en'], $template['language'], $template['page'], $template['slots'], $sortOrder]);

        $idStatement = $this->db->prepare('SELECT id FROM correspondence_document_templates WHERE code = ? LIMIT 1');
        $idStatement->execute([$template['code']]);
        $templateId = (int) $idStatement->fetchColumn();
        $header = ['logo' => true, 'organization_title' => true, 'date' => true, 'number' => true, 'attachment' => true];
        $footer = ['page_number' => true, 'organization_contact' => true, 'confidentiality_mark' => true];
        $signature = ['slots' => $template['slots'], 'show_name' => true, 'show_position' => true, 'show_asset' => true];
        $snapshot = ['header' => $header, 'footer' => $footer, 'page' => $template['page_schema'], 'signature' => $signature];
        $checksum = hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $version = $this->db->prepare("
            INSERT INTO correspondence_document_template_versions (
                template_id, version_number, header_schema_json, footer_schema_json,
                page_schema_json, signature_schema_json, content_checksum, status, created_at
            ) VALUES (?, 1, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $version->execute([$templateId, json_encode($header, JSON_UNESCAPED_UNICODE), json_encode($footer, JSON_UNESCAPED_UNICODE), json_encode($template['page_schema'], JSON_UNESCAPED_UNICODE), json_encode($signature, JSON_UNESCAPED_UNICODE), $checksum]);

        $versionIdStatement = $this->db->prepare('SELECT id FROM correspondence_document_template_versions WHERE template_id = ? AND version_number = 1 LIMIT 1');
        $versionIdStatement->execute([$templateId]);
        $this->db->prepare('UPDATE correspondence_document_templates SET current_version_id = ? WHERE id = ?')->execute([(int) $versionIdStatement->fetchColumn(), $templateId]);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);
        return (int) $statement->fetchColumn() > 0;
    }
}
