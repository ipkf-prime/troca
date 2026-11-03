<?php
declare(strict_types=1);

/**
 * SAFIR v2.4.3: offline per-template XLSX scaffold regression.
 * No DB, HTTP, environment mutation, provider transmission or real recipients.
 */
use App\Services\BaleSafirExcelSkeletonService;

$parent = dirname(__DIR__);
$root = is_file($parent . '/public_html/app/Services/BaleSafirExcelSkeletonService.php')
    ? $parent . '/public_html/'
    : $parent . '/app/';
require_once $root . 'app/Services/BaleSafirExcelSkeletonService.php';

function assertSafir(bool $condition, string $code): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL=' . $code);
    }
    echo $code . "=PASS\n";
}

$service = new BaleSafirExcelSkeletonService();
$sampleA = [
    'phone_column' => 'شماره همراه',
    'mappings' => [
        'name' => ['نام', 'نام خانوادگی'],
        'sherkat' => ['نام شرکت'],
    ],
];
$expectedA = ['شماره همراه', 'نام', 'نام خانوادگی', 'نام شرکت'];
assertSafir($service->headers($sampleA) === $expectedA, 'HEADER_ORDER_AND_COMPOSITE_MAPPING');

$sampleB = [
    'phone_column' => 'mobile',
    'mappings' => [
        'name' => ['نام و نام خانوادگی'],
        'company' => ['نام شرکت', 'شهرستان'],
        'reused' => ['نام شرکت'],
    ],
];
assertSafir(
    $service->headers($sampleB) === ['mobile', 'نام و نام خانوادگی', 'نام شرکت', 'شهرستان'],
    'DYNAMIC_CATALOG_AND_DEDUPLICATION'
);

assertSafir(
    $service->headers([
        'phone_column' => 'شماره همراه',
        'mappings' => ['name' => ["نام\t  و   نام  ", " خانوادگی "]],
    ]) === ['شماره همراه', 'نام و نام', 'خانوادگی'],
    'NORMALIZE_IMPORT_HEADER_WHITESPACE'
);

foreach ([
    ['phone_column' => '', 'mappings' => ['x' => ['نام']]],
    ['phone_column' => 'موبایل', 'mappings' => ['x' => []]],
    ['phone_column' => 'موبایل', 'mappings' => ['x' => [100]]],
    ['phone_column' => 'موبایل', 'mappings' => ['x' => array_fill(0, 9, 'نام')]],
] as $i => $broken) {
    $failed = false;
    try {
        $service->headers($broken);
    } catch (RuntimeException) {
        $failed = true;
    }
    assertSafir($failed, 'REJECT_INVALID_MAPPING_' . ($i + 1));
}

if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
    echo "RUNTIME_ZIP_AND_XML=NOT_AVAILABLE_ON_THIS_HOST\n";
    exit(0);
}

$blob = $service->build($sampleA);
assertSafir(str_starts_with($blob, "PK\x03\x04"), 'XLSX_ZIP_SIGNATURE');
assertSafir(strlen($blob) < 200000, 'XLSX_BOUNDED_OUTPUT');

$temp = tempnam(sys_get_temp_dir(), 'safir_xlsx_reg_');
if (!is_string($temp)) {
    throw new RuntimeException('FAIL=TEMP_UNAVAILABLE');
}
try {
    file_put_contents($temp, $blob);
    $zip = new ZipArchive();
    assertSafir($zip->open($temp) === true, 'XLSX_VALID_ZIP');
    try {
        $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        assertSafir(is_string($worksheet) && is_string($styles), 'XLSX_REQUIRED_PARTS');
        $xml = simplexml_load_string($worksheet, SimpleXMLElement::class, LIBXML_NONET);
        assertSafir($xml instanceof SimpleXMLElement, 'XLSX_VALID_WORKSHEET_XML');
        $values = $xml->xpath('//*[local-name()="row" and @r="1"]/*[local-name()="c"]/*[local-name()="is"]/*[local-name()="t"]');
        assertSafir(
            is_array($values) && array_map('strval', $values) === $expectedA,
            'XLSX_ROW1_EXACT_IMPORTABLE_HEADERS'
        );
        $rows = $xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');
        assertSafir(is_array($rows) && count($rows) === 1, 'XLSX_NO_RECIPIENT_DATA');
        assertSafir(str_contains($worksheet, 'rightToLeft="1"'), 'XLSX_RTL_SHEET');
        assertSafir(str_contains($styles, 'numFmtId="49"'), 'XLSX_TEXT_FORMAT_PRESERVES_PHONE_ZERO');
        assertSafir(str_contains($worksheet, 'A1:D1'), 'XLSX_HEADER_FILTER');
    } finally {
        $zip->close();
    }
} finally {
    @unlink($temp);
}

$rbac = file_get_contents($root . 'app/Services/AdminNavigationRbacService.php');
$routes = file_get_contents($root . 'routes/bale-safir-campaigns.php');
$view = file_get_contents($root . 'resources/views/admin/partials/bale-safir-campaign.php');
assertSafir(is_string($rbac) && substr_count($rbac, "'/admin/communications/safir/template.xlsx'") >= 2, 'RBAC_SCOPED_ROUTE_DECLARED');
assertSafir(is_string($routes)
    && str_contains($routes, "adminGuard(\$response, '/admin/communications/safir/template.xlsx')")
    && str_contains($routes, "'notifications.safir.manage'")
    && str_contains($routes, "'notifications.send.manage'"),
    'DOWNLOAD_ROUTE_HAS_AUTHORIZATION');
assertSafir(is_string($view)
    && str_contains($view, 'data-safir-template-select')
    && str_contains($view, 'data-safir-skeleton-link')
    && str_contains($view, 'دانلود اکسل خام'),
    'TEMPLATE_LIST_AND_IMPORT_UI_BOUND');
echo "SAFIR_V243_DYNAMIC_XLSX_OFFLINE=PASS\n";
