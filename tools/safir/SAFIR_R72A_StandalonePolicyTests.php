<?php
declare(strict_types=1);
// CLI test-only fallback when the testing container lacks ext-mbstring.
// The application already requires mbstring for its existing SAFIR service.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $text, ?string $encoding = null): int {
        return preg_match_all('/./us', $text) ?: 0;
    }
}
// Run either inside the Git repository or beside a downloaded class file.
$classPath = dirname(__DIR__, 2) . '/public_html/app/Services/BaleSafirDurableCampaignPolicy.php';
if (!is_file($classPath)) {
    $classPath = __DIR__ . '/BaleSafirDurableCampaignPolicy.php';
}
require_once $classPath;

use App\Services\BaleSafirDurableCampaignPolicy as P;

$tested = 0;
function same(mixed $actual, mixed $expected, string $label): void {
    global $tested;
    if ($actual !== $expected) {
        fwrite(STDERR, "R72A_TEST_FAIL={$label}\n");
        exit(1);
    }
    $tested++;
}
function rejects(callable $action, string $label): void {
    global $tested;
    try {
        $action();
    } catch (InvalidArgumentException) {
        $tested++;
        return;
    }
    fwrite(STDERR, "R72A_TEST_FAIL={$label}\n");
    exit(1);
}

$template = [
    'code' => 'operational.demo',
    'body' => 'سلام {{recipient_name}} در {{unit_title}}',
    'phone_column' => 'شماره',
    'mappings' => ['recipient_name' => ['نام', 'نام خانوادگی'], 'unit_title' => ['واحد']],
    'bot_id' => '123456', 'template_id' => 'approved_tpl_01',
    'active' => true, 'provider_approved' => true,
];
$frozen = P::freezeApprovedTemplate($template);
same($frozen['code'], 'operational.demo', 'dynamic_template_code');
same($frozen['sha256'], hash('sha256', $frozen['snapshot_json']), 'template_hash');
same(json_decode($frozen['snapshot_json'], true), $template, 'template_snapshot');
same(P::normalizePhone('۰۹۱۲۳۴۵۶۷۸۹'), '989123456789', 'persian_digits');
same(P::normalizePhone('٠٩١٢٣٤٥٦٧٨٩'), '989123456789', 'arabic_digits');
same(P::normalizePhone('+98 (912) 345-6789'), '989123456789', 'plus_98');
same(P::normalizePhone('9123456789'), '989123456789', 'excel_numeric');
same(P::normalizePhone('00989123456789'), '989123456789', 'intl_00');
same(P::normalizePhone('0012345'), null, 'invalid_phone');
$rows = [
    ['row'=>2, 'columns'=>['شماره'=>'09123456789','نام'=>'علی','نام خانوادگی'=>'رضایی','واحد'=>'مالی']],
    ['row'=>3, 'columns'=>['شماره'=>'invalid','نام'=>'زهرا','نام خانوادگی'=>'الف','واحد'=>'مالی']],
    ['row'=>4, 'columns'=>['شماره'=>'09121111111','نام'=>'','نام خانوادگی'=>'الف','واحد'=>'مالی']],
];
$plan = P::planChunk($frozen,$rows);
same($plan['ready_count'], 1, 'ready_count');
same($plan['skipped_count'], 2, 'skipped_count');
same($plan['ready'][0]['phone_normalized'], '989123456789', 'normalized_ready');
same(json_decode($plan['ready'][0]['text_fields_json'],true),
    ['recipient_name'=>'علی رضایی','unit_title'=>'مالی'], 'dynamic_fields');
same($plan['skipped'][0]['reason'], 'invalid_phone','invalid_reason');
same($plan['skipped'][1]['reason'], 'invalid_fields','invalid_fields_reason');
rejects(fn()=>P::planChunk($frozen, []), 'empty_chunk_rejected');
rejects(fn()=>P::planChunk($frozen, array_fill(0, P::MAX_CHUNK_ROWS + 1, $rows[0])), 'chunk_limit');
rejects(fn()=>P::planChunk($frozen,[['row'=>0,'columns'=>[]]]), 'bad_row_number');
$bad = $template; $bad['provider_approved'] = false;
rejects(fn()=>P::freezeApprovedTemplate($bad),'unapproved_template');
$bad = $template; $bad['body'] = 'سلام {{unknown_variable}}';
rejects(fn()=>P::freezeApprovedTemplate($bad),'mapping_mismatch');
$bad = $template; $bad['body'] = 'سلام {{ bad }}';
rejects(fn()=>P::freezeApprovedTemplate($bad),'bad_placeholder');
echo "R72A_STANDALONE_POLICY_TESTS={$tested}_PASS\n";
echo "DATABASE_CONNECTION=NO\n";
echo "PROVIDER_HTTP=NO\n";
echo "SOURCE_ONLY=YES\n";
