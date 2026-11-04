<?php
declare(strict_types=1);

/** R73C1 source-only private CSV staging tests; synthetic data and temp paths. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $text, ?string $encoding = null): int {
        return preg_match_all('/./us', $text) ?: 0;
    }
}
foreach ([
    'BaleSafirDurableCampaignPolicy.php',
    'BaleSafirDurableCsvChunkReader.php',
    'BaleSafirDurableCsvFileReceipt.php',
    'BaleSafirDurableCsvPrivateStage.php'
] as $class) {
    $path = __DIR__ . '/' . $class;
    if (!is_file($path)) {
        $path = dirname(__DIR__, 2) . '/public_html/app/Services/' . $class;
    }
    require_once $path;
}

use App\Services\BaleSafirDurableCampaignPolicy as Policy;
use App\Services\BaleSafirDurableCsvPrivateStage as Stage;

$passed=0;
function assertion(bool $ok,string $name): void {
    global $passed;
    if (!$ok) { fwrite(STDERR, "R73C1_TEST_FAIL={$name}\n");exit(1); }
    $passed++;
}
function rejected(callable $fn,string $name): void {
    global $passed;
    try {$fn();} catch (InvalidArgumentException|RuntimeException|JsonException $e) {
        $passed++;return;
    }
    fwrite(STDERR,"R73C1_TEST_FAIL={$name}\n");exit(1);
}
function stageCount(string $root): int {
    return count(glob($root.'/safir-r73c-file-*',GLOB_ONLYDIR) ?: []);
}

$root = sys_get_temp_dir().'/safir-r73c1-test-'.bin2hex(random_bytes(8));
if (!mkdir($root,0700)) throw new RuntimeException('TEST_ROOT_SETUP_FAILED');
chmod($root,0700);
$marker=$root.'/'.Stage::ROOT_MARKER;
file_put_contents($marker,Stage::ROOT_MARKER_BODY);
chmod($marker,0600);
$source=tempnam(sys_get_temp_dir(),'r73c-in-');
if (!is_string($source)) throw new RuntimeException('TEST_SOURCE_SETUP_FAILED');
$csv="موبایل,نام,واحد\n".
    "09123456789,علی,ستاد\n".
    "invalid,فرد,ستاد\n".
    "۰۹۱۲۳۴۵۶۷۸۸,زهرا,امور\n";
file_put_contents($source,$csv);
$template = Policy::freezeApprovedTemplate([
    'code'=>'r73c1.dynamic',
    'body'=>'سلام {{name}} {{unit}}',
    'phone_column'=>'موبایل',
    'mappings'=>['name'=>['نام'],'unit'=>['واحد']],
    'bot_id'=>'1234567','template_id'=>'approved_mock_01',
    'active'=>true,'provider_approved'=>true,
]);
try {
    $digest=hash('sha256',$csv);
    assertion(strlen($digest)===64,'INPUT_SOURCE_HASH_READY');
    $stage=Stage::stage($source,$root,$template,$digest,2);
    assertion($stage['stage_verified']===true,'PUBLISHED_STAGE');
    assertion(preg_match('/^safir-r73c-file-[a-f0-9]{32}$/D',$stage['stage_id'])===1,'STAGE_ID');
    assertion($stage['source_sha256']===$digest,'SOURCE_HASH');
    assertion($stage['source_bytes']===strlen($csv),'SOURCE_BYTES');
    assertion($stage['source_rows']===3,'SOURCE_ROWS');
    assertion($stage['chunk_count']===2,'CHUNKS');
    assertion($stage['preliminary_ready_rows']===2,'PRELIMINARY_READY');
    assertion($stage['preliminary_skipped_rows']===1,'PRELIMINARY_SKIPPED');
    $dir=$root.'/'.$stage['stage_id'];
    assertion(is_dir($dir),'PRIVATE_STAGE_DIR_EXISTS');
    assertion((fileperms($dir)&0077)===0,'PRIVATE_DIRECTORY_MODE');
    assertion((fileperms($dir.'/source.csv')&0077)===0,'PRIVATE_SOURCE_MODE');
    assertion((fileperms($dir.'/receipt.json')&0077)===0,'PRIVATE_RECEIPT_MODE');
    $manifest=file_get_contents($dir.'/receipt.json');
    assertion(is_string($manifest) && !str_contains($manifest,'09123456789'),
        'NO_RAW_PHONE_IN_RECEIPT');
    $receipt=Stage::verify($root,$stage['stage_id'],$template);
    assertion($receipt['source_sha256']===$digest,'VERIFY_GOOD_RECEIPT');
    assertion($receipt['source_rows']===3,'VERIFY_ROW_COUNT');
    assertion(Stage::verify($root,$stage['stage_id'],$template)===$receipt,'REPEAT_VERIFICATION');

    $savedSource=file_get_contents($dir.'/source.csv');
    file_put_contents($dir.'/source.csv',$savedSource.'X');
    rejected(fn()=>Stage::verify($root,$stage['stage_id'],$template),'CHANGED_SOURCE_REJECTED');
    file_put_contents($dir.'/source.csv',$savedSource);
    assertion(Stage::verify($root,$stage['stage_id'],$template)===$receipt,'SOURCE_REPAIRED');

    file_put_contents($dir.'/receipt.json',str_replace(
        'verified_not_imported','imported',$manifest));
    rejected(fn()=>Stage::verify($root,$stage['stage_id'],$template),'MANIFEST_STATUS_TAMPER_REJECTED');
    file_put_contents($dir.'/receipt.json',$manifest);
    assertion(Stage::verify($root,$stage['stage_id'],$template)===$receipt,'MANIFEST_REPAIRED');

    $invalidTemplate=$template;
    $invalidTemplate['sha256']=str_repeat('0',64);
    rejected(fn()=>Stage::verify($root,$stage['stage_id'],$invalidTemplate),'WRONG_TEMPLATE_REJECTED');
    rejected(fn()=>Stage::verify($root,'../../etc/passwd',$template),'PATH_TRAVERSAL_REJECTED');
    rejected(fn()=>Stage::verify($root,'safir-r73c-file-'.str_repeat('1',32),$template),
        'MISSING_STAGE_REJECTED');
    chmod($dir.'/receipt.json',0644);
    rejected(fn()=>Stage::verify($root,$stage['stage_id'],$template),'WORLD_READABLE_RECEIPT_REJECTED');
    chmod($dir.'/receipt.json',0600);
    assertion(Stage::verify($root,$stage['stage_id'],$template)===$receipt,'PRIVATE_RECEIPT_RECOVERED');

    $originalMarker=Stage::ROOT_MARKER_BODY;
    file_put_contents($marker,'INVALID');
    rejected(fn()=>Stage::verify($root,$stage['stage_id'],$template),'INVALID_ROOT_MARKER_REJECTED');
    file_put_contents($marker,$originalMarker);
    chmod($marker,0600);
    chmod($root,0755);
    rejected(fn()=>Stage::verify($root,$stage['stage_id'],$template),'UNSAFE_ROOT_MODE_REJECTED');
    chmod($root,0700);

    $fake=$root.'-link';
    if (!symlink($root,$fake)) throw new RuntimeException('TEST_SYMLINK_SETUP_FAILED');
    rejected(fn()=>Stage::verify($fake,$stage['stage_id'],$template),'SYMLINK_ROOT_REJECTED');
    unlink($fake);
    $srcLink=$root.'/source-link.csv';
    if (!symlink($source,$srcLink)) throw new RuntimeException('TEST_SOURCE_LINK_SETUP_FAILED');
    rejected(fn()=>Stage::stage($srcLink,$root,$template,$digest,2),'SYMLINK_SOURCE_REJECTED');
    unlink($srcLink);

    $before=stageCount($root);
    rejected(fn()=>Stage::stage($source,$root,$template,str_repeat('0',64),2),
        'BAD_EXTERNAL_DIGEST_REJECTED');
    assertion(stageCount($root)===$before,'FAILED_HASH_NO_PUBLISHED_STAGE');
    rejected(fn()=>Stage::stage($source,$root,$template,$digest,2,',',1024),
        'FILE_LIMIT_ENFORCED');
    assertion(stageCount($root)===$before,'TOO_LARGE_SOURCE_CLEANED');

    $broken=$root.'/broken.csv';
    $late="موبایل,نام,واحد\n09123456789,علی,ستاد\n".
        "۰۹۱۲۳۴۵۶۷۸۸,زهرا,امور\n09121111111,ناقص\n";
    file_put_contents($broken,$late);
    rejected(fn()=>Stage::stage($broken,$root,$template,hash('sha256',$late),1),
        'LATE_PARSE_FAILURE_NO_STAGE');
    assertion(stageCount($root)===$before,'LATE_ERROR_CLEANED');
    unlink($broken);

    $big=$root.'/synthetic-3000.csv';
    $handle=fopen($big,'wb');
    fwrite($handle,"موبایل,نام,واحد\n");
    for($i=0;$i<3000;$i++) {
        fwrite($handle,'09'.str_pad((string)$i,9,'0',STR_PAD_LEFT).",کاربر,ستاد\n");
    }
    fclose($handle);
    $bigSha=hash_file('sha256',$big);
    $batch=Stage::stage($big,$root,$template,$bigSha,500);
    assertion($batch['source_rows']===3000,'THREE_THOUSAND_ROWS_STAGED');
    assertion($batch['chunk_count']===6,'SIX_BOUNDED_CHUNKS');
    $other=Stage::verify($root,$batch['stage_id'],$template);
    assertion($other['chunk_count']===6,'THREE_THOUSAND_STAGED_VERIFIED');
    unlink($big);
    echo 'R73C1_PRIVATE_STAGE_TESTS='.$passed."_PASS\n";
    echo "THREE_THOUSAND_ROWS_CHUNKS=6\n";
    echo "EXTERNAL_DATABASE_CONNECTION=NO\n";
    echo "PROVIDER_HTTP=NO\n";
    echo "PRODUCTION_MUTATION=NO\n";
} finally {
    foreach (glob($root.'/safir-r73c-file-*',GLOB_ONLYDIR) ?: [] as $folder) {
        foreach (['source.csv','receipt.json'] as $name) {
            $file=$folder.'/'.$name;
            if (is_file($file)&&!is_link($file)) unlink($file);
        }
        rmdir($folder);
    }
    if (is_file($marker)) unlink($marker);
    if (is_file($source)) unlink($source);
    rmdir($root);
}
