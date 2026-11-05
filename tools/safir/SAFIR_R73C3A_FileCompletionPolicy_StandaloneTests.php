<?php
declare(strict_types=1);

namespace App\Services {
    if (!class_exists(BaleSafirDurableCampaignPolicy::class)) {
        final class BaleSafirDurableCampaignPolicy {
            public const MAX_CHUNK_ROWS = 500;
        }
    }
}
namespace {
    use App\Services\BaleSafirDurableFileCompletionPolicy as P;

    require_once __DIR__.'/BaleSafirDurableFileCompletionPolicy.php';

    $passed = 0;
    function yes(bool $ok, string $name): void {
        global $passed;
        if (!$ok) { fwrite(STDERR, "R73C3A_TEST_FAIL=$name\n"); exit(1); }
        ++$passed;
    }
    function rejected(callable $fn, string $name): void {
        global $passed;
        try { $fn(); }
        catch (\InvalidArgumentException|\RuntimeException $e) { ++$passed; return; }
        fwrite(STDERR, "R73C3A_TEST_FAIL=$name\n"); exit(1);
    }

    $file = [
        'id'=>9001,'campaign_id'=>7,'state_code'=>'importing',
        'source_rows'=>5,'expected_chunk_count'=>2,
        'preliminary_ready_rows'=>4,'preliminary_skipped_rows'=>1,
        'completed_at'=>null,'failed_at'=>null,
    ];
    $batches = [
        ['id'=>101,'campaign_id'=>7,'import_file_id'=>9001,'chunk_index'=>1,
         'state_code'=>'complete','source_rows'=>3,'accepted_rows'=>2,
         'rejected_rows'=>1,'finished_at'=>'2026-11-05 09:00:00'],
        ['id'=>102,'campaign_id'=>7,'import_file_id'=>9001,'chunk_index'=>2,
         'state_code'=>'complete','source_rows'=>2,'accepted_rows'=>1,
         'rejected_rows'=>1,'finished_at'=>'2026-11-05 09:00:00'],
    ];
    $counts=[101=>2,102=>1];
    $base=P::requireExactCompletion($file,$batches,$counts);
    yes($base===['imported_chunk_count'=>2,'source_rows'=>5,
                  'accepted_rows'=>3,'rejected_rows'=>2],'GOOD_EXACT_TERMINAL_TOTALS');
    yes(P::requireExactCompletion($file,$batches,$counts)===$base,
        'PURE_DETERMINISTIC_RECHECK');

    $f=$file; $f['state_code']='verified';
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'UNSTARTED_REJECTED');
    $f=$file; $f['state_code']='complete';
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'ALREADY_COMPLETE_REJECTED');
    $f=$file; $f['state_code']='failed';
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'FAILED_STATE_REJECTED');
    $f=$file; $f['completed_at']='2026-11-05 09:00:00';
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'PREEXISTING_TERMINAL_TIMESTAMP_REJECTED');
    $f=$file; $f['failed_at']='2026-11-05 09:00:00';
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'PREEXISTING_FAILURE_TIMESTAMP_REJECTED');
    $f=$file; $f['expected_chunk_count']=3;
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'MISSING_CHUNK_REJECTED');
    $f=$file; $f['source_rows']=4;
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'FILE_SOURCE_ROWS_MISMATCH_REJECTED');
    $f=$file; $f['preliminary_ready_rows']=3;
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'PRELIMINARY_SUM_MISMATCH_REJECTED');
    $f=$file; $f['preliminary_ready_rows']=2; $f['preliminary_skipped_rows']=3;
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'ACCEPTED_EXCEEDS_PRELIMINARY_READY_REJECTED');

    $b=$batches; unset($b[1]); $b=array_values($b);
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'ONLY_FIRST_CHUNK_REJECTED');
    $b=$batches; $b[1]['chunk_index']=3;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'CHUNK_INDEX_GAP_REJECTED');
    $b=$batches; $b[1]['chunk_index']=1;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'DUPLICATE_CHUNK_INDEX_REJECTED');
    $b=$batches; $b[0]['campaign_id']=8;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'CROSS_CAMPAIGN_REJECTED');
    $b=$batches; $b[0]['import_file_id']=9002;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'CROSS_FILE_REJECTED');
    $b=$batches; $b[0]['state_code']='processing';
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'PARTIAL_BATCH_REJECTED');
    $b=$batches; $b[0]['finished_at']=null;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'UNFINISHED_BATCH_REJECTED');
    $b=$batches; $b[1]['id']=101;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'DUPLICATE_BATCH_ID_REJECTED');
    $b=$batches; $b[1]['source_rows']=3;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'BATCH_ROWS_MISMATCH_REJECTED');
    $b=$batches; $b[0]['accepted_rows']=1;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'BATCH_LOCAL_COUNTERS_REJECTED');
    $b=$batches; $b[0]['source_rows']=501;$b[0]['accepted_rows']=500;$b[0]['rejected_rows']=1;
    rejected(fn()=>P::requireExactCompletion($file,$b,$counts),'BOUNDED_CHUNK_LIMIT_ENFORCED');
    $c=[101=>1,102=>1];
    rejected(fn()=>P::requireExactCompletion($file,$batches,$c),'ACTUAL_RECIPIENT_COUNT_MISMATCH_REJECTED');
    $c=[101=>2];
    rejected(fn()=>P::requireExactCompletion($file,$batches,$c),'MISSING_BATCH_RECIPIENT_COUNT_REJECTED');
    $c=[101=>2,102=>1,103=>0];
    rejected(fn()=>P::requireExactCompletion($file,$batches,$c),'EXTRA_RECIPIENT_BATCH_REJECTED');
    $f=$file; $f['source_rows']=-1;
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'NEGATIVE_SOURCE_ROWS_REJECTED');
    $f=$file; $f['source_rows']='1 OR 1=1';
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'NONNUMERIC_COUNTER_REJECTED');
    $f=$file; $f['expected_chunk_count']='0';
    rejected(fn()=>P::requireExactCompletion($f,$batches,$counts),'ZERO_CHUNKS_REJECTED');

    // Single chunk with zero accepted: 1 row rejected, zero recipient rows.
    $single=$file;
    $single['source_rows']=1;$single['expected_chunk_count']=1;
    $single['preliminary_ready_rows']=0;$single['preliminary_skipped_rows']=1;
    $one=[['id'=>111,'campaign_id'=>7,'import_file_id'=>9001,'chunk_index'=>1,
           'state_code'=>'complete','source_rows'=>1,'accepted_rows'=>0,
           'rejected_rows'=>1,'finished_at'=>'2026-11-05 09:00:00']];
    yes(P::requireExactCompletion($single,$one,[111=>0])['accepted_rows']===0,
        'ZERO_ACCEPTED_VALID_WITH_ONE_REJECTED');
    echo "R73C3A_PURE_COMPLETION_POLICY_TESTS={$passed}_PASS\n";
    echo "PRODUCTION_DATABASE_MUTATION=NO\n";
    echo "EXTERNAL_DATABASE_CONNECTION=NO\n";
    echo "PROVIDER_SEND=NO\n";
}
