<?php

namespace App\Services\Automation\Correspondence;

use App\Support\AdminFormat;
use IPKF\Database\Database;
use PDO;

class CorrespondenceViewModelBuilder
{
    public function __construct(private ?AutomationLookupRepository $lookups = null)
    {
        $this->lookups ??= new AutomationLookupRepository();
    }

    public function listItem(array $row): array
    {
        $reference = (string) ($row['public_reference'] ?? '');

        return [
            'public_reference' => $reference,
            'url' => '/admin/automation/correspondences/' . rawurlencode($reference),
            'edit_url' => '/admin/automation/correspondences/' . rawurlencode($reference) . '/edit',
            'subject' => $this->value($row['subject'] ?? null),
            'type' => $this->lookups->label('correspondence_direction', $row['direction_code'] ?? ''),
            'direction' => $this->lookups->label('correspondence_direction', $row['direction_code'] ?? ''),
            'status' => $this->badge((string) ($row['status_code'] ?? '')),
            'priority' => $this->lookups->label('correspondence_priority', $row['priority_code'] ?? ''),
            'confidentiality' => $this->lookups->label('correspondence_confidentiality', $row['confidentiality_code'] ?? ''),
            'correspondent' => $this->value($row['correspondent_display'] ?? null),
            'current_version' => AdminFormat::digits((int) ($row['current_version_number'] ?? 0)),
            'relevant_date' => $this->dateOnly($row['external_date'] ?? null),
            'updated_at' => $this->dateTime($row['updated_at'] ?? null),
            'editable' => ($row['status_code'] ?? '') === 'draft',
        ];
    }

    public function detail(array $correspondence, array $versions, array $parties, array $events, string $tab): array
    {
        $reference = (string) ($correspondence['public_reference'] ?? '');
        $tabs = $this->tabs($reference, $tab);
        $latestVersion = $versions[0] ?? [];

        return [
            'correspondence' => $this->detailFields($correspondence, $latestVersion),
            'workspace' => [
                'title' => $this->value($correspondence['subject'] ?? null),
                'subtitle' => 'فضای کاری مکاتبه اداری؛ نسخه‌ها، طرف‌ها و تاریخچه بدون نمایش شناسه‌های فنی',
                'icon' => 'file-lines',
                'back_url' => '/admin/automation/correspondences',
                'back_label' => 'بازگشت به مکاتبات',
                'active_tab' => $tab,
                'badges' => [$this->badge((string) ($correspondence['status_code'] ?? ''))],
                'meta' => [
                    ['label' => 'شناسه عمومی', 'value' => $reference, 'dir' => 'ltr'],
                    ['label' => 'نسخه جاری', 'value' => AdminFormat::digits((int) ($correspondence['current_version_number'] ?? 0))],
                ],
            ],
            'tabs' => $tabs,
            'active_tab' => $tab,
            'versions' => array_map(fn (array $row): array => $this->version($row), $versions),
            'parties' => array_map(fn (array $row): array => $this->party($row), $parties),
            'events' => array_map(fn (array $row): array => $this->event($row), $events),
            'editable' => ($correspondence['status_code'] ?? '') === 'draft',
            'edit_url' => '/admin/automation/correspondences/' . rawurlencode($reference) . '/edit',
        ];
    }

    public function formData(?array $correspondence, array $versions, array $parties): array
    {
        $latestVersion = $versions[0] ?? [];

        return [
            'public_reference' => $correspondence['public_reference'] ?? '',
            'lock_version' => (int) ($correspondence['lock_version'] ?? 0),
            'subject' => (string) ($correspondence['subject'] ?? ''),
            'summary' => (string) ($correspondence['summary'] ?? ''),
            'document_template_reference' => (string) ($correspondence['document_template_reference'] ?? ''),
            'content' => (string) ($latestVersion['content_snapshot'] ?? ''),
            'direction_code' => (string) ($correspondence['direction_code'] ?? 'incoming'),
            'priority_code' => (string) ($correspondence['priority_code'] ?? 'normal'),
            'confidentiality_code' => (string) ($correspondence['confidentiality_code'] ?? 'normal'),
            'channel_code' => (string) ($correspondence['channel_code'] ?? 'manual'),
            'external_number' => (string) ($correspondence['external_number'] ?? ''),
            'external_date' => (string) ($correspondence['external_date'] ?? ''),
            'parties' => $parties,
        ];
    }

