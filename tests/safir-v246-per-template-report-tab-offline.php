<?php
declare(strict_types=1);

/**
 * SAFIR v2.4.6 scoped report tab: isolated, no network or real DB.
 *
 * Usage: php tests/safir-v246-per-template-report-tab-offline.php /path/to/release
 */
namespace IPKF\Support {
    final class Env
    {
        public static array $testValues = [];
        public static function get(string $key, mixed $default = null): mixed
        {
            return self::$testValues[$key] ?? $default;
        }
    }
}
namespace {
    use App\Services\BaleSafirTestReportService;
    use IPKF\Support\Env;

    function verify(bool $pass, string $name): void
    {
        if (!$pass) {
            throw new \RuntimeException('FAIL=' . $name);
        }
        echo $name . "=PASS\n";
    }

    $baseArg = (string) ($argv[1] ?? dirname(__DIR__));
    $root = rtrim($baseArg, '/');
    if (is_file($root . '/public_html/app/Services/BaleSafirTestReportService.php')) {
        $root .= '/public_html';
    }
    verify(is_file($root . '/app/Services/BaleSafirTestReportService.php'), 'REPORT_SERVICE_PRESENT');
    require $root . '/app/Services/BaleSafirTestReportService.php';

    $tmp = sys_get_temp_dir() . '/safir-v246-report-' . bin2hex(random_bytes(8));
    if (!mkdir($tmp, 0700) || !mkdir($tmp . '/attempts', 0700)) {
        throw new \RuntimeException('FAIL=TEST_TEMP_SETUP');
    }
    $cleanup = static function () use ($tmp): void {
        foreach (glob($tmp . '/attempts/*.json') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($tmp . '/attempts');
        @rmdir($tmp);
    };

    try {
        Env::$testValues = [
            'BALE_SAFIR_CATALOG_PATH' => $tmp . '/catalog.json',
            'BALE_SAFIR_TEST_ATTEMPT_DIR' => $tmp . '/attempts',
        ];
        $samples = [
            ['invite', '2026-11-01T09:00:00+00:00', '98*****1234'],
            ['unrelated_template', '2026-11-03T10:00:00+00:00', '98*****5678'],
            ['invite', '2026-11-02T11:00:00+00:00', '98*****1234'],
            ['unrelated_template', '2026-11-03T11:00:00+00:00', '98*****5678'],
        ];
        foreach ($samples as $index => [$code, $time, $masked]) {
            $record = [
                'template_code' => $code,
                'attempted_at' => $time,
                'actor' => 7,
                'recipient_masked' => $masked,
                'request_id' => 'test-' . $index,
                'status' => 'sent',
                'http_code' => 200,
                'message_id' => 'id-' . $index,
                'error_code' => 0,
                'error_description' => '',
            ];
            file_put_contents(
                $tmp . '/attempts/' . hash('sha256', (string) $index) . '.json',
                json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            );
        }

        $service = new BaleSafirTestReportService();
        $invite = $service->recent(10, 'invite');
        verify(count($invite) === 2, 'HISTORY_FILTER_PER_TEMPLATE');
        verify(
            count(array_filter($invite, static fn (array $row): bool => $row['template'] !== 'invite')) === 0,
            'NO_OTHER_TEMPLATE_HISTORY'
        );
        verify(
            $invite[0]['time'] === '2026-11-02T11:00:00+00:00',
            'FILTER_BEFORE_SORT_AND_LIMIT'
        );
        verify(count($service->recent(1, 'invite')) === 1, 'TEMPLATE_LIMIT');
        verify(count($service->recent(10, 'not_saved')) === 0, 'EMPTY_HISTORY_VALID');

        $csv = $service->csv('invite');
        verify(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV_EXCEL_BOM');
        verify(str_contains($csv, 'invite'), 'CSV_CONTAINS_SELECTED_TEMPLATE');
        verify(!str_contains($csv, 'unrelated_template') && !str_contains($csv, '98*****5678'),
            'CSV_EXCLUDES_OTHER_TEMPLATE');

        $view = file_get_contents($root . '/resources/views/admin/partials/bale-safir-campaign.php');
        $route = file_get_contents($root . '/routes/bale-safir-campaigns.php');
        $campaignService = file_get_contents($root . '/app/Services/BaleSafirCampaignService.php');
        verify(is_string($view) && is_string($route) && is_string($campaignService), 'SCOPED_SOURCE_PRESENT');
        verify(!str_contains($view, '<h3>گزارش تست‌های ارسال سفیر</h3>'), 'GLOBAL_REPORT_CARD_REMOVED');
        verify(str_contains($view, 'data-safir-tab="report"')
            && str_contains($view, 'data-safir-panel="report"')
            && str_contains($view, 'if ($mode === \'edit\')'),
            'PER_TEMPLATE_REPORT_TAB_PRESENT');
        verify(str_contains($view, 'test-report.csv?template_code='), 'SCOPED_CSV_LINK');
        verify(str_contains($view, 'data-safir-save-button')
            && str_contains($view, "saveButton.hidden = name === 'report'"),
            'REPORT_TAB_READ_ONLY_UI');
        verify(str_contains($route, '->csv($templateCode)')
            && str_contains($route, 'Invalid template reference')
            && str_contains($route, "'notifications.safir.manage'"),
            'EXISTING_RBAC_AND_TEMPLATE_FILTER_PRESERVED');
        verify(str_contains($campaignService, 'recent(10, $editingCode)'),
            'ONLY_LOAD_EDITED_TEMPLATE_HISTORY');
        echo "SAFIR_V246_SCOPED_REPORT_TAB_OFFLINE=PASS\n";
        echo "DATABASE_MUTATION=NO\nPROVIDER_SEND=NO\n";
    } finally {
        $cleanup();
    }
}
