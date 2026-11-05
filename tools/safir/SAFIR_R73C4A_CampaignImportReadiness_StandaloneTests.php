<?php
declare(strict_types=1);

/** R73C4A pure 100% source-only tests. No PDO, provider, production or SQL. */
require_once __DIR__.'/BaleSafirDurableCampaignPolicy.php';
require_once __DIR__.'/BaleSafirDurableCampaignImportReadinessPolicy.php';

use App\Services\BaleSafirDurableCampaignPolicy as T;
use App\Services\BaleSafirDurableCampaignImportReadinessPolicy as Ready;

$passed=0;
function yes(bool $ok,string $name): void {
    global $passed;
    if (!$ok) { fwrite(STDERR,"R73C4A_TEST_FAIL=$name\n");exit(1); }
    $passed++;
}
function no(callable $fn,string $name): void {
    global $passed;
    try { $fn(); }
    catch (InvalidArgumentException|RuntimeException|JsonException $e) {
        $passed++;return;
    }
    fwrite(STDERR,"R73C4A_TEST_FAIL=$name\n"); exit(1);
}
$template=T::freezeApprovedTemplate([
    'code'=>'r73c4a.synthetic','body'=>'Hello {{name}}',
    'phone_column'=>'mobile','mappings'=>['name'=>['name']],
    'bot_id'=>'1234567','template_id'=>'test_fixture_only',
    'active'=>true,'provider_approved'=>true,
]);
$campaign=[
    'id'=>77,'owner_user_id'=>501,'state_code'=>'draft',
    'approved_at'=>null,'approved_by_user_id'=>null,
    'template_snapshot_json'=>$template['snapshot_json'],
    'template_sha256'=>$template['sha256'],
    'template_code'=>$template['code']
];
$file=[
    'id'=>101,'campaign_id'=>77,'state_code'=>'complete',
    'stage_id'=>'safir-r73c-file-'.str_repeat('a',32),
    'source_sha256'=>str_repeat('a',64),
    'template_sha256'=>$template['sha256'],
    'chunk_manifest_sha256'=>str_repeat('c',64),
    'source_bytes'=>100,'source_rows'=>3,
    'expected_chunk_count'=>2,'imported_chunk_count'=>2,
    'preliminary_ready_rows'=>3,'preliminary_skipped_rows'=>0,
    'accepted_rows'=>2,'rejected_rows'=>1,
    'verified_at'=>'2026-11-05 12:00:00',
    'started_at'=>'2026-11-05 12:01:00',
    'completed_at'=>'2026-11-05 12:02:00',
    'failed_at'=>null,'failure_code'=>null,
];
$batches=[
    ['id'=>201,'campaign_id'=>77,'import_file_id'=>101,
     'chunk_index'=>1,'source_sha256'=>str_repeat('d',64),
     'state_code'=>'complete','source_rows'=>2,
     'accepted_rows'=>1,'rejected_rows'=>1,
     'finished_at'=>'2026-11-05 12:02:00'],
    ['id'=>202,'campaign_id'=>77,'import_file_id'=>101,
     'chunk_index'=>2,'source_sha256'=>str_repeat('e',64),
     'state_code'=>'complete','source_rows'=>1,
     'accepted_rows'=>1,'rejected_rows'=>0,
     'finished_at'=>'2026-11-05 12:02:00'],
];
$stats=[
    201=>['total'=>1,'ready'=>1,'request_ids_assigned'=>0,'delivery_metadata_assigned'=>0],
    202=>['total'=>1,'ready'=>1,'request_ids_assigned'=>0,'delivery_metadata_assigned'=>0],
];
$run=static fn($c,$f,$b,$s,$a=0,$supp=0)=>
    Ready::requireCompleteImport($c,$f,$b,$s,$a,$supp);

yes($run($campaign,[$file],$batches,$stats) ===
    ['file_count'=>1,'chunk_count'=>2,'source_rows'=>3,
     'ready_rows'=>2,'rejected_rows'=>1],
    'ONE_COMPLETE_FILE_TWO_CHUNKS');
