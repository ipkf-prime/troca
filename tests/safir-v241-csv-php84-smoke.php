<?php
declare(strict_types=1);

/**
 * Safir v2.4.1 CSV regression under strict PHP error promotion.
 * Synthetic test data only; no database, HTTP, production credentials or message send.
 */
namespace IPKF\Support {
    final class Env
    {
        public static array $values = [];
        public static function get(string $key, mixed $default = null): mixed
        {
            return self::$values[$key] ?? $default;
        }
    }
}

namespace {
    use App\Services\BaleSafirTestReportService;
    use IPKF\Support\Env;

    require dirname(__DIR__) . '/public_html/app/Services/BaleSafirTestReportService.php';

    if (PHP_VERSION_ID < 80400) {
        fwrite(STDERR, "FAIL=PHP84_REQUIRED\n");
        exit(2);
    }

    $root = sys_get_temp_dir() . '/ipkf-safir-v241-' . bin2hex(random_bytes(8));
    $attempts = $root . '/attempts';
    $created = false;
    try {
        if (!mkdir($root, 0700) || !mkdir($attempts, 0700)) {
            throw new \RuntimeException('FAIL=TEMP_CREATE');
        }
        $created = true;
        file_put_contents($root . '/templates.json', "{}\n");
        Env::$values = [
            'BALE_SAFIR_CATALOG_PATH' => $root . '/templates.json',
            'BALE_SAFIR_TEST_ATTEMPT_DIR' => $attempts,
        ];
        $record = [
            'attempted_at' => '2026-11-02T00:00:00+00:00',
            'actor' => 7,
            'template_code' => 'invite',
            'recipient_masked' => '98*****1234',
            'request_id' => '=2+2',
            'status' => 'sent',
            'http_code' => 200,
            'message_id' => '@external',
            'error_code' => 0,
            'error_description' => 'synthetic-test',
        ];
        file_put_contents(
            $attempts . '/' . str_repeat('a', 64) . '.json',
            json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        error_reporting(E_ALL);
        set_error_handler(static function (
            int $severity,
            string $message,
            string $file,
            int $line
        ): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        $csv = (new BaleSafirTestReportService())->csv();
        if (!str_starts_with($csv, "\xEF\xBB\xBF")) {
            throw new \RuntimeException('FAIL=CSV_BOM');
        }
        $lines = explode("\n", trim(substr($csv, 3)));
        if (count($lines) !== 2) {
            throw new \RuntimeException('FAIL=CSV_LINE_COUNT');
        }
        $header = str_getcsv($lines[0], ',', '"', '');
        $values = str_getcsv($lines[1], ',', '"', '');
        if (count($header) !== 10 || count($values) !== 10
            || $values[2] !== 'invite'
            || $values[3] !== '98*****1234'
            || $values[4] !== "'=2+2"
            || $values[7] !== "'@external"
            || $values[5] !== 'sent') {
            throw new \RuntimeException('FAIL=CSV_COLUMNS_OR_ESCAPE');
        }
        echo "PHP84_STRICT_CSV_GENERATION=PASS\n";
        echo "CSV_10_COLUMNS_UTF8_BOM=PASS\n";
        echo "CSV_FORMULA_INJECTION_PROTECTION=PASS\n";
        echo "CSV_SYNTHETIC_RECORDS_ONLY=YES\n";
        echo "NO_HTTP_NO_DB_NO_SEND=PASS\n";
        echo "SAFIR_V241_CSV_REGRESSION=PASS\n";
    } finally {
        restore_error_handler();
        if ($created) {
            @unlink($attempts . '/' . str_repeat('a', 64) . '.json');
            @unlink($root . '/templates.json');
            @rmdir($attempts);
            @rmdir($root);
        }
    }
}
