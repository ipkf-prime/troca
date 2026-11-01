<?php
declare(strict_types=1);

/**
 * Offline tests only. NEVER calls the Safir API or mutates a database.
 * Run: php tests/bale-safir-campaign-smoke.php
 */
define('BASE_PATH', dirname(__DIR__) . '/public_html');
require BASE_PATH . '/system/Support/Env.php';
require BASE_PATH . '/system/Support/Session.php';
require BASE_PATH . '/app/Services/BaleSafirCampaignService.php';

use App\Services\BaleSafirCampaignService;

if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
    throw new RuntimeException('TEST_REQUIRE_ZIP_SIMPLEXML');
}

$service = new BaleSafirCampaignService();
$reflect = new ReflectionClass($service);
$phoneMethod = $reflect->getMethod('normalizePhone');
$cases = [
    ['09123333170', '989123333170'],
    ['989123333170', '989123333170'],
    ['9123333170', '989123333170'],
    ['00989123333170', '989123333170'],
    ['۰۹۱۲۳۳۳۳۱۷۰', '989123333170'],
    ['0912INVALID', null],
    ['123', null],
];
foreach ($cases as [$input, $expected]) {
    $actual = $phoneMethod->invoke($service, $input);
    if ($actual !== $expected) {
        throw new RuntimeException('PHONE_NORMALIZATION_FAILED: ' . $input);
    }
}
echo "PHONE_NORMALIZATION=PASS\n";

$template = $service->settings();
if (($template['template_id'] ?? '') !== 'yHdC0D'
    || !str_contains($template['body'], '{{name}}')
    || !str_contains($template['body'], '{{sherkat}}')
) {
    throw new RuntimeException('TEMPLATE_CONTRACT_FAILED');
}
echo "TEMPLATE_CONTRACT=PASS\n";

$tmp = tempnam(sys_get_temp_dir(), 'safirtest');
if (!is_string($tmp)) {
    throw new RuntimeException('TEST_TMP_FAILED');
}
$shared = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<si><t>نام</t></si><si><t>نام خانوادگی</t></si><si><t>نام شرکت</t></si><si><t>شماره همراه مدیرعامل</t></si>'
    . '</sst>';
$sheet = '<?xml version="1.0" encoding="UTF-8"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
    . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
    . '<row r="2"><c r="A2" t="inlineStr"><is><t>علی</t></is></c><c r="B2" t="inlineStr"><is><t>محمدی</t></is></c>'
    . '<c r="C2" t="inlineStr"><is><t>شرکت نمونه</t></is></c><c r="D2" t="inlineStr"><is><t>09123333170</t></is></c></row>'
    . '</sheetData></worksheet>';
try {
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('TEST_ZIP_FAILED');
    }
    $zip->addFromString('xl/sharedStrings.xml', $shared);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $method = $reflect->getMethod('parseWorkbook');
    $rows = $method->invoke($service, $tmp);
    if (count($rows) !== 1 || $rows[0] !== ['علی','محمدی','شرکت نمونه','09123333170']) {
        throw new RuntimeException('XLSX_PARSE_FAILED: ' . json_encode($rows, JSON_UNESCAPED_UNICODE));
    }
    echo "XLSX_PARSE=PASS\n";

    $badSheet = str_replace('<c r="A2" t="inlineStr">', '<c r="A2" t="inlineStr"><f>1+1</f>', $sheet);
    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        throw new RuntimeException('TEST_ZIP_REOPEN_FAILED');
    }
    $zip->addFromString('xl/worksheets/sheet1.xml', $badSheet);
    $zip->close();
    try {
        $method->invoke($service, $tmp);
        throw new RuntimeException('FORMULA_ALLOWED_UNEXPECTEDLY');
    } catch (ReflectionException $e) {
        throw $e;
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'safir_xlsx_formulas_forbidden') {
            throw $e;
        }
    }
    echo "FORMULA_REJECTION=PASS\n";
} finally {
    @unlink($tmp);
}

echo "BALE_SAFIR_OFFLINE_SMOKE=PASS\n";