$second=$file;
$second['id']=102;
$second['stage_id']='safir-r73c-file-'.str_repeat('b',32);
$second['source_sha256']=str_repeat('b',64);
$second['chunk_manifest_sha256']=str_repeat('f',64);
$second['source_rows']=1;
$second['expected_chunk_count']=1;
$second['imported_chunk_count']=1;
$second['preliminary_ready_rows']=0;
$second['preliminary_skipped_rows']=1;
$second['accepted_rows']=0;
$second['rejected_rows']=1;
$third=['id'=>203,'campaign_id'=>77,'import_file_id'=>102,
        'chunk_index'=>1,'source_sha256'=>str_repeat('9',64),
        'state_code'=>'complete','source_rows'=>1,
        'accepted_rows'=>0,'rejected_rows'=>1,
        'finished_at'=>'2026-11-05 12:02:00'];
yes($run($campaign,[$file,$second],[...$batches,$third],
    [...$stats,203=>['total'=>0,'ready'=>0,'request_ids_assigned'=>0,'delivery_metadata_assigned'=>0]]) ===
    ['file_count'=>2,'chunk_count'=>3,'source_rows'=>4,
     'ready_rows'=>2,'rejected_rows'=>2],
    'MULTI_FILE_INCLUDING_ALL_REJECTED_SECOND_FILE');
yes($run($campaign,[$file],$batches,$stats)===$run($campaign,[$file],$batches,$stats),
    'PURE_REPEATABLE');

no(fn()=>$run($campaign,[],$batches,$stats),'EMPTY_FILE_SET');
no(fn()=>$run($campaign,[$file],[],$stats),'EMPTY_BATCH_SET');
$c=$campaign; $c['state_code']='approved';
no(fn()=>$run($c,[$file],$batches,$stats),'ALREADY_APPROVED');
$c=$campaign; $c['approved_at']='2026-11-05 12:00:00';
no(fn()=>$run($c,[$file],$batches,$stats),'ALREADY_HAS_APPROVAL_TIME');
$c=$campaign; $c['approved_by_user_id']=503;
no(fn()=>$run($c,[$file],$batches,$stats),'ALREADY_HAS_APPROVER');
$c=$campaign; $c['template_sha256']=str_repeat('0',64);
no(fn()=>$run($c,[$file],$batches,$stats),'TEMPLATE_DIGEST_TAMPER');
$c=$campaign; $c['owner_user_id']=0;
no(fn()=>$run($c,[$file],$batches,$stats),'NO_CAMPAIGN_OWNER');
no(fn()=>$run($campaign,[$file],$batches,$stats,1),'ATTEMPT_ALREADY_EXISTS');
no(fn()=>$run($campaign,[$file],$batches,$stats,0,1),'SUPPRESSED_READY_EXISTS');

$f=$file; $f['state_code']='importing';
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_NOT_COMPLETE');
$f=$file; $f['campaign_id']=78;
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_OTHER_CAMPAIGN');
$f=$file; $f['completed_at']=null;
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_NO_COMPLETION_TIMESTAMP');
$f=$file; $f['started_at']=null;
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_NEVER_STARTED');
$f=$file; $f['failed_at']='2026-11-05 12:00:00';
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_FAILED_TIMESTAMP');
$f=$file; $f['imported_chunk_count']=1;
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_CHUNK_PROGRESS_INCOMPLETE');
$f=$file; $f['source_rows']=4;
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_SOURCE_ROWS_MISMATCH');
$f=$file; $f['accepted_rows']=3;
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_ACCEPTED_COUNTER_WRONG');
$f=$file; $f['preliminary_ready_rows']=1;$f['preliminary_skipped_rows']=2;
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_ACCEPTED_EXCEEDS_PRELIMINARY');
$f=$file; $f['stage_id']='not-private';
no(fn()=>$run($campaign,[$f],$batches,$stats),'INVALID_STAGE_NAME');
$f=$file; $f['template_sha256']=str_repeat('0',64);
no(fn()=>$run($campaign,[$f],$batches,$stats),'FILE_TEMPLATE_HASH_MISMATCH');
$f=$file; $f['chunk_manifest_sha256']='broken';
no(fn()=>$run($campaign,[$f],$batches,$stats),'BAD_CHUNK_MANIFEST_HASH');
no(fn()=>$run($campaign,[$file,$file],$batches,$stats),'DUPLICATE_FILE_ID');
$f=$second; $f['source_sha256']=$file['source_sha256'];
no(fn()=>$run($campaign,[$file,$f],[...$batches,$third],
    [...$stats,203=>['total'=>0,'ready'=>0,'request_ids_assigned'=>0,'delivery_metadata_assigned'=>0]]),
    'DUPLICATE_FILE_SOURCE_HASH');
