<?php
declare(strict_types=1);

namespace App\Services;

use IPKF\Support\Env;
use RuntimeException;

/**
 * Private, durable Safir manual-test reporting. No API credentials, full phone,
 * message body or variable values are read into reports.
 */
final class BaleSafirTestReportService
{
    private function directory(): ?string
    {
        $configured = trim((string) Env::get('BALE_SAFIR_TEST_ATTEMPT_DIR', ''));
        $catalog = trim((string) Env::get('BALE_SAFIR_CATALOG_PATH', ''));
        $parent = $catalog !== '' ? realpath(dirname($catalog)) : false;
        $path = $configured !== '' && !is_link($configured) ? realpath($configured) : false;
        if ($parent === false || $path === false || !is_dir($path)
            || !str_starts_with($path . '/', $parent . '/')) {
            return null;
        }
        return $path;
    }

    public function recent(int $limit = 20): array
    {
        $directory = $this->directory();
        if ($directory === null) {
            return [];
        }
        $limit = max(1, min(2000, $limit));
        $records = [];
        foreach (new \DirectoryIterator($directory) as $item) {
            if (!$item->isFile() || $item->isLink()
                || !preg_match('/^[a-f0-9]{64}\.json$/D', $item->getFilename())
                || $item->getSize() > 16384) {
                continue;
            }
            $value = @file_get_contents($item->getPathname());
            if (!is_string($value)) {
                continue;
            }
            $record = json_decode($value, true);
            if (!is_array($record)) {
                continue;
            }
            $status = (string) ($record['status'] ?? 'unknown');
            if (!in_array($status, ['sent', 'failed', 'unknown'], true)) {
                $status = 'unknown';
            }
            $records[] = [
                'time' => mb_substr((string) ($record['attempted_at'] ?? ''), 0, 36),
                'actor' => (int) ($record['actor'] ?? 0),
                'template' => mb_substr((string) ($record['template_code'] ?? ''), 0, 70),
                'phone_masked' => mb_substr((string) ($record['recipient_masked'] ?? ''), 0, 30),
                'request_id' => mb_substr((string) ($record['request_id'] ?? ''), 0, 130),
                'status' => $status,
                'http_code' => (int) ($record['http_code'] ?? 0),
                'message_id' => mb_substr((string) ($record['message_id'] ?? ''), 0, 180),
                'error_code' => (int) ($record['error_code'] ?? 0),
                'error_description' => mb_substr((string) ($record['error_description'] ?? ''), 0, 180),
            ];
            if (count($records) > 5000) {
                throw new RuntimeException('safir_report_too_many_records');
            }
        }
        usort($records, static fn (array $a, array $b): int =>
            strcmp($b['time'], $a['time']));
        return array_slice($records, 0, $limit);
    }

    public function csv(): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new RuntimeException('safir_report_unavailable');
        }
        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['زمان UTC', 'شناسه مدیر', 'کد قالب', 'شماره ماسک‌شده',
                'شناسه درخواست', 'وضعیت', 'HTTP', 'شناسه پیام', 'کد خطا', 'شرح خطا']);
            foreach ($this->recent(2000) as $row) {
                $values = [
                    $row['time'], (string) $row['actor'], $row['template'], $row['phone_masked'],
                    $row['request_id'], $row['status'], (string) $row['http_code'],
                    $row['message_id'], (string) $row['error_code'], $row['error_description'],
                ];
                fputcsv($stream, array_map(static function (string $value): string {
                    // Excel must never interpret untrusted provider fields as a formula.
                    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
                    return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
                }, $values));
            }
            rewind($stream);
            $result = stream_get_contents($stream);
            return is_string($result) ? $result : '';
        } finally {
            fclose($stream);
        }
    }
}
