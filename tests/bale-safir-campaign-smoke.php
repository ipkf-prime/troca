<?php
declare(strict_types=1);

/**
 * Strictly offline Safir tests; no provider API calls and no database mutation.
 * Run: php tests/bale-safir-campaign-smoke.php
 */
define('BASE_PATH', dirname(__DIR__) . '/public_html');
require BASE_PATH . '/system/Support/Env.php';
require BASE_PATH . '/system/Support/Session.php';
require BASE_PATH . '/system/Http/Response.php';
require BASE_PATH . '/app/Services/BaleSafirTemplateCatalogService.php';
require BASE_PATH . '/app/Services/BaleSafirCampaignService.php';
require BASE_PATH . '/app/Services/BaleSafirTestReportService.php';

use App\Services\BaleSafirCampaignService;
use App\Services\BaleSafirTemplateCatalogService;

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
    if ($phoneMethod->invoke($service, $input) !== $expected) {
        throw new RuntimeException('PHONE_NORMALIZATION_FAILED: ' . $input);
    }
}
$exportRoute = (string) file_get_contents(BASE_PATH . '/routes/bale-safir-campaigns.php');
if (str_contains($exportRoute, '->body(')) {
    throw new RuntimeException('ROUTE_USES_UNSUPPORTED_RESPONSE_METHOD');
}
$response = (new \IPKF\Http\Response())
    ->status(200)
    ->header('Content-Type', 'text/csv; charset=UTF-8')
    ->send('test,csv');
if ($response->statusCode() !== 200) {
    throw new RuntimeException('CSV_RESPONSE_COMPATIBILITY_FAILED');
}
echo "CSV_RESPONSE_COMPATIBILITY=PASS\n";

echo "PHONE_NORMALIZATION=PASS\n";

// The new manual-test path MUST fail closed in Production by default.
// This test does not initialize API clients and never sends an HTTP request.
$_ENV['APP_ENV'] = 'production';
$_ENV['BALE_SAFIR_TEST_SEND_ENABLED'] = 'false';
$_ENV['BALE_SAFIR_TEST_PRODUCTION_APPROVED'] = 'false';
$manualBlocked = false;
try {
    $service->manualTest(
        101, 'invite', '09123333170',
        ['name' => 'تست', 'sherkat' => 'شرکت نمونه'],
        str_repeat('a', 32)
    );
} catch (RuntimeException $exception) {
    $manualBlocked = $exception->getMessage() === 'safir_manual_test_disabled';
}
if (!$manualBlocked) {
    throw new RuntimeException('MANUAL_TEST_SEND_NOT_FAIL_CLOSED');
}
echo "MANUAL_TEST_PRODUCTION_FAIL_CLOSED=PASS\n";

$validator = $reflect->getMethod('validateManualFields');
$normalized = $validator->invoke(
    $service,
    ['mappings' => ['name' => ['نام'], 'sherkat' => ['نام شرکت']]],
    ['sherkat' => ' شركت آزمایشی ', 'name' => ' علي ']
);
if (array_keys($normalized) !== ['name', 'sherkat']
    || $normalized['name'] !== 'علی'
    || $normalized['sherkat'] !== 'شرکت آزمایشی') {
    throw new RuntimeException('MANUAL_TEST_FIELD_NORMALIZATION_FAILED');
}
foreach ([
    ['name' => 'فقط نام'],
    ['name' => 'نام', 'sherkat' => 'شرکت', 'extra' => 'اضافی'],
] as $invalidFields) {
    try {
        $validator->invoke($service, ['mappings' => ['name' => ['نام'], 'sherkat' => ['شرکت']]], $invalidFields);
        throw new RuntimeException('MANUAL_TEST_EXTRA_OR_MISSING_FIELD_ACCEPTED');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'safir_template_variables_invalid') {
            throw $exception;
        }
    }
}
echo "MANUAL_TEST_VARIABLE_VALIDATION=PASS\n";


