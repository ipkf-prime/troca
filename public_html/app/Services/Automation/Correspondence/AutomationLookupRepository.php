<?php

namespace App\Services\Automation\Correspondence;

use PDO;

class AutomationLookupRepository
{
    private const FALLBACK_LABELS = [
        'correspondence_direction' => ['incoming' => 'وارده', 'outgoing' => 'صادره', 'internal' => 'داخلی'],
        'correspondence_status' => ['draft' => 'پیش نویس', 'registered' => 'ثبت شده', 'received' => 'دریافت شده', 'dispatched' => 'ارسال شده', 'in_progress' => 'در حال اقدام', 'closed' => 'بسته شده', 'cancelled' => 'لغو شده'],
        'correspondence_priority' => ['low' => 'کم', 'normal' => 'عادی', 'high' => 'زیاد', 'urgent' => 'فوری'],
        'correspondence_confidentiality' => ['normal' => 'عادی', 'confidential' => 'محرمانه', 'secret' => 'سری', 'top_secret' => 'به کلی سری'],
        'correspondence_channel' => ['manual' => 'ثبت دستی', 'postal' => 'پست', 'courier' => 'پیک', 'email' => 'رایانامه', 'system' => 'سامانه', 'internal' => 'داخلی'],
        'correspondence_party_role' => ['sender' => 'فرستنده', 'primary_recipient' => 'گیرنده اصلی', 'cc' => 'رونوشت', 'bcc' => 'رونوشت مخفی', 'external_correspondent' => 'طرف بیرونی'],
        'correspondence_party_kind' => ['person' => 'شخص داخلی', 'user' => 'کاربر داخلی', 'organization' => 'سازمان', 'org_unit' => 'واحد سازمانی', 'external' => 'طرف بیرونی'],
        'correspondence_event_type' => ['created' => 'ایجاد شد', 'revised' => 'نسخه جدید پیش نویس', 'registered' => 'ثبت رسمی', 'dispatched' => 'ارسال شد', 'cancelled' => 'لغو شد', 'attachment_linked' => 'پیوست افزوده شد', 'attachment_removed' => 'پیوست حذف شد', 'attachment_metadata_updated' => 'مشخصات پیوست ویرایش شد'],
        'correspondence_relation_type' => ['reply_to' => 'عطف / پاسخ به', 'follow_up' => 'پیرو', 'continuation' => 'ادامه', 'replacement' => 'جایگزین', 'related' => 'مرتبط', 'cancellation_reference' => 'مرجع ابطال'],
        'attachment_role' => ['main' => 'فایل اصلی', 'enclosure' => 'پیوست', 'supporting' => 'مدرک پشتیبان', 'scan' => 'تصویر اسکن‌شده'],
    ];

    public function __construct(private ?AutomationOperationalRuntime $runtime = null)
    {
        $this->runtime ??= new AutomationOperationalRuntime();
    }

    public function options(string $domain): array
    {
        $statement = $this->connection()->prepare('
            SELECT lookup_values.code, lookup_values.title
            FROM lookup_values
            INNER JOIN lookup_domains ON lookup_domains.id = lookup_values.domain_id
            WHERE lookup_domains.code = ?
              AND lookup_domains.status = ?
              AND lookup_values.status = ?
            ORDER BY lookup_values.sort_order ASC, lookup_values.id ASC
        ');
        $statement->execute([$domain, 'active', 'active']);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $fallback = self::FALLBACK_LABELS[$domain] ?? [];

        return array_map(fn (array $row): array => [
            'code' => (string) $row['code'],
            'label' => $fallback[(string) $row['code']] ?? (string) $row['title'],
        ], $rows);
    }

    public function label(string $domain, ?string $code): string
    {
        $code = trim((string) ($code ?? ''));

        if ($code === '') {
            return '—';
        }

        foreach ($this->options($domain) as $option) {
            if (($option['code'] ?? '') === $code) {
                return (string) $option['label'];
            }
        }

        return self::FALLBACK_LABELS[$domain][$code] ?? $code;
    }

    public function valid(string $domain, string $code): bool
    {
        foreach ($this->options($domain) as $option) {
            if (($option['code'] ?? '') === $code) {
                return true;
            }
        }

        return false;
    }

    public function formOptions(): array
    {
        return [
            'directions' => $this->options('correspondence_direction'),
            'statuses' => $this->options('correspondence_status'),
            'priorities' => $this->options('correspondence_priority'),
            'confidentialities' => $this->options('correspondence_confidentiality'),
            'channels' => $this->options('correspondence_channel'),
            'party_roles' => $this->options('correspondence_party_role'),
            'party_kinds' => [
                ['code' => 'user', 'label' => 'کاربر داخلی'],
                ['code' => 'person', 'label' => 'شخص داخلی'],
                ['code' => 'organization', 'label' => 'سازمان'],
                ['code' => 'org_unit', 'label' => 'واحد سازمانی'],
                ['code' => 'external', 'label' => 'طرف بیرونی'],
            ],
            'relation_types' => $this->options('correspondence_relation_type'),
            'attachment_roles' => $this->options('attachment_role'),
        ];
    }

    private function connection(): PDO
    {
        return $this->runtime->connection();
    }
}
