<?php
declare(strict_types=1);

/**
 * Strictly offline Safir tests; no provider API calls and no database mutation.
 * Run: php tests/bale-safir-campaign-smoke.php
 */
define('BASE_PATH', dirname(__DIR__) . '/public_html');
require BASE_PATH . '/system/Support/Env.php';
require BASE_PATH . '/system/Support/Session.php';
require BASE_PATH . '/app/Services/BaleSafirTemplateCatalogService.php';
require BASE_PATH . '/app/Services/BaleSafirCampaignService.php';

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
echo "PHONE_NORMALIZATION=PASS\n";

$catalog = new BaleSafirTemplateCatalogService();
$invite = $catalog->get('invite');
if (!is_array($invite) || ($invite['template_id'] ?? '') !== 'yHdC0D'
    || BaleSafirTemplateCatalogService::placeholders((string) $invite['body']) !== ['name','sherkat']
    || $invite['mappings']['name'] !== ['نام', 'نام خانوادگی']
) {
    throw new RuntimeException('CATALOG_SEED_INVALID');
}
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

    $payloadMethod = $reflect->getMethod('payload');
    $payload = $payloadMethod->invoke($service, $catalog->get('payment_reminder'), [
        'fields' => ['tracking_code' => 'TR-100', 'amount' => '450000'],
    ], '989123333170');
    $fields = $payload['message_data']['template_message']['text_fields'];
    if ($payload['message_data']['template_message']['template_id'] !== 'abcXYZ'
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
} finally {
    @unlink($workbook);
    @unlink($tmpDirectory . '/catalog.json');
    @unlink($tmpDirectory . '/.bale_safir_templates.lock');
    @rmdir($tmpDirectory);
}

echo "BALE_SAFIR_DYNAMIC_OFFLINE_SMOKE=PASS\n";