no(fn()=>$run($campaign,[$file,$file],$batches,$stats),'DUPLICATE_STAGE_ID');

$b=$batches; $b[0]['import_file_id']=null;
no(fn()=>$run($campaign,[$file],$b,$stats),'LEGACY_UNLINKED_BATCH');
$b=$batches; $b[0]['campaign_id']=78;
no(fn()=>$run($campaign,[$file],$b,$stats),'BATCH_OTHER_CAMPAIGN');
$b=$batches; $b[1]['chunk_index']=1;
no(fn()=>$run($campaign,[$file],$b,$stats),'DUPLICATE_CHUNK_INDEX');
$b=$batches; $b[1]['chunk_index']=3;
no(fn()=>$run($campaign,[$file],$b,$stats),'OUT_OF_RANGE_CHUNK_INDEX');
$b=$batches; $b[1]['source_sha256']=$b[0]['source_sha256'];
no(fn()=>$run($campaign,[$file],$b,$stats),'DUPLICATE_CHUNK_SHA');
$b=$batches; $b[1]['state_code']='processing';
no(fn()=>$run($campaign,[$file],$b,$stats),'UNFINISHED_BATCH');
$b=$batches; $b[1]['finished_at']=null;
no(fn()=>$run($campaign,[$file],$b,$stats),'BATCH_MISSING_FINISH_TIME');
$b=$batches; $b[0]['accepted_rows']=2;
no(fn()=>$run($campaign,[$file],$b,$stats),'BATCH_INTERNAL_TOTALS_WRONG');
$b=$batches; $b[0]['source_rows']=501;$b[0]['accepted_rows']=500;$b[0]['rejected_rows']=1;
no(fn()=>$run($campaign,[$file],$b,$stats),'BATCH_BOUND_EXCEEDED');
$b=[$batches[0]];
no(fn()=>$run($campaign,[$file],$b,$stats),'MISSING_BATCH');
$b=[...$batches,$batches[1]];
no(fn()=>$run($campaign,[$file],$b,$stats),'EXTRA_BATCH');
$b=$batches; $b[1]['id']=201;
no(fn()=>$run($campaign,[$file],$b,$stats),'DUPLICATE_BATCH_ID');

$s=$stats; $s[201]['total']=2;
no(fn()=>$run($campaign,[$file],$batches,$s),'ACTUAL_RECIPIENT_COUNT_MISMATCH');
$s=$stats; $s[201]['ready']=0;
no(fn()=>$run($campaign,[$file],$batches,$s),'UNREADY_RECIPIENT_BLOCKS');
$s=$stats; $s[201]['request_ids_assigned']=1;
no(fn()=>$run($campaign,[$file],$batches,$s),'REQUEST_ALREADY_ASSIGNED');
$s=$stats; $s[201]['delivery_metadata_assigned']=1;
no(fn()=>$run($campaign,[$file],$batches,$s),'RECIPIENT_DELIVERY_METADATA_PRESENT');
$s=$stats; unset($s[202]);
no(fn()=>$run($campaign,[$file],$batches,$s),'MISSING_RECIPIENT_AGGREGATE');
$s=$stats; $s[203]=['total'=>0,'ready'=>0,'request_ids_assigned'=>0,'delivery_metadata_assigned'=>0];
no(fn()=>$run($campaign,[$file],$batches,$s),'UNLINKED_RECIPIENT_AGGREGATE');
$s=$stats; $s[201]['total']='bad';
no(fn()=>$run($campaign,[$file],$batches,$s),'NONNUMERIC_RECIPIENT_COUNT');
$s=$stats; $s[201]['total']=0;$s[201]['ready']=0;$s[202]['total']=0;$s[202]['ready']=0;
no(fn()=>$run($campaign,[$file],$batches,$s),'NO_ELIGIBLE_RECIPIENTS');

echo "R73C4A_PURE_CAMPAIGN_READINESS_TESTS={$passed}_PASS\n";
echo "CAMPAIGN_APPROVAL=NOT_IMPLEMENTED\n";
echo "PRODUCTION_DATABASE_MUTATION=NO\n";
echo "EXTERNAL_DATABASE_CONNECTION=NO\n";
echo "PROVIDER_SEND=NO\n";