    private function detailFields(array $row, array $version): array
    {
        return [
            'public_reference' => (string) ($row['public_reference'] ?? ''),
            'subject' => $this->value($row['subject'] ?? null),
            'summary' => $this->value($row['summary'] ?? null),
            'document_template' => $this->value($row['document_template_title'] ?? null),
            'content' => $this->value($version['content_snapshot'] ?? null),
            'type' => $this->lookups->label('correspondence_direction', $row['direction_code'] ?? ''),
            'status' => $this->badge((string) ($row['status_code'] ?? '')),
            'priority' => $this->lookups->label('correspondence_priority', $row['priority_code'] ?? ''),
            'confidentiality' => $this->lookups->label('correspondence_confidentiality', $row['confidentiality_code'] ?? ''),
            'channel' => $this->lookups->label('correspondence_channel', $row['channel_code'] ?? ''),
            'external_number' => $this->value($row['external_number'] ?? null),
            'external_date' => $this->dateOnly($row['external_date'] ?? null),
            'created_at' => $this->dateTime($row['created_at'] ?? null),
            'updated_at' => $this->dateTime($row['updated_at'] ?? null),
            'lock_version' => (int) ($row['lock_version'] ?? 0),
        ];
    }

    private function version(array $row): array
    {
        return [
            'number' => AdminFormat::digits((int) ($row['version_number'] ?? 0)),
            'subject' => $this->value($row['subject_snapshot'] ?? null),
            'summary' => $this->value($row['summary_snapshot'] ?? null),
            'change_note' => $this->value($row['change_note'] ?? null),
            'created_at' => $this->dateTime($row['created_at'] ?? null),
        ];
    }

    private function party(array $row): array
    {
        return [
            'role' => $this->lookups->label('correspondence_party_role', $row['party_role_code'] ?? ''),
            'kind' => $this->partyKindLabel((string) ($row['target_kind_code'] ?? '')),
            'display' => $this->partyDisplay($row),
            'contact' => $this->value($row['external_contact_or_address'] ?? null),
        ];
    }

    private function event(array $row): array
    {
        return [
            'type' => $this->lookups->label('correspondence_event_type', $row['event_type_code'] ?? ''),
            'from' => $this->lookups->label('correspondence_status', $row['previous_status_code'] ?? ''),
            'to' => $this->lookups->label('correspondence_status', $row['resulting_status_code'] ?? ''),
            'occurred_at' => $this->dateTime($row['occurred_at'] ?? null),
        ];
    }

    private function tabs(string $reference, string $active): array
    {
        $base = '/admin/automation/correspondences/' . rawurlencode($reference);
        $items = [
            ['key' => 'summary', 'title' => 'خلاصه', 'icon' => 'file-lines', 'url' => $base],
            ['key' => 'content', 'title' => 'نسخه جاری', 'icon' => 'file-lines', 'url' => $base . '?tab=content'],
            ['key' => 'parties', 'title' => 'طرف‌ها', 'icon' => 'users', 'url' => $base . '?tab=parties'],
            ['key' => 'versions', 'title' => 'نسخه‌ها', 'icon' => 'calendar', 'url' => $base . '?tab=versions'],
            ['key' => 'history', 'title' => 'تاریخچه', 'icon' => 'status', 'url' => $base . '?tab=history'],
        ];

        return array_map(fn (array $item): array => $item + ['is_visible' => true, 'is_active' => $item['key'] === $active], $items);
    }

    private function partyDisplay(array $row): string
    {
        if (($row['target_kind_code'] ?? '') === 'external') {
            $parts = array_filter([(string) ($row['external_display_name'] ?? ''), (string) ($row['external_organization_name'] ?? '')]);
            return $this->value(implode(' - ', $parts));
        }

        $core = Database::connect();

        if (!empty($row['person_id'])) {
            $statement = $core->prepare('SELECT full_name FROM persons WHERE id = ? LIMIT 1');
            $statement->execute([(int) $row['person_id']]);
            return $this->value($statement->fetchColumn() ?: null);
        }

        if (!empty($row['organization_id'])) {
            $statement = $core->prepare('SELECT title FROM organizations WHERE id = ? LIMIT 1');
            $statement->execute([(int) $row['organization_id']]);
            return $this->value($statement->fetchColumn() ?: null);
        }

        if (!empty($row['org_unit_id'])) {
            $statement = $core->prepare('SELECT title FROM org_units WHERE id = ? LIMIT 1');
            $statement->execute([(int) $row['org_unit_id']]);
            return $this->value($statement->fetchColumn() ?: null);
        }

        return 'مرجع داخلی';
    }

    private function partyKindLabel(string $kind): string
    {
        return match ($kind) {
            'person' => 'شخص/کاربر داخلی',
            'organization' => 'سازمان',
            'org_unit' => 'واحد سازمانی',
            'external' => 'طرف بیرونی',
            default => '—',
        };
    }

    private function badge(string $status): array
    {
        return ['code' => $status !== '' ? $status : 'unknown', 'label' => $this->lookups->label('correspondence_status', $status)];
    }

    private function value(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '—' : $value;
    }

    private function dateTime(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '—' : AdminFormat::jalaliDateTime($value);
    }

    private function dateOnly(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? '—' : AdminFormat::jalaliDate($value);
    }
}
