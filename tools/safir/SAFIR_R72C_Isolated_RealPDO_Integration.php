<?php
declare(strict_types=1);
// SAFIR R72C: real-PDO integration ONLY inside the R72C disposable socket-only DB.
// Never connect using network or production credentials. No provider calls.
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(STDERR, "R72C=FAIL;REASON=BAD_ARGS\n"); exit(2);
}
[$script, $socket, $datadir, $schema] = $argv;
$stage = dirname($socket);
if (!preg_match('#^/var/tmp/safir-r72c-pdo-[A-Za-z0-9_-]{8,80}$#D', $stage)
    || $socket !== $stage.'/db.sock' || $datadir !== $stage.'/data'
    || is_link($stage) || is_link($socket) || is_link($datadir)
    || realpath($stage) !== $stage || realpath($datadir) !== $datadir
    || !is_dir($datadir) || !file_exists($socket)
    || !preg_match('/^[A-Za-z0-9_]+$/D', $schema)
    || !extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "R72C=FAIL;REASON=ISOLATION_OR_EXTENSION\n"); exit(3);
}
require_once '/home/troca/BaleSafirDurableCampaignPolicy.php';
require_once '/home/troca/SAFIR_R72B_DurableCampaignRepository.php';
use App\Services\BaleSafirDurableCampaignRepository as Store;
function gate(bool $ok,string $why): void {
    if (!$ok) { fwrite(STDERR,"R72C=FAIL;REASON={$why}\n");exit(4); }
}
function rejected(callable $fn,string $why): void {
    try { $fn(); } catch (InvalidArgumentException|RuntimeException $e) {
        echo "R72C_REJECTION_{$why}=PASS\n"; return;
    }
    gate(false,"REJECTION_NOT_ENFORCED_{$why}");
}
function sampleRow(int $n,string $phone,string $name='کاربر آزمایشی'): array {
    return ['row'=>$n,'columns'=>['mobile'=>$phone,'name'=>$name,'unit'=>'واحد آزمایشی']];
}
try {
    $pdo=new PDO('mysql:unix_socket='.$socket.';dbname='.$schema.';charset=utf8mb4',
        'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                   PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                   PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);
    $id=$pdo->query('SELECT @@datadir AS datadir, @@socket AS socket, '
        .'@@skip_networking AS netoff, @@read_only AS readonly, '
        .'@@event_scheduler AS events, @@log_bin AS binlog, @@version AS version, DATABASE() AS dbname'
        )->fetch(PDO::FETCH_ASSOC);
    gate(is_array($id) && realpath((string)$id['datadir'])===$datadir
        && $id['socket']===$socket && (string)$id['netoff']==='1'
        && (string)$id['readonly']==='0' && strtoupper((string)$id['events'])==='OFF'
        && (string)$id['binlog']==='0' && str_starts_with((string)$id['version'],'11.8.')
        && $id['dbname']===$schema,'ISOLATED_SERVER_IDENTITY');
    $tables=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() "
        ."AND TABLE_NAME IN ('safir_campaigns','safir_campaign_import_batches',"
        ."'safir_campaign_recipients','safir_campaign_send_attempts','safir_suppressed_phones')"
        )->fetchColumn();
    gate((int)$tables===5,'FIVE_TABLES_MISSING');
    echo "R72C_REAL_PDO_SOCKET_ONLY=PASS\n";

    $repo=new Store($pdo);
    $template=['code'=>'r72c.operational','body'=>'سلام {{name}} {{unit}}',
        'phone_column'=>'mobile','mappings'=>['name'=>['name'],'unit'=>['unit']],
        'bot_id'=>'1234567','template_id'=>'approved_mock_01',
        'active'=>true,'provider_approved'=>true];
    $a=$repo->createDraft(710001,'R72C synthetic one',$template,'operational','Synthetic isolated test');
    gate(($a['state']??'')==='draft' && preg_match('/^[a-f0-9]{32}$/D',$a['reference'])===1,
        'DRAFT_PERSIST');
    $st=$pdo->prepare('INSERT INTO safir_suppressed_phones (phone_normalized,reason_code) VALUES (?,?)');
    $st->execute(['989121111111','r72c_test_only']);
    $s1=$repo->importChunk(710001,$a['reference'],str_repeat('a',64),'mock-1.csv',[
        sampleRow(2,'09123456789'),sampleRow(3,'۰۹۱۲۳۴۵۶۷۸۹'),
        sampleRow(4,'09121111111'),sampleRow(5,'invalid'),
        sampleRow(6,'09123334444',''),
    ]);
    gate($s1['source_rows']===5 && $s1['accepted_rows']===1 && $s1['rejected_rows']===4,
        'CHUNK_ONE_COUNTERS');
    gate($s1['reasons']===['invalid_phone'=>1,'invalid_fields'=>1,
        'duplicate_phone'=>1,'suppressed_phone'=>1],'SUPPRESSION_AND_DEDUP');
    echo "R72C_SUPPRESSION_AND_FIRST_CHUNK=PASS\n";
    $s2=$repo->importChunk(710001,$a['reference'],str_repeat('b',64),'mock-2.csv',[
        sampleRow(7,'09123456789'),sampleRow(8,'09127777777')]);
    gate($s2['source_rows']===2 && $s2['accepted_rows']===1
        && $s2['rejected_rows']===1 && $s2['reasons']['duplicate_phone']===1,
        'CROSS_CHUNK_DEDUP');
    $expected=['source_rows'=>7,'accepted_rows'=>2,'rejected_rows'=>5,'ready_rows'=>2];
    gate($repo->summaryForOwner(710001,$a['reference'])===$expected,'DURABLE_SUMMARY');
    echo "R72C_REAL_DB_TWO_CHUNKS=PASS\n";
    rejected(fn()=> $repo->importChunk(710001,$a['reference'],str_repeat('a',64),
        'duplicate.csv',[sampleRow(10,'09128888888')]),'DUPLICATE_HASH');
    rejected(fn()=> $repo->importChunk(710002,$a['reference'],str_repeat('c',64),
        'wrong-actor.csv',[sampleRow(10,'09128888888')]),'OTHER_OWNER');
    rejected(fn()=> $repo->summaryForOwner(710002,$a['reference']),'OWNER_SUMMARY');
    gate($repo->summaryForOwner(710001,$a['reference'])===$expected,
        'REJECTS_MUST_NOT_MUTATE');
    gate($pdo->beginTransaction(),'OUTER_BEGIN');
    try {
        rejected(fn()=> $repo->importChunk(710001,$a['reference'],str_repeat('d',64),
            'outer.csv',[sampleRow(11,'09128888888')]),'OUTER_TRANSACTION');
    } finally {
        $pdo->rollBack();
    }
    gate($repo->summaryForOwner(710001,$a['reference'])===$expected,'ROLLBACK_COUNT');
    echo "R72C_AUTH_AND_TRANSACTION_GUARDS=PASS\n";
    $b=$repo->createDraft(710003,'R72C synthetic two',$template,'operational','Synthetic isolated test');
    $s3=$repo->importChunk(710003,$b['reference'],str_repeat('e',64),
        'other-campaign.csv',[sampleRow(2,'09123456789')]);
    gate($s3['accepted_rows']===1,'CROSS_CAMPAIGN_DEDUP_WRONG_SCOPE');
    echo "R72C_CAMPAIGN_SCOPED_UNIQUENESS=PASS\n";
    echo "R72C_REAL_PDO_INTEGRATION=PASS\n";
    echo "R72C_PROVIDER_HTTP=NO\n";
    echo "R72C_PRODUCTION_MUTATION=NO\n";
} catch (Throwable $e) {
    fwrite(STDERR,"R72C=FAIL;REASON=REAL_PDO_EXCEPTION\n"); exit(7);
}
