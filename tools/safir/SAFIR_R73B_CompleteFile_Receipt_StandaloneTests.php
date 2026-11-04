<?php
declare(strict_types=1);

/** SAFIR R73B tests: source-only, no PDO, DB credentials or HTTP provider. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $text, ?string $encoding = null): int {
        return preg_match_all('/./us', $text) ?: 0;
    }
}
$root = __DIR__ . '/';
require_once $root . 'BaleSafirDurableCampaignPolicy.php';
require_once $root . 'BaleSafirDurableCsvChunkReader.php';
require_once $root . 'BaleSafirDurableCsvFileReceipt.php';

use App\Services\BaleSafirDurableCampaignPolicy as Policy;
use App\Services\BaleSafirDurableCsvFileReceipt as Receipt;

$checks = 0;
function expect(bool $yes, string $name): void {
    global $checks;
    if (!$yes) {fwrite(STDERR, "R73B_FAIL=$name\n"); exit(1);}
    $checks++;
}
function reject(callable $f, string $name): void {
    global $checks;
    try {$f();} catch (InvalidArgumentException $e) {$checks++; return;}
    fwrite(STDERR, "R73B_FAIL=$name\n"); exit(1);
}
function temporary(string $csv) {
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) throw new RuntimeException('TEST_TEMP_FAILED');
    fwrite($stream,$csv); rewind($stream); return $stream;
}
$template = Policy::freezeApprovedTemplate([
    'code' => 'r73b.operations',
    'body' => 'سلام {{person}} از {{unit}}',
    'phone_column' => 'موبایل',
    'mappings' => ['person' => ['نام'], 'unit' => ['واحد']],
    'bot_id' => '1234567', 'template_id' => 'approved_mock_01',
    'active' => true, 'provider_approved' => true,
]);
$csv = "\xEF\xBB\xBFموبایل,نام,واحد\r\n"
    . "09123456789,علی,ستاد\r\n"
    . "invalid,الف,ستاد\r\n"
    . "09121111111,,ستاد\r\n"
    . "۰۹۱۲۳۴۵۶۷۸۸,زهرا,امور\r\n";
$s = temporary($csv);
$r = Receipt::inspect($s,$template,2);
expect($r['version'] === 'safir_csv_file_receipt_v1', 'VERSION');
expect($r['source_sha256'] === hash('sha256',$csv),'WHOLE_FILE_SHA');
expect($r['source_bytes'] === strlen($csv),'WHOLE_FILE_SIZE');
expect($r['source_rows'] === 4,'ROW_COUNT');
expect($r['chunk_count'] === 2,'CHUNK_COUNT');
expect($r['chunk_size'] === 2,'CHUNK_SIZE');
expect($r['preliminary_ready_rows'] === 2,'READY_COUNT');
expect($r['preliminary_skipped_rows'] === 2,'SKIPPED_COUNT');
expect($r['template_sha256'] === $template['sha256'],'TEMPLATE_BINDING');
expect(preg_match('/^[a-f0-9]{64}$/D',$r['chunk_manifest_sha256'])===1,'MANIFEST_DIGEST');
expect(ftell($s) === 0,'STREAM_RESET');
Receipt::verify($s,$template,$r);
expect(true,'VERIFY_INTACT_RECEIPT');
expect(ftell($s)===0,'VERIFY_RESETS_STREAM');
$again=Receipt::inspect($s,$template,2);
expect($r===$again,'DETERMINISTIC_RECEIPT');
$mutated=$r; $mutated['source_rows']=5;
reject(fn()=>Receipt::verify($s,$template,$mutated),'TAMPERED_ROW_COUNT_REJECTED');
$mutated=$r; $mutated['chunk_manifest_sha256']=str_repeat('0',64);
reject(fn()=>Receipt::verify($s,$template,$mutated),'TAMPERED_MANIFEST_REJECTED');
$mutated=$r; $mutated['source_sha256']=str_repeat('0',64);
reject(fn()=>Receipt::verify($s,$template,$mutated),'TAMPERED_FILE_HASH_REJECTED');
$mutated=$r; $mutated['delimiter']=';';
reject(fn()=>Receipt::verify($s,$template,$mutated),'TAMPERED_DELIMITER_REJECTED');
$mutated=$r; unset($mutated['source_bytes']);
reject(fn()=>Receipt::verify($s,$template,$mutated),'MISSING_RECEIPT_FIELD_REJECTED');
$damaged=$template; $damaged['sha256']=str_repeat('a',64);
reject(fn()=>Receipt::inspect($s,$damaged,2),'TEMPLATE_DIGEST_TAMPER_REJECTED');
$damaged=$template; $damaged['mappings']['person']=['دیگر'];
reject(fn()=>Receipt::inspect($s,$damaged,2),'TEMPLATE_MAPPING_TAMPER_REJECTED');
reject(fn()=>Receipt::inspect($s,$template,0),'CHUNK_TOO_SMALL_REJECTED');
reject(fn()=>Receipt::inspect($s,$template,501),'CHUNK_TOO_BIG_REJECTED');
reject(fn()=>Receipt::inspect($s,$template,2,'|'),'BAD_DELIMITER_REJECTED');
fseek($s,1);
reject(fn()=>Receipt::inspect($s,$template,2),'STREAM_OFFSET_REJECTED');
rewind($s);
fseek($s,0,SEEK_END); fwrite($s, "09129999999,اضافه,ستاد\n"); rewind($s);
reject(fn()=>Receipt::verify($s,$template,$r),'MUTATED_SOURCE_REJECTED');
fclose($s);
$late=temporary("موبایل,نام,واحد\n" .
    "09123456789,علی,ستاد\n09121111111,زهرا,ستاد\n" .
    "09123333333,ناقص\n");
reject(fn()=>Receipt::inspect($late,$template,2),'LATE_FAILURE_NO_RECEIPT');
fclose($late);
$broken=temporary("موبایل,نام,واحد\n09123456789,علی,ستاد\n" .
    "09121111111,\"باز\n");
reject(fn()=>Receipt::inspect($broken,$template,1),'UNCLOSED_QUOTE_AFTER_FIRST_CHUNK');
fclose($broken);
$many="موبایل,نام,واحد\n";
for($i=0;$i<3000;$i++) $many .= '09' . str_pad((string)$i,9,'0',STR_PAD_LEFT) . ",کاربر,ستاد\n";
$big=temporary($many);
$receipt=Receipt::inspect($big,$template,500);
expect($receipt['source_rows']===3000,'THREE_THOUSAND_ROWS');
expect($receipt['chunk_count']===6,'SIX_500_ROW_CHUNKS');
expect($receipt['source_sha256']===hash('sha256',$many),'THREE_THOUSAND_HASH');
expect($receipt['preliminary_ready_rows']===3000,'THREE_THOUSAND_PRELIMINARY_READY');
Receipt::verify($big,$template,$receipt);
expect(true,'THREE_THOUSAND_VERIFY');
fclose($big);
echo 'R73B_COMPLETE_FILE_RECEIPT_TESTS='.$checks."_PASS\n";
echo "THREE_THOUSAND_ROWS_CHUNKS=6\n";
echo "DATABASE_CONNECTION=NO\n";
echo "PROVIDER_HTTP=NO\n";
echo "PRODUCTION_MUTATION=NO\n";
