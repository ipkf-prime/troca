<?php
declare(strict_types=1);

/**
 * SAFIR R73C3B: test-only real PDO integration against an approved-for-test,
 * private, socket-only MariaDB 11.8 disposable R68 copy.
 * NO connection to production, HTTP/provider, routes, worker or cron.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(STDERR,"R73C3B=FAIL;REASON=BAD_ARGS\n"); exit(2);
}
[$script, $socket, $datadir, $schema] = $argv;
$stage = dirname($socket);
if (!preg_match('#^/var/tmp/safir-r72c-pdo-[A-Za-z0-9_-]{8,80}$#D', $stage)
    || $socket !== $stage.'/db.sock' || $datadir !== $stage.'/data'
    || is_link($stage) || is_link($socket) || is_link($datadir)
    || realpath($stage) !== $stage || realpath($datadir) !== $datadir
    || !is_dir($datadir) || !file_exists($socket)
    || !preg_match('/^[A-Za-z0-9_]+$/D', $schema)
    || !extension_loaded('pdo_mysql') || !extension_loaded('mbstring')) {
    fwrite(STDERR,"R73C3B=FAIL;REASON=ISOLATION_OR_EXTENSION\n"); exit(3);
}

foreach ([
    'BaleSafirDurableCampaignPolicy.php',
    'SAFIR_R72B_DurableCampaignRepository.php',
    'BaleSafirDurableCsvChunkReader.php',
    'BaleSafirDurableCsvFileReceipt.php',
    'BaleSafirDurableCsvPrivateStage.php',
    'BaleSafirDurableFileCompletionPolicy.php',
    'BaleSafirDurableFileCompletionRepository.php',
    'BaleSafirDurableFileImportRepository.php'
] as $class) {
    require_once '/home/troca/'.$class;
}

use App\Services\BaleSafirDurableCampaignRepository as Campaigns;
use App\Services\BaleSafirDurableCsvPrivateStage as Stage;
use App\Services\BaleSafirDurableFileCompletionRepository as Finisher;
use App\Services\BaleSafirDurableFileImportRepository as Files;

$passed=0;
function gate(bool $ok,string $label): void {
    global $passed;
    if (!$ok) { fwrite(STDERR,"R73C3B=FAIL;REASON=$label\n");exit(4); }
    $passed++;
    echo "R73C3B_TEST_$label=PASS\n";
}
function rejected(callable $fn,string $label): void {
    global $passed;
    try { $fn(); }
    catch (InvalidArgumentException|RuntimeException $e) {
        if (!str_starts_with($e->getMessage(),'safir_')) {
            fwrite(STDERR,"R73C3B=FAIL;REASON=UNEXPECTED_REJECTION_TYPE_$label\n");
            exit(5);
        }
        $passed++; echo "R73C3B_REJECTION_$label=PASS\n";return;
    }
    fwrite(STDERR,"R73C3B=FAIL;REASON=REJECTION_MISSING_$label\n");
    exit(5);
}

try {
    $pdo=new PDO('mysql:unix_socket='.$socket.';dbname='.$schema.';charset=utf8mb4',
        'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                   PDO::ATTR_EMULATE_PREPARES=>false,
                   PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                   PDO::ATTR_TIMEOUT=>3]);
    $identity=$pdo->query(
        'SELECT @@datadir AS datadir,@@socket AS socket,@@skip_networking AS no_tcp,'
        .'@@read_only AS ro,@@event_scheduler AS events,@@log_bin AS binlog,'
        .'@@version AS version,DATABASE() AS db'
    )->fetch(PDO::FETCH_ASSOC);
    gate(is_array($identity)
         && realpath((string)$identity['datadir'])===$datadir
         && $identity['socket']===$socket
         && (string)$identity['no_tcp']==='1'
         && (string)$identity['ro']==='0'
         && strtoupper((string)$identity['events'])==='OFF'
         && (string)$identity['binlog']==='0'
         && str_starts_with((string)$identity['version'],'11.8.')
         && $identity['db']===$schema,
         'SOCKET_ONLY_WRITABLE_DISPOSABLE_IDENTITY');
    $tables=(int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
        ."AND TABLE_NAME IN ('safir_campaigns','safir_campaign_import_batches',"
        ."'safir_campaign_recipients','safir_campaign_send_attempts',"
        ."'safir_suppressed_phones','safir_campaign_import_files')"
    )->fetchColumn();
    gate($tables===6,'R73C2_SIX_TABLE_SCHEMA_REQUIRED');

    $privateRoot=$stage.'/r73c3-fixture';
    if (!mkdir($privateRoot,0700)) {
        throw new RuntimeException('safir_r73c3_test_private_root_create_failed');
    }
    chmod($privateRoot,0700);
    $marker=$privateRoot.'/'.Stage::ROOT_MARKER;
    if (file_put_contents($marker,Stage::ROOT_MARKER_BODY)===false) {
        throw new RuntimeException('safir_r73c3_test_private_marker_write_failed');
    }
    chmod($marker,0600);

    $template=['code'=>'r73c3b.synthetic','body'=>'Hello {{name}} {{unit}}',
       'phone_column'=>'mobile','mappings'=>[
           'name'=>['name'],'unit'=>['unit']
       ],'bot_id'=>'1234567','template_id'=>'isolated_fixture_only',
       'active'=>true,'provider_approved'=>true];
    $campaigns=new Campaigns($pdo);
    $files=new Files($pdo);
    $finish=new Finisher($pdo);
    $owner=710500;
    $draft=$campaigns->createDraft($owner,'R73C3B synthetic staged CSV',
        $template,'operational','TEST-ONLY NO PROVIDER SEND');
    $reference=$draft['reference'];
    $frozen=App\Services\BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($template);

    $body="mobile,name,unit\n"
        ."09123456789,Ali,HQ\n"
        ."invalid,Bad,HQ\n"
        ."09127777777,Bob,HQ\n"
        ."09123456789,Duplicate,HQ\n"
        ."09121111111,Suppressed,HQ\n";
    $source=$privateRoot.'/synthetic-input.csv';
    if (file_put_contents($source,$body)!==strlen($body)) {
        throw new RuntimeException('safir_r73c3_test_csv_write_failed');
    }
    chmod($source,0600);
    $staged=Stage::stage($source,$privateRoot,$frozen,hash('sha256',$body),3);
    $stageId=$staged['stage_id'];
    gate($staged['source_rows']===5 && $staged['chunk_count']===2
         && $staged['preliminary_ready_rows']===4
         && $staged['preliminary_skipped_rows']===1,
         'VERIFIED_FILE_RECEIPT_TWO_CHUNKS');

    rejected(fn()=> $files->registerVerifiedFile($owner+1,$reference,$privateRoot,$stageId),
        'OTHER_OWNER_REGISTRATION');
    $registered=$files->registerVerifiedFile($owner,$reference,$privateRoot,$stageId);
    gate($registered['state']==='verified' && $registered['file_id']>0
        && $registered['stage_id']===$stageId,
        'REGISTERED_VERIFIED_FILE');
    rejected(fn()=> $files->registerVerifiedFile($owner,$reference,$privateRoot,$stageId),
        'DUPLICATE_FILE_REGISTRATION');
    rejected(fn()=> $files->importVerifiedChunk($owner,$reference,$privateRoot,$stageId,2),
        'OUT_OF_ORDER_CHUNK');
    rejected(fn()=> $files->importVerifiedChunk($owner+1,$reference,$privateRoot,$stageId,1),
        'OTHER_OWNER_CHUNK');

    // R72C real-PDO probe already created this suppression before this test.
    gate((int)$pdo->query(
        "SELECT COUNT(*) FROM safir_suppressed_phones "
        ."WHERE phone_normalized='989121111111'"
    )->fetchColumn()===1,'PRIOR_SUPPRESSION_PRESENT');

    $first=$files->importVerifiedChunk($owner,$reference,$privateRoot,$stageId,1);
    gate($first['chunk_index']===1 && $first['source_rows']===3
        && $first['accepted_rows']===2 && $first['rejected_rows']===1
        && $first['reasons']['invalid_phone']===1,
        'CHUNK_ONE_LINKED_ATOMIC');
    rejected(fn()=> $finish->completeForOwner($owner,$reference,$privateRoot,$stageId),
        'COMPLETE_WITH_MISSING_CHUNK');
    rejected(fn()=> $files->importVerifiedChunk($owner,$reference,$privateRoot,$stageId,1),
        'CHUNK_ONE_RETRY_REJECTED');

    $second=$files->importVerifiedChunk($owner,$reference,$privateRoot,$stageId,2);
    gate($second['chunk_index']===2 && $second['source_rows']===2
        && $second['accepted_rows']===0 && $second['rejected_rows']===2
        && $second['reasons']['duplicate_phone']===1
        && $second['reasons']['suppressed_phone']===1,
        'CHUNK_TWO_DEDUP_AND_SUPPRESSION');
    $before=$campaigns->summaryForOwner($owner,$reference);
    gate($before===['source_rows'=>5,'accepted_rows'=>2,
                    'rejected_rows'=>3,'ready_rows'=>2],
        'DURABLE_BATCH_SUMMARY_MATCHES');

    // Isolated-only fault injection: DB batch counters may be internally
    // consistent but disagree with actual accepted recipient rows. The
    // terminal transaction must reject, then preserve importing state.
    $tamper=$pdo->prepare(
        'UPDATE safir_campaign_import_batches '
        .'SET accepted_rows=?,rejected_rows=? '
        .'WHERE import_file_id=? AND chunk_index=1'
    );
    $tamper->execute([1,2,$registered['file_id']]);
    rejected(fn()=> $finish->completeForOwner($owner,$reference,$privateRoot,$stageId),
        'REAL_RECIPIENT_COUNT_DRIFT_REJECTED');
    $q=$pdo->prepare('SELECT state_code FROM safir_campaign_import_files WHERE id=?');
    $q->execute([$registered['file_id']]);
    gate($q->fetchColumn()==='importing','FAILED_COMPLETION_DID_NOT_ADVANCE_STATE');
    $tamper->execute([2,1,$registered['file_id']]);
    gate($tamper->rowCount()===1,'ISOLATED_BATCH_COUNTER_RESTORED');

    $complete=$finish->completeForOwner($owner,$reference,$privateRoot,$stageId);
    gate($complete===['imported_chunk_count'=>2,'source_rows'=>5,
                       'accepted_rows'=>2,'rejected_rows'=>3],
        'FILE_TERMINALIZED_WITH_REAL_COUNTS');
    rejected(fn()=> $finish->completeForOwner($owner,$reference,$privateRoot,$stageId),
        'REPEAT_COMPLETION_REJECTED');
    rejected(fn()=> $files->importVerifiedChunk($owner,$reference,$privateRoot,$stageId,2),
        'POST_COMPLETION_IMPORT_REJECTED');

    $q=$pdo->prepare(
        'SELECT state_code,expected_chunk_count,imported_chunk_count,'
        .'accepted_rows,rejected_rows FROM safir_campaign_import_files '
        .'WHERE id=?'
    );
    $q->execute([$registered['file_id']]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    gate(is_array($row) && $row['state_code']==='complete'
        && (int)$row['expected_chunk_count']===2
        && (int)$row['imported_chunk_count']===2
        && (int)$row['accepted_rows']===2
        && (int)$row['rejected_rows']===3,
        'COMPLETE_RECEIPT_PERSISTED');
    $q=$pdo->prepare(
        'SELECT COUNT(*),COUNT(DISTINCT chunk_index),'
        .'SUM(accepted_rows),SUM(rejected_rows) '
        .'FROM safir_campaign_import_batches WHERE import_file_id=?'
    );
    $q->execute([$registered['file_id']]);
    $counts=$q->fetch(PDO::FETCH_NUM);
    gate(is_array($counts) && array_map('intval',$counts)===[2,2,2,3],
        'EXACT_TWO_CHUNK_DB_LINKS_PERSISTED');
    $q=$pdo->prepare(
        'SELECT COUNT(*) FROM safir_campaign_recipients r '
        .'JOIN safir_campaign_import_batches b ON b.id=r.import_batch_id '
        .'AND b.campaign_id=r.campaign_id WHERE b.import_file_id=?'
    );
    $q->execute([$registered['file_id']]);
    gate((int)$q->fetchColumn()===2,'ACTUAL_TWO_RECIPIENTS_PERSISTED');

    // New campaign/source: source bytes changed after private staging must be
    // rejected before DB registration and leave no durable file receipt.
    $secondDraft=$campaigns->createDraft($owner,'R73C3B tamper source',
        $template,'operational','TEST-ONLY');
    $secondReference=$secondDraft['reference'];
    $staged2=Stage::stage($source,$privateRoot,$frozen,hash('sha256',$body),3);
    $path2=$privateRoot.'/'.$staged2['stage_id'].'/source.csv';
    if (file_put_contents($path2,$body."changed\n")===false) {
        throw new RuntimeException('safir_r73c3_test_tamper_write_failed');
    }
    rejected(fn()=> $files->registerVerifiedFile(
        $owner,$secondReference,$privateRoot,$staged2['stage_id']
    ), 'TAMPERED_STAGED_SOURCE_REJECTED');
    $q=$pdo->prepare('SELECT COUNT(*) FROM safir_campaign_import_files WHERE stage_id=?');
    $q->execute([$staged2['stage_id']]);
    gate((int)$q->fetchColumn()===0,'TAMPER_REGISTRATION_ROLLED_BACK');

    echo "R73C3B_REAL_PDO_TESTS={$passed}_PASS\n";
    echo "R73C3B_REAL_PDO_IMPORT_COMPLETION=PASS\n";
    echo "R73C3B_PROVIDER_HTTP=NO\n";
    echo "R73C3B_PRODUCTION_DATABASE_MUTATION=NO\n";
} catch (Throwable $e) {
    fwrite(STDERR,"R73C3B=FAIL;REASON=UNEXPECTED_PDO_OR_STAGE_EXCEPTION\n");
    exit(7);
}