$catalog = new BaleSafirTemplateCatalogService();
$invite = $catalog->get('invite');
if (!is_array($invite) || ($invite['template_id'] ?? '') !== 'yHdC0D'
    || BaleSafirTemplateCatalogService::placeholders((string) $invite['body']) !== ['name','sherkat']
    || $invite['mappings']['name'] !== ['نام', 'نام خانوادگی']
) {
    throw new RuntimeException('CATALOG_SEED_INVALID');
}
if (BaleSafirTemplateCatalogService::placeholders('کد {{شماره_پیگیری}} / {{serial}}') !== ['شماره_پیگیری', 'serial']) {
    throw new RuntimeException('UNICODE_VARIABLE_PARSE_FAILED');
}
echo "UNICODE_VARIABLE_PARSE=PASS\n";
echo "CATALOG_SEED=PASS\n";

$tmpDirectory = sys_get_temp_dir() . '/ipkf_safir_' . bin2hex(random_bytes(6));
if (!mkdir($tmpDirectory, 0700)) {
    throw new RuntimeException('TEST_TMP_FAILED');
}
$_ENV['BALE_SAFIR_CATALOG_PATH'] = $tmpDirectory . '/catalog.json';
$_ENV['BALE_SAFIR_MAX_TEMPLATES'] = '20';
$workbook = $tmpDirectory . '/recipients.xlsx';

