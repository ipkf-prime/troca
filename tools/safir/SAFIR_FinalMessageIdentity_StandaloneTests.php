<?php
declare(strict_types=1);

require_once __DIR__ . '/../../public_html/app/Services/BaleSafirDurableCampaignPolicy.php';
require_once __DIR__ . '/../../public_html/app/Services/BaleSafirFinalMessageIdentity.php';

use App\Services\BaleSafirFinalMessageIdentity as Identity;

function ensure(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL: '.$label); }
    echo $label.'=PASS'.PHP_EOL;
}
function reject(callable $callback, string $label): void {
    try { $callback(); } catch (InvalidArgumentException|RuntimeException $e) {
        echo $label.'=PASS'.PHP_EOL;
        return;
    }
    throw new RuntimeException('FAIL: '.$label);
}
$a=Identity::prepare('bale', '۰۹۱۲۳۴۵۶۷۸۹', 'سلام {{name}}', ['name'=>'علی'], 20);
$b=Identity::prepare('bale', '989123456789', 'سلام {{name}}', ['name'=>'علی'], 20);
ensure($a['phone_normalized']==='989123456789', 'PERSIAN_DIGIT_NORMALIZATION');
ensure($a['final_text']==='سلام علی', 'TEMPLATE_RENDER');
ensure($a['character_count']===8, 'UNICODE_CHARACTER_COUNT');
ensure($a['dedup_sha256']===$b['dedup_sha256'], 'SAME_PHONE_SAME_TEXT_IDENTICAL');
$c=Identity::prepare('bale', '09123456789', 'سلام {{name}}', ['name'=>'رضا'], 20);
ensure($a['dedup_sha256']!==$c['dedup_sha256'], 'DIFFERENT_MESSAGE_NOT_DUPLICATE');
reject(fn()=>Identity::prepare('bale','09123456789','سلام {{name}}',['name'=>'علی'],7),'FINAL_TEXT_TOO_LONG');
reject(fn()=>Identity::prepare('bale','09123456789','سلام {{missing}}',[],20),'UNRESOLVED_PLACEHOLDER');
echo "SAFIR_FINAL_MESSAGE_IDENTITY_TEST=PASS".PHP_EOL;
