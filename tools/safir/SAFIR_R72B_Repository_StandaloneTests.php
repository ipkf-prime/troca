<?php
declare(strict_types=1);

/** R72B deterministic fake-PDO contract tests; never connects to any DB. */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $text, ?string $encoding = null): int {
        return preg_match_all('/./us', $text) ?: 0;
    }
}
$policy = __DIR__ . '/BaleSafirDurableCampaignPolicy.php';
if (!is_file($policy)) {
    $policy = __DIR__ . '/safir_r72a/BaleSafirDurableCampaignPolicy.php';
}
require_once $policy;
require_once __DIR__ . '/SAFIR_R72B_DurableCampaignRepository.php';

use App\Services\BaleSafirDurableCampaignRepository as Store;

final class MemoryPdo extends PDO
{
    public bool $active = false;
    public array $campaigns = [];
    public array $batches = [];
    public array $recipients = [];
    public array $suppressed = [];
    public int $begins = 0;
    public int $commits = 0;
    public int $rollbacks = 0;
    public ?string $failIfSqlContains = null;
    private int $nextCampaign = 11;
    private int $nextBatch = 100;
    private int $nextRecipient = 1000;
    private string $lastId = '0';
    private array $snapshot = [];

    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool
    {
        if ($this->active) { throw new RuntimeException('nested'); }
        $this->snapshot = [$this->campaigns, $this->batches, $this->recipients,
                           $this->nextCampaign, $this->nextBatch, $this->nextRecipient];
        $this->active = true; $this->begins++; return true;
    }
    public function commit(): bool
    {
        $this->active = false; $this->commits++; return true;
    }
    public function rollBack(): bool
    {
        [$this->campaigns,$this->batches,$this->recipients,$this->nextCampaign,
         $this->nextBatch,$this->nextRecipient] = $this->snapshot;
        $this->active = false; $this->rollbacks++; return true;
    }
    public function lastInsertId(?string $name = null): string|false { return $this->lastId; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new MemoryStmt($this,$query);
    }

    /** @return array{rows:array,rowCount:int} */
    public function simulate(string $query, array $p): array
    {
        if ($this->failIfSqlContains !== null && str_contains($query, $this->failIfSqlContains)) {
            throw new RuntimeException('injected_failure');
        }
        if (str_contains($query,'INSERT INTO safir_campaigns')) {
            $id = $this->nextCampaign++;
            $this->campaigns[$id] = ['id'=>$id,'public_reference'=>$p[0], 'owner_user_id'=>$p[1],
                'title'=>$p[2],'template_code'=>$p[3], 'template_sha256'=>$p[4],
                'template_snapshot_json'=>$p[5], 'state_code'=>'draft',
                'purpose_code'=>$p[6], 'consent_basis'=>$p[7]];
            $this->lastId = (string) $id; return ['rows'=>[], 'rowCount'=>1];
        }
        if (str_contains($query,'FROM safir_campaigns WHERE public_reference = ? FOR UPDATE')) {
            foreach ($this->campaigns as $row) {
                if ($row['public_reference'] === $p[0]) return ['rows'=>[$row], 'rowCount'=>1];
            }
            return ['rows'=>[], 'rowCount'=>0];
        }
        if (str_contains($query,'FROM safir_campaign_import_batches') && !str_contains($query,'SUM(source_rows)')) {
            foreach ($this->batches as $row) {
                if ($row['campaign_id'] == $p[0] && $row['source_sha256'] === $p[1]) {
                    return ['rows'=>[['id'=>$row['id']]],'rowCount'=>1];
                }
            }
            return ['rows'=>[], 'rowCount'=>0];
        }
        if (str_contains($query,'INSERT INTO safir_campaign_import_batches')) {
            $id = $this->nextBatch++;
            $this->batches[$id] = ['id'=>$id,'campaign_id'=>$p[0],
                'source_sha256'=>$p[1],'source_filename'=>$p[2],'state_code'=>'processing'];
            $this->lastId = (string) $id; return ['rows'=>[],'rowCount'=>1];
        }
        if (str_contains($query,'FROM safir_suppressed_phones')) {
            $exists = in_array($p[0],$this->suppressed,true);
            return ['rows'=>$exists ? [['1'=>1]] : [], 'rowCount'=>(int)$exists];
        }
        if (str_contains($query,'FROM safir_campaign_recipients WHERE campaign_id') && !str_contains($query,'SELECT COUNT(*)')) {
            foreach ($this->recipients as $item) {
                if ($item['campaign_id'] == $p[0] && $item['phone_normalized'] === $p[1]) {
                    return ['rows'=>[['1'=>1]],'rowCount'=>1];
                }
            }
            return ['rows'=>[],'rowCount'=>0];
        }
        if (str_contains($query,'INSERT INTO safir_campaign_recipients')) {
            $id=$this->nextRecipient++;
            $this->recipients[$id] = ['id'=>$id,'campaign_id'=>$p[0],
                'import_batch_id'=>$p[1],'source_row_number'=>$p[2],
                'phone_normalized'=>$p[3],'text_fields_json'=>$p[4], 'state_code'=>'ready'];
            return ['rows'=>[],'rowCount'=>1];
        }
        if (str_contains($query,'UPDATE safir_campaign_import_batches SET')) {
            $id=$p[4];
            if (!isset($this->batches[$id]) || $this->batches[$id]['state_code']!==$p[6]) {
                return ['rows'=>[],'rowCount'=>0];
            }
            $this->batches[$id]['state_code']=$p[0];
            $this->batches[$id]['source_rows']=$p[1];
            $this->batches[$id]['accepted_rows']=$p[2];
            $this->batches[$id]['rejected_rows']=$p[3];
            return ['rows'=>[],'rowCount'=>1];
        }
        if (str_contains($query,'FROM safir_campaigns WHERE owner_user_id')) {
            foreach ($this->campaigns as $row) {
                if ($row['owner_user_id']==$p[0] && $row['public_reference']===$p[1]) {
                    return ['rows'=>[['id'=>$row['id']]],'rowCount'=>1];
                }
            }
            return ['rows'=>[],'rowCount'=>0];
        }
        if (str_contains($query,'SUM(source_rows)')) {
            $totals=['source_rows'=>0,'accepted_rows'=>0,'rejected_rows'=>0];
            foreach ($this->batches as $row) {
                if ($row['campaign_id'] != $p[0] || $row['state_code'] !== 'complete') continue;
                foreach ($totals as $key=>$_) $totals[$key]+=$row[$key];
            }
            return ['rows'=>[$totals],'rowCount'=>1];
        }
        if (str_contains($query,'SELECT COUNT(*) FROM safir_campaign_recipients')) {
            $ready=0;
            foreach ($this->recipients as $row) {
                if ($row['campaign_id']==$p[0] && $row['state_code']==='ready') $ready++;
            }
            return ['rows'=>[['count'=>$ready]],'rowCount'=>1];
        }
        throw new RuntimeException('unexpected_sql: '.$query);
    }
}

