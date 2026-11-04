<?php
declare(strict_types=1);

/** R73A source-only tests; no DB, no network or provider credentials. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $text, ?string $encoding = null): int {
        return preg_match_all('/./us', $text) ?: 0;
    }
}
$root = dirname(__DIR__, 2) . '/public_html/app/Services/';
$policyPath = is_file($root . 'BaleSafirDurableCampaignPolicy.php')
    ? $root . 'BaleSafirDurableCampaignPolicy.php'
    : __DIR__ . '/BaleSafirDurableCampaignPolicy.php';
$readerPath = is_file($root . 'BaleSafirDurableCsvChunkReader.php')
    ? $root . 'BaleSafirDurableCsvChunkReader.php'
    : __DIR__ . '/BaleSafirDurableCsvChunkReader.php';
require_once $policyPath;
require_once $readerPath;

use App\Services\BaleSafirDurableCampaignPolicy as Policy;
use App\Services\BaleSafirDurableCsvChunkReader as Reader;

$passed = 0;
function okay(bool $test, string $name): void
{
    global $passed;
    if (!$test) {
        fwrite(STDERR, 'R73A_TEST_FAIL=' . $name . "\n"); exit(1);
    }
    $passed++;
}
function rejectCsv(callable $test, string $name): void
{
    global $passed;
    try {
        $test();
    } catch (InvalidArgumentException) {
        $passed++; return;
    }
    fwrite(STDERR, 'R73A_TEST_FAIL=' . $name . "\n"); exit(1);
}
function parseCsv(string $data, array $template, int $size = 500, string $delimiter = ','): array
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) {
        throw new RuntimeException('temp unavailable');
    }
    fwrite($stream, $data);
    rewind($stream);
    try {
        return iterator_to_array(Reader::chunks($stream, $template, $size, $delimiter), false);
    } finally {
        fclose($stream);
    }
}
$template = Policy::freezeApprovedTemplate([
    'code' => 'r73.dynamic',
    'body' => 'سلام {{person_name}}، {{unit_name}}',
    'phone_column' => 'موبایل',
    'mappings' => ['person_name' => ['نام'], 'unit_name' => ['واحد']],
    'bot_id' => '1234567',
    'template_id' => 'approved_mock_01',
    'active' => true, 'provider_approved' => true,
]);
$header = "\xEF\xBB\xBFموبایل,نام,واحد\r\n";
$body = "09123456789,\"علی, رضا\",مالی\r\n"
    . "۰۹۱۲۳۴۵۶۷۸۸,\"زهرا \"\"الف\"\"\",مدیریت\r\n"
    . "09121111111,کامران,\"روستایی\nاستانی\"\r\n"
    . "invalid,فرد,مالی\r\n"
    . "09127777777,قاسم,IT\r\n";
$original = parseCsv($header . $body, $template, 2);
okay(count($original) === 3, 'THREE_BOUNDED_CHUNKS');
okay(array_column($original, 'record_count') === [2,2,1], 'CHUNK_SIZES_2_2_1');
okay(array_column($original, 'first_row') === [2,4,6], 'LOGICAL_SOURCE_ROWS');
okay(array_column($original, 'last_row') === [3,5,6], 'LAST_ROWS');
okay($original[0]['rows'][0]['columns']['نام'] === 'علی, رضا', 'QUOTED_COMMA');
okay($original[0]['rows'][1]['columns']['نام'] === 'زهرا "الف"', 'ESCAPED_QUOTE');
okay(str_contains($original[1]['rows'][0]['columns']['واحد'], "\n"), 'QUOTED_MULTILINE');
okay($original[0]['rows'][0]['columns']['موبایل'] === '09123456789', 'HEADER_BOM_REMOVED');
okay(preg_match('/^[a-f0-9]{64}$/D', $original[0]['sha256']) === 1, 'SOURCE_SHA_FORMAT');
$repeat = parseCsv($header . $body, $template, 2);
okay(array_column($repeat, 'sha256') === array_column($original, 'sha256'), 'CHUNK_HASH_DETERMINISTIC');
$changed = parseCsv($header . str_replace('علی, رضا', 'علی، رضا', $body), $template, 2);
okay($changed[0]['sha256'] !== $original[0]['sha256'], 'SOURCE_BYTE_HASH_CHANGES');
okay($changed[1]['sha256'] === $original[1]['sha256'], 'UNCHANGED_CHUNK_HASH');
$plan = Policy::planChunk($template, $original[0]['rows']);
okay($plan['ready_count'] === 2, 'R72A_COMPATIBLE');
okay($plan['ready'][0]['phone_normalized'] === '989123456789', 'NORMALIZATION_COMPATIBLE');
okay(count(parseCsv("موبایل;نام;واحد\n09123456789;لیلا;ستاد\n",
    $template, 500, ';')) === 1, 'SEMICOLON_DELIMITER');
okay(parseCsv("موبایل,نام,واحد\n09123456789,علی,ستاد", $template)[0]['record_count'] === 1,
    'NO_TRAILING_NEWLINE');
okay(parseCsv("\nموبایل,نام,واحد\n\n09123456789,علی,ستاد\n", $template)[0]['record_count'] === 1,
    'SKIP_EMPTY_LINES');

rejectCsv(fn()=>parseCsv('',$template), 'MISSING_HEADER');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n",$template), 'HEADER_ONLY');
rejectCsv(fn()=>parseCsv("نام,واحد\nعلی,ستاد\n",$template), 'REQUIRED_COLUMN_MISSING');
rejectCsv(fn()=>parseCsv("موبایل,نام,نام\n09123456789,علی,رضا\n",$template), 'DUPLICATE_HEADER');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,علی\n",$template), 'WRONG_COLUMN_COUNT');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,\"علی,مالی\n",$template), 'UNTERMINATED_QUOTE');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,a\"b,مالی\n",$template), 'QUOTE_IN_UNQUOTED');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,\"علی\"x,مالی\n",$template), 'QUOTE_TRAILING_JUNK');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,علی,\x00مالی\n",$template), 'CONTROL_CHAR');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,\xFF,مالی\n",$template), 'INVALID_UTF8');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789," . str_repeat('X', 8193) . ",مالی\n",$template), 'FIELD_CAP');
$wideHeader = 'موبایل,نام,واحد,' . implode(',', array_map(static fn(int $i): string => 'c' . $i, range(1, 9))) . "\n";
$wideRow = '09123456789,علی,ستاد,' . implode(',', array_fill(0, 9, str_repeat('X', 8192))) . "\n";
rejectCsv(fn()=>parseCsv($wideHeader . $wideRow,$template), 'RECORD_BYTE_CAP');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,علی,مالی\n",$template, 501), 'CHUNK_CAP');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,علی,مالی\n",$template, 0), 'ZERO_CHUNK');
rejectCsv(fn()=>parseCsv("موبایل,نام,واحد\n09123456789,علی,مالی\n",$template, 2, '|'), 'INVALID_DELIMITER');
rejectCsv(fn()=>parseCsv("موبایل, نام,واحد\n09123456789,علی,مالی\n",$template), 'HEADER_TRIM_INVALID');

$many = "موبایل,نام,واحد\n";
for ($i=0; $i < 3000; $i++) {
    $many .= '09' . str_pad((string)$i, 9, '0', STR_PAD_LEFT) . ',کاربر,ستاد' . "\n";
}
$big = parseCsv($many, $template, 500);
okay(count($big) === 6, 'THREE_THOUSAND_ROWS_SIX_CHUNKS');
okay(array_sum(array_column($big,'record_count')) === 3000, 'THREE_THOUSAND_ROWS_UNLOST');
okay($big[5]['last_row'] === 3001, 'SOURCE_ROW_ACCURACY_3000');
okay(count(array_unique(array_column($big, 'sha256'))) === 6, 'CHUNK_HASHES_DISTINCT');
okay(max(array_column($big, 'record_count')) <= 500, 'CHUNK_BOUND');
echo 'R73A_CSV_STREAM_TESTS=' . $passed . "_PASS\n";
echo "THREE_THOUSAND_ROWS_CHUNKS=6\n";
echo "DATABASE_CONNECTION=NO\n";
echo "PROVIDER_HTTP=NO\n";
echo "PRODUCTION_MUTATION=NO\n";