try {
    $created = $catalog->save([
        'code' => 'payment_reminder',
        'title' => 'یادآوری پرداخت',
        'bot_id' => '1903835978',
        'template_id' => 'abcXYZ',
        'phone_column' => 'شماره تماس',
        'body' => 'سلام، شناسه {{tracking_code}} و مبلغ {{amount}}',
        'mappings' => [
            'tracking_code' => 'شناسه',
            'amount' => 'مبلغ',
        ],
        'provider_approved' => '1',
    ]);
    if ($created['mappings'] !== ['tracking_code' => ['شناسه'], 'amount' => ['مبلغ']]
        || count($catalog->all()) !== 2
        || ($catalog->get('invite')['template_id'] ?? null) !== 'yHdC0D'
    ) {
        throw new RuntimeException('DYNAMIC_CATALOG_SAVE_FAILED');
    }
    $catalog->setActive('payment_reminder', false);
    if (($catalog->get('payment_reminder')['active'] ?? true) !== false) {
        throw new RuntimeException('TEMPLATE_DEACTIVATION_FAILED');
    }
    $catalog->setActive('payment_reminder', true);
    echo "DYNAMIC_CATALOG_SAVE_EDIT_STATUS=PASS\n";

    // New row-driven editor contract: editable label, English key, Excel column.
    $one = $catalog->save([
        'code' => 'dynamic_one',
        'title' => 'یک متغیر',
        'bot_id' => '1903835978',
        'template_id' => 'newOne',
        'phone_column' => 'شماره همراه',
        'body' => 'سلام {{name}}',
        'variable_items' => [
            ['key_en' => 'name', 'label_fa' => 'نام و نام خانوادگی', 'excel_column' => 'نام + نام خانوادگی'],
        ],
    ]);
    if (($one['mappings']['name'] ?? null) !== ['نام', 'نام خانوادگی']
        || ($one['variable_labels']['name'] ?? null) !== 'نام و نام خانوادگی') {
        throw new RuntimeException('ONE_VARIABLE_EDITOR_CONTRACT_FAILED');
    }
    echo "ONE_VARIABLE_EDITOR=PASS\n";

    $hundredRows = [];
    $hundredTags = [];
    for ($i = 1; $i <= 100; $i++) {
        $key = 'v' . $i;
        $hundredRows[] = ['key_en' => $key, 'label_fa' => 'فیلد ' . $i, 'excel_column' => 'ستون ' . $i];
        $hundredTags[] = '{{' . $key . '}}';
    }
    $hundred = $catalog->save([
        'code' => 'dynamic_hundred',
        'title' => 'صد متغیر',
        'bot_id' => '1903835978',
        'template_id' => 'newHundred',
        'phone_column' => 'شماره همراه',
        'body' => implode(' ', $hundredTags),
        'variable_items' => $hundredRows,
    ]);
    if (count($hundred['mappings'] ?? []) !== 100
        || count($hundred['variable_labels'] ?? []) !== 100) {
        throw new RuntimeException('HUNDRED_VARIABLE_EDITOR_FAILED');
    }
    echo "HUNDRED_VARIABLE_EDITOR=PASS\n";

    foreach ([
        'duplicate' => [
            ['key_en' => 'name', 'label_fa' => 'نام', 'excel_column' => 'نام'],
            ['key_en' => 'name', 'label_fa' => 'نام دوم', 'excel_column' => 'نام خانوادگی'],
        ],
        'mismatch' => [
            ['key_en' => 'wrong', 'label_fa' => 'کلید اشتباه', 'excel_column' => 'نام'],
        ],
    ] as $case => $variableRows) {
        try {
            $catalog->save([
                'code' => 'invalid_' . $case,
                'title' => 'غیرمجاز',
                'bot_id' => '1903835978',
                'template_id' => 'badTemplate',
                'phone_column' => 'شماره همراه',
                'body' => 'سلام {{name}}',
                'variable_items' => $variableRows,
            ]);
            throw new RuntimeException('INVALID_VARIABLE_UNEXPECTED_PASS:' . $case);
        } catch (RuntimeException $e) {
            $expected = $case === 'duplicate'
                ? 'safir_template_mapping_invalid'
                : 'safir_template_variables_mismatch';
            if ($e->getMessage() !== $expected) {
                throw $e;
            }
        }
    }
    echo "VARIABLE_DUPLICATE_AND_MISMATCH_REJECTION=PASS\n";


    $payloadMethod = $reflect->getMethod('payload');
    $payload = $payloadMethod->invoke($service, $catalog->get('payment_reminder'), [
        'fields' => ['tracking_code' => 'TR-100', 'amount' => '450000'],
    ], '989123333170', 'test-idempotency-123456');
    $fields = $payload['message_data']['template_message']['text_fields'];
    if ($payload['request_id'] !== 'test-idempotency-123456'
        || $payload['phone_number'] !== '989123333170'
        || $payload['message_data']['template_message']['template_id'] !== 'abcXYZ'
        || (array) $fields !== ['tracking_code' => 'TR-100', 'amount' => '450000']
        || array_key_exists('name', (array) $fields)
    ) {
        throw new RuntimeException('DYNAMIC_PROVIDER_PAYLOAD_FAILED');
    }
    echo "DYNAMIC_PROVIDER_PAYLOAD=PASS\n";

    $shared = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<si><t>نام</t></si><si><t>نام خانوادگی</t></si><si><t>نام شرکت</t></si><si><t>شماره همراه مدیرعامل</t></si>'
        . '</sst>';
    $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
        . '<row r="2"><c r="A2" t="inlineStr"><is><t>علی</t></is></c><c r="B2" t="inlineStr"><is><t>محمدی</t></is></c>'
        . '<c r="C2" t="inlineStr"><is><t>شرکت نمونه</t></is></c><c r="D2" t="inlineStr"><is><t>09123333170</t></is></c></row>'
        . '</sheetData></worksheet>';
    $zip = new ZipArchive();
    if ($zip->open($workbook, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('TEST_ZIP_FAILED');
    }
    $zip->addFromString('xl/sharedStrings.xml', $shared);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $parse = $reflect->getMethod('parseWorkbook');
    $rows = $parse->invoke($service, $workbook);
    if (count($rows) !== 1 || ($rows[0]['row'] ?? null) !== 2
        || ($rows[0]['columns']['نام شرکت'] ?? '') !== 'شرکت نمونه'
        || ($rows[0]['columns']['شماره همراه مدیرعامل'] ?? '') !== '09123333170'
    ) {
        throw new RuntimeException('XLSX_DYNAMIC_HEADER_PARSE_FAILED: ' . json_encode($rows, JSON_UNESCAPED_UNICODE));
    }
    echo "XLSX_DYNAMIC_HEADER_PARSE=PASS\n";
    $extra = '';
    for ($i = 3; $i <= 3001; $i++) {
        $extra .= '<row r="' . $i . '"><c r="A' . $i . '" t="inlineStr"><is><t>تست</t></is></c></row>';
    }
    $largeSheet = str_replace('</sheetData>', $extra . '</sheetData>', $sheet);
    $zip = new ZipArchive();
    $zip->open($workbook);
    $zip->addFromString('xl/worksheets/sheet1.xml', $largeSheet);
    $zip->close();
    $many = $parse->invoke($service, $workbook);
    if (count($many) !== 3000) {
        throw new RuntimeException('XLSX_3000_ROW_CAPACITY_FAILED');
    }
    echo "XLSX_3000_ROW_CAPACITY=PASS\n";


    $duplicateSheet = str_replace('<v>1</v></c>', '<v>0</v></c>', $sheet);
    $zip = new ZipArchive();
    $zip->open($workbook);
    $zip->addFromString('xl/worksheets/sheet1.xml', $duplicateSheet);
    $zip->close();
    try {
        $parse->invoke($service, $workbook);
        throw new RuntimeException('DUPLICATE_HEADER_UNEXPECTED_PASS');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'safir_xlsx_duplicate_header') {
            throw $e;
        }
    }
    echo "DUPLICATE_HEADER_REJECT=PASS\n";

    $badSheet = str_replace('<c r="A2" t="inlineStr">', '<c r="A2" t="inlineStr"><f>1+1</f>', $sheet);
    $zip = new ZipArchive();
    $zip->open($workbook);
    $zip->addFromString('xl/worksheets/sheet1.xml', $badSheet);
    $zip->close();
    try {
        $parse->invoke($service, $workbook);
        throw new RuntimeException('FORMULA_UNEXPECTED_PASS');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'safir_xlsx_formulas_forbidden') {
            throw $e;
        }
    }
    echo "FORMULA_REJECTION=PASS\n";

    // Private persistent manual test audit report, never containing cleartext
    // recipients or credentials; Excel formula injection is neutralized.
    $attemptDir = $tmpDirectory . '/test-attempts';
    if (!mkdir($attemptDir, 0700)) {
        throw new RuntimeException('REPORT_FIXTURE_DIRECTORY_FAILED');
    }
    $_ENV['BALE_SAFIR_TEST_ATTEMPT_DIR'] = $attemptDir;
    $fixture = [
        'actor' => 100,
        'template_code' => 'invite',
        'recipient_masked' => '98*****3170',
        'request_id' => 'manual-test-0123456789abcdef0123456789abcdef',
        'attempted_at' => gmdate('c'),
        'status' => 'failed',
        'http_code' => 429,
        'message_id' => '',
        'error_code' => 3,
        'error_description' => '=cmd|/C calc',
    ];
    file_put_contents($attemptDir . '/' . str_repeat('a', 64) . '.json',
        json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    $reports = new \App\Services\BaleSafirTestReportService();
    $recent = $reports->recent(10);
    $csv = $reports->csv();
    if (count($recent) !== 1 || $recent[0]['error_code'] !== 3
        || !str_contains($csv, "'=cmd|/C calc")
        || str_contains($csv, 'api-access-key')
        || !str_contains($csv, '98*****3170')) {
        throw new RuntimeException('SAFIR_PERSISTENT_REPORT_CONTRACT_FAILED');
    }
    echo "SAFIR_PERSISTENT_REPORT_CSV=PASS\n";
    unlink($attemptDir . '/' . str_repeat('a', 64) . '.json');
    rmdir($attemptDir);

} finally {
    @unlink($workbook);
    @unlink($tmpDirectory . '/catalog.json');
    @unlink($tmpDirectory . '/.bale_safir_templates.lock');
    @rmdir($tmpDirectory);
}

echo "BALE_SAFIR_DYNAMIC_OFFLINE_SMOKE=PASS\n";
