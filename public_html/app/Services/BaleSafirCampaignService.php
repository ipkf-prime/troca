<?php
declare(strict_types=1);

namespace App\Services;

use IPKF\Support\Env;
use IPKF\Support\Session;
use RuntimeException;
use ZipArchive;

final class BaleSafirCampaignService
{
    private const SESSION_KEY = 'ipkf_bale_safir_campaign_v1';
    private const MAX_UPLOAD_BYTES = 2097152;
    private const MAX_UNCOMPRESSED_BYTES = 4194304;
    private const MAX_ROWS = 500;

    public function page(int $userId): array
    {
        $campaign = $this->campaign($userId);
        $settings = $this->settings();

        $counts = ['ready' => 0, 'sent' => 0, 'failed' => 0, 'unknown' => 0, 'skipped' => 0];
        foreach (($campaign['rows'] ?? []) as $row) {
            $status = (string) ($row['status'] ?? 'skipped');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        return [
            'settings' => $settings,
            'campaign' => $campaign,
            'counts' => $counts,
            'send_enabled' => $this->sendEnabled(),
            'test_phone_masked' => $this->maskPhone(
                (string) Env::get('BALE_SAFIR_TEST_PHONE', '')
            ),
            'credential_ready' => trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', '')) !== '',
        ];
    }

    public function settings(): array
    {
        $path = BASE_PATH . '/storage/private/bale_safir_settings.json';
        $initial = BASE_PATH . '/resources/ui-content/bale-safir-invite.json';
        $source = is_readable($path) ? $path : $initial;
        $value = is_readable($source) ? json_decode((string) file_get_contents($source), true) : null;
        if (!is_array($value)) {
            throw new RuntimeException('safir_settings_missing');
        }

        return [
            'bot_id' => (string) ($value['bot_id'] ?? ''),
            'template_id' => (string) ($value['template_id'] ?? ''),
            'body' => (string) ($value['body'] ?? ''),
        ];
    }

    public function saveSettings(int $userId, array $input): void
    {
        $botId = trim((string) ($input['bot_id'] ?? ''));
        $templateId = trim((string) ($input['template_id'] ?? ''));
        $body = trim((string) ($input['body'] ?? ''));
        if (!preg_match('/^[1-9][0-9]{5,19}$/D', $botId)
            || !preg_match('/^[A-Za-z0-9_-]{2,64}$/D', $templateId)
            || mb_strlen($body, 'UTF-8') > 4000
            || $body === ''
            || substr_count($body, '{{name}}') !== 1
            || substr_count($body, '{{sherkat}}') !== 1
        ) {
            throw new RuntimeException('safir_settings_invalid');
        }
        $directory = BASE_PATH . '/storage/private';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('safir_storage_unavailable');
        }
        $path = $directory . '/bale_safir_settings.json';
        $temp = tempnam($directory, 'safir_');
        if (!is_string($temp)) {
            throw new RuntimeException('safir_storage_unavailable');
        }
        try {
            $written = file_put_contents($temp, json_encode([
                'bot_id' => $botId,
                'template_id' => $templateId,
                'body' => $body,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
            if ($written === false || !chmod($temp, 0600) || !rename($temp, $path)) {
                throw new RuntimeException('safir_storage_unavailable');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
        // A template change invalidates a prior preview and all pending sends.
        Session::forget(self::SESSION_KEY);
    }

    public function import(int $userId, array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null)
            || !is_uploaded_file($file['tmp_name'])
            || (int) ($file['size'] ?? 0) <= 0
            || (int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES
            || !preg_match('/\.xlsx$/i', (string) ($file['name'] ?? ''))
        ) {
            throw new RuntimeException('safir_invalid_upload');
        }
        $rows = $this->parseWorkbook((string) $file['tmp_name']);
        $settings = $this->settings();
        $items = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $first = trim($this->normalizeText($row[0] ?? ''));
            $last = trim($this->normalizeText($row[1] ?? ''));
            $company = trim($this->normalizeText($row[2] ?? ''));
            $phone = $this->normalizePhone($row[3] ?? '');
            $status = 'ready';
            $error = '';
            if (!$phone || $first === '' || $last === '' || $company === ''
                || mb_strlen($first, 'UTF-8') > 100
                || mb_strlen($last, 'UTF-8') > 100
                || mb_strlen($company, 'UTF-8') > 250
            ) {
                $status = 'skipped';
                $error = 'اطلاعات ناقص یا شماره نامعتبر';
            } elseif (isset($seen[$phone])) {
                $status = 'skipped';
                $error = 'شماره تکراری';
            } else {
                $seen[$phone] = true;
            }
            $name = trim($first . ' ' . $last);
            $items[] = [
                'row' => $index + 2,
                'name' => $name,
                'company' => $company,
                'phone' => $phone ?: '',
                'status' => $status,
                'error' => $error,
                'message_id' => '',
                'http_code' => '',
                'attempted_at' => '',
                'preview' => strtr($settings['body'], [
                    '{{name}}' => $name,
                    '{{sherkat}}' => $company,
                ]),
            ];
        }
        $campaign = [
            'actor_user_id' => $userId,
            'reference' => bin2hex(random_bytes(12)),
            'template_hash' => hash('sha256', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'created_at' => gmdate('c'),
            'rows' => $items,
            'test_status' => '',
            'test_message_id' => '',
            'last_error' => '',
        ];
        Session::put(self::SESSION_KEY, $campaign);
        return $campaign;
    }

    public function reset(int $userId): void
    {
        $this->campaign($userId);
        Session::forget(self::SESSION_KEY);
    }

    public function test(int $userId): string
    {
        $campaign = $this->requireCampaign($userId);
        $this->assertSendEnabled();
        $allow = $this->normalizePhone((string) Env::get('BALE_SAFIR_TEST_PHONE', ''));
        if ($allow === null) {
            throw new RuntimeException('safir_test_allowlist_missing');
        }
        $first = null;
        foreach ($campaign['rows'] as $row) {
            if ($row['status'] === 'ready') {
                $first = $row;
                break;
            }
        }
        if ($first === null) {
            throw new RuntimeException('safir_no_ready_rows');
        }
        // Mark unknown before network I/O; no silent duplicate retries.
        $campaign['test_status'] = 'unknown';
        $campaign['test_message_id'] = '';
        Session::put(self::SESSION_KEY, $campaign);
        $result = $this->sendOne($allow, $first, 'test-' . $campaign['reference']);
        $campaign['test_status'] = $result['status'];
        $campaign['test_message_id'] = $result['message_id'];
        $campaign['last_error'] = $result['error'];
        Session::put(self::SESSION_KEY, $campaign);
        return $result['status'];
    }

    public function dispatch(int $userId, string $confirmation, string $reference): array
    {
        $this->assertSendEnabled();
        $campaign = $this->requireCampaign($userId);
        if (!hash_equals((string) $campaign['reference'], $reference)
            || !hash_equals('SEND ' . $reference, $confirmation)
            || ($campaign['test_status'] ?? '') !== 'sent'
        ) {
            throw new RuntimeException('safir_confirmation_required');
        }
        $sent = 0;
        foreach ($campaign['rows'] as $index => $row) {
            if ($row['status'] !== 'ready') {
                continue;
            }
            if ($sent >= 3) {
                break;
            }
            $sent++;
            // Claim each row *before* network I/O; session lock serializes requests.
            $campaign['rows'][$index]['status'] = 'unknown';
            $campaign['rows'][$index]['attempted_at'] = gmdate('c');
            Session::put(self::SESSION_KEY, $campaign);
            try {
                $result = $this->sendOne($row['phone'], $row, 'campaign-' . $campaign['reference'] . '-' . $index);
                $campaign['rows'][$index]['status'] = $result['status'];
                $campaign['rows'][$index]['message_id'] = $result['message_id'];
                $campaign['rows'][$index]['http_code'] = (string) $result['http_code'];
                $campaign['rows'][$index]['error'] = $result['error'];
            } catch (\Throwable) {
                $campaign['rows'][$index]['status'] = 'unknown';
                $campaign['rows'][$index]['error'] = 'وضعیت ارسال نامشخص؛ نیازمند بررسی';
                Session::put(self::SESSION_KEY, $campaign);
                break;
            }
            Session::put(self::SESSION_KEY, $campaign);
            if ($result['status'] === 'sent' && $sent < 3) {
                $delayMs = max(500, min(10000, (int) Env::get('BALE_SAFIR_INTERVAL_MS', 1500)));
                usleep($delayMs * 1000);
            }
            if ($result['status'] !== 'sent') {
                // Stop on provider errors or uncertain delivery; don't automatically retry.
                break;
            }
        }
        return $this->page($userId)['counts'];
    }

    public function export(int $userId): string
    {
        $campaign = $this->requireCampaign($userId);
        $stream = fopen('php://temp', 'w+');
        if (!$stream) {
            throw new RuntimeException('safir_export_failed');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['ردیف', 'نام مدیرعامل', 'شرکت', 'شماره همراه', 'وضعیت', 'خطا', 'شناسه پیام', 'کد HTTP', 'زمان']);
        foreach ($campaign['rows'] as $row) {
            fputcsv($stream, array_map(
                static function ($value): string {
                    $value = (string) $value;
                    return preg_match('/^[=\+\-@\t\r]/u', $value) ? "'" . $value : $value;
                },
                [$row['row'], $row['name'], $row['company'], $row['phone'], $row['status'],
                 $row['error'], $row['message_id'], $row['http_code'], $row['attempted_at']]
            ));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return is_string($csv) ? $csv : '';
    }

    private function campaign(int $userId): ?array
    {
        $campaign = Session::get(self::SESSION_KEY);
        if (!is_array($campaign) || (int) ($campaign['actor_user_id'] ?? 0) !== $userId) {
            return null;
        }
        return $campaign;
    }

    private function requireCampaign(int $userId): array
    {
        $campaign = $this->campaign($userId);
        if ($campaign === null) {
            throw new RuntimeException('safir_campaign_missing');
        }
        $hash = hash('sha256', json_encode($this->settings(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (!hash_equals($hash, (string) $campaign['template_hash'])) {
            throw new RuntimeException('safir_template_changed');
        }
        return $campaign;
    }

    private function sendEnabled(): bool
    {
        $enabled = filter_var(Env::get('BALE_SAFIR_SEND_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $production = strtolower((string) Env::get('APP_ENV', 'production')) === 'production';
        return $enabled && (!$production ||
            filter_var(Env::get('BALE_SAFIR_PRODUCTION_APPROVED', false), FILTER_VALIDATE_BOOLEAN));
    }

    private function assertSendEnabled(): void
    {
        if (!$this->sendEnabled()) {
            throw new RuntimeException('safir_send_disabled');
        }
        if (trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', '')) === '') {
            throw new RuntimeException('safir_key_missing');
        }
    }

    private function sendOne(string $phone, array $row, string $requestId): array
    {
        $settings = $this->settings();
        $key = trim((string) Env::get('BALE_SAFIR_API_ACCESS_KEY', ''));
        if (!function_exists('curl_init')) {
            throw new RuntimeException('safir_curl_missing');
        }
        $payload = json_encode([
            'bot_id' => (int) $settings['bot_id'],
            'phone_number' => $phone,
            'message_data' => ['template_message' => [
                'template_id' => $settings['template_id'],
                'text_fields' => [
                    'name' => $row['name'],
                    'sherkat' => $row['company'],
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $curl = curl_init('https://safir.bale.ai/api/v3/send_message');
        if (!$curl) {
            throw new RuntimeException('safir_curl_missing');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'api-access-key: ' . $key,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }
        try {
            $raw = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }
        if (!is_string($raw)) {
            return ['status' => 'unknown', 'http_code' => $httpCode, 'message_id' => '',
                'error' => 'قطع ارتباط؛ وضعیت ارسال مشخص نیست'];
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['status' => 'unknown', 'http_code' => $httpCode, 'message_id' => '',
                'error' => 'پاسخ نامعتبر؛ وضعیت ارسال نامشخص'];
        }
        $errorData = $json['error_data'] ?? null;
        $messageId = trim((string) ($json['message_id'] ?? ($json['data']['message_id'] ?? '')));
        $ok = $httpCode >= 200 && $httpCode < 300 && $messageId !== ''
            && ($errorData === null || $errorData === [] || $errorData === '');
        $error = $ok ? '' : (
            $errorData !== null && $errorData !== [] && $errorData !== ''
                ? mb_substr((string) json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 0, 180, 'UTF-8')
                : 'پاسخ سرویس ارسال تأیید نشده است'
        );
        return [
            'status' => $ok ? 'sent' : ($httpCode >= 400 && $httpCode < 500 ? 'failed' : 'unknown'),
            'http_code' => $httpCode,
            'message_id' => $messageId,
            'error' => $error,
        ];
    }

    private function parseWorkbook(string $path): array
    {
        if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
            throw new RuntimeException('safir_xlsx_extension_missing');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('safir_xlsx_invalid');
        }
        try {
            if ($zip->numFiles > 100) {
                throw new RuntimeException('safir_xlsx_too_large');
            }
            $read = static function (ZipArchive $zip, string $name): ?string {
                $stat = $zip->statName($name);
                if (!is_array($stat)) {
                    return null;
                }
                if (($stat['size'] ?? 0) > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('safir_xlsx_too_large');
                }
                $raw = $zip->getFromName($name);
                return is_string($raw) ? $raw : null;
            };
            $sharedXml = $read($zip, 'xl/sharedStrings.xml');
            $sheetXml = $read($zip, 'xl/worksheets/sheet1.xml');
            if ($sheetXml === null) {
                throw new RuntimeException('safir_xlsx_sheet_missing');
            }
            $shared = [];
            if ($sharedXml !== null) {
                $xml = @simplexml_load_string($sharedXml, \SimpleXMLElement::class, LIBXML_NONET);
                if (!$xml) {
                    throw new RuntimeException('safir_xlsx_invalid');
                }
                foreach ($xml->si as $si) {
                    $parts = $si->xpath('.//*[local-name()="t"]') ?: [];
                    $shared[] = implode('', array_map('strval', $parts));
                }
            }
            $xml = @simplexml_load_string($sheetXml, \SimpleXMLElement::class, LIBXML_NONET);
            if (!$xml) {
                throw new RuntimeException('safir_xlsx_invalid');
            }
            $table = [];
            foreach ($xml->sheetData->row as $row) {
                $number = (int) $row['r'];
                if ($number <= 0 || $number > self::MAX_ROWS + 1) {
                    throw new RuntimeException('safir_xlsx_too_many_rows');
                }
                $cells = [];
                foreach ($row->c as $cell) {
                    if (isset($cell->f)) {
                        throw new RuntimeException('safir_xlsx_formulas_forbidden');
                    }
                    if (!preg_match('/^([A-Z]+)[0-9]+$/D', (string) $cell['r'], $m)) {
                        continue;
                    }
                    $column = 0;
                    foreach (str_split($m[1]) as $letter) {
                        $column = $column * 26 + (ord($letter) - 64);
                    }
                    if ($column > 20) {
                        continue;
                    }
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $parts = $cell->is->xpath('.//*[local-name()="t"]') ?: [];
                        $value = implode('', array_map('strval', $parts));
                    }
                    $cells[$column - 1] = $value;
                }
                $table[$number] = $cells;
            }
            if (!isset($table[1])) {
                throw new RuntimeException('safir_xlsx_header_missing');
            }
            $aliases = [
                ['نام', 'first_name'], ['نام خانوادگی', 'last_name'],
                ['نام شرکت', 'company'], ['شماره همراه مدیرعامل', 'mobile'],
            ];
            $mapped = [];
            foreach ($table[1] as $index => $value) {
                $header = preg_replace('/\s+/u', ' ', trim($this->normalizeText($value)));
                foreach ($aliases as $col => $labels) {
                    if ($header === $labels[0] || $header === $labels[1]) {
                        $mapped[$col] = $index;
                    }
                }
            }
            if (count($mapped) !== 4) {
                throw new RuntimeException('safir_xlsx_header_missing');
            }
            $output = [];
            foreach ($table as $number => $cells) {
                if ($number === 1) {
                    continue;
                }
                $values = [];
                foreach (range(0, 3) as $i) {
                    $values[] = trim((string) ($cells[$mapped[$i]] ?? ''));
                }
                if (implode('', $values) !== '') {
                    $output[] = $values;
                }
            }
            if ($output === []) {
                throw new RuntimeException('safir_xlsx_empty');
            }
            return $output;
        } finally {
            $zip->close();
        }
    }

    private function normalizeText(string $text): string
    {
        return strtr($text, ['ي' => 'ی', 'ك' => 'ک', '‌' => ' ', "\u{FEFF}" => '']);
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = strtr($phone, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5',
            '۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2',
            '٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
        $phone = preg_replace('/[\s()+-]/u', '', trim($phone)) ?? '';
        if (preg_match('/^09[0-9]{9}$/D', $phone)) {
            return '98' . substr($phone, 1);
        }
        if (preg_match('/^989[0-9]{9}$/D', $phone)) {
            return $phone;
        }
        // Excel may store 0912... as a number and drop the leading zero.
        if (preg_match('/^9[0-9]{9}$/D', $phone)) {
            return '98' . $phone;
        }
        if (preg_match('/^00989[0-9]{9}$/D', $phone)) {
            return substr($phone, 2);
        }
        return null;
    }

    private function maskPhone(string $phone): string
    {
        $value = $this->normalizePhone($phone);
        return $value ? '98*****' . substr($value, -4) : 'تنظیم نشده';
    }
}