final class MemoryStmt extends PDOStatement
{
    private array $data=[];
    private int $cursor=0;
    private int $affected=0;
    public function __construct(private MemoryPdo $connection,private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $r=$this->connection->simulate($this->sql,$params??[]);
        $this->data=$r['rows'];$this->cursor=0;$this->affected=$r['rowCount'];return true;
    }
    public function rowCount(): int { return $this->affected; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    { return $this->data[$this->cursor++] ?? false; }
    public function fetchColumn(int $column = 0): mixed
    { $item=$this->fetch();return $item === false ? false : array_values($item)[$column]??false; }
}

$count=0;
function ok(bool $yes,string $label): void
{
    global $count;
    if (!$yes) {fwrite(STDERR,'R72B_TEST_FAIL='.$label."\n");exit(1);}
    $count++;
}
function reject(callable $function, string $label): void
{
    global $count;
    try { $function(); } catch (InvalidArgumentException|RuntimeException $e) {
        $count++;return;
    }
    fwrite(STDERR,'R72B_TEST_FAIL='.$label."\n");exit(1);
}
function inputRow(int $n,string $phone,string $name='مدیر'): array
{ return ['row'=>$n,'columns'=>['شماره'=>$phone,'نام'=>$name,'واحد'=>'ستاد']]; }

$db=new MemoryPdo();$store=new Store($db);
$template=['code'=>'operational.demo','body'=>'سلام {{name}} {{unit}}',
    'phone_column'=>'شماره','mappings'=>['name'=>['نام'],'unit'=>['واحد']],
    'bot_id'=>'123456','template_id'=>'template_01','active'=>true,'provider_approved'=>true];
$created=$store->createDraft(7,'کمپین آزمایشی',$template,'operational','آزمون بدون ارسال');
ok(preg_match('/^[a-f0-9]{32}$/D',$created['reference'])===1,'campaign_reference');
ok($created['state']==='draft','campaign_draft_state');
ok(count($db->campaigns)===1 && $db->commits===1,'create_transaction_commit');
$db->suppressed=['989121111111'];
$chunk1=[inputRow(2,'09123456789'),inputRow(3,'09123456789'),
         inputRow(4,'09121111111'),inputRow(5,'invalid'), inputRow(6,'09123334444','')];
$summary=$store->importChunk(7,$created['reference'],str_repeat('a',64),'part-001.xlsx',$chunk1);
ok($summary['source_rows']===5,'source_rows');
ok($summary['accepted_rows']===1,'accepted_count');
ok($summary['rejected_rows']===4,'rejected_count');
ok($summary['reasons']===['invalid_phone'=>1,'invalid_fields'=>1,'duplicate_phone'=>1,'suppressed_phone'=>1], 'reason_totals');
ok(count($db->batches)===1 && count($db->recipients)===1,'atomic_batch_persist');
ok($db->batches[100]['state_code']==='complete' && $db->batches[100]['rejected_rows']===4,'batch_receipt');
$fields=json_decode($db->recipients[1000]['text_fields_json'],true);
ok($fields===['name'=>'مدیر','unit'=>'ستاد'],'dynamic_fields_json');
$chunk2=[inputRow(7,'09123456789'),inputRow(8,'09127777777')];
$summary2=$store->importChunk(7,$created['reference'],str_repeat('b',64),'part-002.xlsx',$chunk2);
ok($summary2['accepted_rows']===1 && $summary2['reasons']['duplicate_phone']===1,'cross_chunk_duplicate');
ok(count($db->recipients)===2 && count($db->batches)===2,'second_chunk_persisted');
reject(fn()=>$store->importChunk(7,$created['reference'],str_repeat('a',64),'part-001.xlsx',$chunk1),'same_chunk_hash_rejected');
ok(count($db->batches)===2,'duplicate_chunk_does_not_write');
reject(fn()=>$store->importChunk(8,$created['reference'],str_repeat('c',64),'part-003.xlsx',[inputRow(9,'09128888888')]),'wrong_actor_rejected');
reject(fn()=>$store->importChunk(7,$created['reference'],'bad-hash','part-003.xlsx',[inputRow(9,'09128888888')]),'bad_sha_rejected');
reject(fn()=>$store->importChunk(7,$created['reference'],str_repeat('c',64),'part-003.xlsx',[inputRow(9,'09128888888'),inputRow(9,'09129999999')]),'duplicate_source_row_rejected');
reject(fn()=>$store->createDraft(0,'Bad',$template,'operational','test'),'invalid_actor_rejected');
reject(fn()=>$store->createDraft(7,'Bad',$template,'operational',''),'empty_basis_rejected');
$bad=$template;$bad['provider_approved']=false;
reject(fn()=>$store->createDraft(7,'Bad',$bad,'operational','basis'),'unapproved_template_rejected');
$counts=$store->summaryForOwner(7,$created['reference']);
ok($counts===['source_rows'=>7,'accepted_rows'=>2,'rejected_rows'=>5,'ready_rows'=>2],'owner_counts');
reject(fn()=>$store->summaryForOwner(999,$created['reference']),'counts_owner_access');
$db->failIfSqlContains='UPDATE safir_campaign_import_batches SET';
$begins=$db->begins;$rollbacks=$db->rollbacks;
reject(fn()=>$store->importChunk(7,$created['reference'],str_repeat('c',64),'part-003.xlsx',[inputRow(9,'09128888888')]),'injected_finalize_failure');
ok($db->rollbacks===$rollbacks+1 && !$db->active,'rollback_on_failure');
ok(count($db->batches)===2 && count($db->recipients)===2,'rollback_removes_partial');
$db->failIfSqlContains=null;
$db->campaigns[11]['state_code']='approved';
reject(fn()=>$store->importChunk(7,$created['reference'],str_repeat('d',64),'part-004.xlsx',[inputRow(10,'09128888888')]),'approved_campaign_immutable');
$db->campaigns[11]['state_code']='draft';
$db->campaigns[11]['template_sha256']=str_repeat('0',64);
reject(fn()=>$store->importChunk(7,$created['reference'],str_repeat('d',64),'part-004.xlsx',[inputRow(10,'09128888888')]),'tampered_template_rejected');
ok($db->active===false,'transaction_closed');
echo 'R72B_FAKE_PDO_CONTRACT_TESTS='.$count."_PASS\n";
echo "EXTERNAL_DATABASE_CONNECTION=NO\n";
echo "PROVIDER_HTTP=NO\n";
echo "PRODUCTION_MUTATION=NO\n";
