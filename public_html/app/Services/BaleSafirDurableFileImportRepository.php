<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * SAFIR R73C3B — disconnected, DB-injected, source-only file-bound importer.
 *
 * NOT routed, scheduled, deployed, permitted to approve a campaign or to send.
 * Requires R70 + R73C2 DDL on an independently reviewed PDO connection.
 * Locks campaign FIRST, then file and batches; same order as R73C3A.
 * CSV is read exclusively from independently verified private Stage::verify.
 */
final class BaleSafirDurableFileImportRepository
{
    public function __construct(private readonly PDO $db) {}

    /** @return array{file_id:int,stage_id:string,state:string} */
    public function registerVerifiedFile(
        int $actorId, string $campaignReference,
        string $privateRoot, string $stageId
    ): array {
        self::validateRequest($actorId, $campaignReference, $privateRoot, $stageId);
        return $this->transaction(function () use (
            $actorId, $campaignReference, $privateRoot, $stageId
        ): array {
            [$campaignId, $frozen] = $this->lockedCampaign($actorId, $campaignReference);
            $receipt = BaleSafirDurableCsvPrivateStage::verify($privateRoot, $stageId, $frozen);
            $this->validateReceipt($receipt);
            $stmt = $this->db->prepare(
                'SELECT id FROM safir_campaign_import_files '
                . 'WHERE campaign_id = ? AND source_sha256 = ? LIMIT 1'
            );
            $stmt->execute([$campaignId, $receipt['source_sha256']]);
            if ($stmt->fetchColumn() !== false) {
                throw new RuntimeException('safir_r73c3_file_source_already_registered');
            }
            $stmt = $this->db->prepare(
                'INSERT INTO safir_campaign_import_files '
                . '(campaign_id,stage_id,source_sha256,template_sha256,'
                . 'chunk_manifest_sha256,source_bytes,source_rows,'
                . 'expected_chunk_count,preliminary_ready_rows,'
                . "preliminary_skipped_rows,state_code) VALUES (?,?,?,?,?,?,?,?,?,?,'verified')"
            );
            $stmt->execute([
                $campaignId, $stageId, $receipt['source_sha256'],
                $receipt['template_sha256'], $receipt['chunk_manifest_sha256'],
                $receipt['source_bytes'], $receipt['source_rows'],
                $receipt['chunk_count'], $receipt['preliminary_ready_rows'],
                $receipt['preliminary_skipped_rows'],
            ]);
            if ($stmt->rowCount() !== 1 || !ctype_digit($this->db->lastInsertId())) {
                throw new RuntimeException('safir_r73c3_file_register_not_confirmed');
            }
            $fileId = (int)$this->db->lastInsertId();
            if ($fileId < 1) {
                throw new RuntimeException('safir_r73c3_file_id_invalid');
            }
            return ['file_id'=>$fileId, 'stage_id'=>$stageId, 'state'=>'verified'];
        });
    }

    /**
     * Imports exactly one NEXT chunk from a previously staged immutable
     * full-file receipt. No arbitrary caller-provided rows or chunk SHA allowed.
     * @return array{file_id:int,chunk_index:int,chunk_sha256:string,source_rows:int,accepted_rows:int,rejected_rows:int,reasons:array<string,int>}
     */
    public function importVerifiedChunk(
        int $actorId, string $campaignReference,
        string $privateRoot, string $stageId, int $requestedChunkIndex
    ): array {
        self::validateRequest($actorId, $campaignReference, $privateRoot, $stageId);
        if ($requestedChunkIndex < 1 || $requestedChunkIndex > 4294967295) {
            throw new InvalidArgumentException('safir_r73c3_chunk_index_invalid');
        }
        return $this->transaction(function () use (
            $actorId, $campaignReference, $privateRoot, $stageId, $requestedChunkIndex
        ): array {
            [$campaignId, $frozen] = $this->lockedCampaign($actorId, $campaignReference);
            $receipt = BaleSafirDurableCsvPrivateStage::verify($privateRoot, $stageId, $frozen);
            $this->validateReceipt($receipt);
            $stmt = $this->db->prepare(
                'SELECT id,campaign_id,stage_id,state_code,source_sha256,'
                . 'template_sha256,chunk_manifest_sha256,source_bytes,'
                . 'source_rows,expected_chunk_count,preliminary_ready_rows,'
                . 'preliminary_skipped_rows,imported_chunk_count,'
                . 'accepted_rows,rejected_rows,completed_at,failed_at '
                . 'FROM safir_campaign_import_files '
                . 'WHERE campaign_id = ? AND stage_id = ? FOR UPDATE'
            );
            $stmt->execute([$campaignId, $stageId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($file)) {
                throw new RuntimeException('safir_r73c3_file_not_registered');
            }
            self::matchFile($file, $receipt);
            if (!in_array($file['state_code'], ['verified','importing'], true)
                || $file['completed_at'] !== null || $file['failed_at'] !== null) {
                throw new RuntimeException('safir_r73c3_file_not_importable');
            }
            $fileId = (int)$file['id'];
            $expected = (int)$file['expected_chunk_count'];
            if ($requestedChunkIndex > $expected) {
                throw new RuntimeException('safir_r73c3_chunk_index_outside_receipt');
            }
            $stmt = $this->db->prepare(
                'SELECT id,chunk_index,state_code,source_rows,accepted_rows,'
                . 'rejected_rows,finished_at FROM safir_campaign_import_batches '
                . 'WHERE campaign_id = ? AND import_file_id = ? '
                . 'ORDER BY chunk_index,id FOR UPDATE'
            );
            $stmt->execute([$campaignId,$fileId]);
            $previous = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($previous)) {
                throw new RuntimeException('safir_r73c3_previous_chunks_unavailable');
            }
            $priorRows=0; $priorAccepted=0; $priorRejected=0;
            foreach ($previous as $index=>$batch) {
                if ((int)$batch['chunk_index']!==$index+1
                    || $batch['state_code']!=='complete'
                    || $batch['finished_at']===null) {
                    throw new RuntimeException('safir_r73c3_previous_chunks_inconsistent');
                }
                $n=(int)$batch['source_rows'];
                $a=(int)$batch['accepted_rows'];
                $r=(int)$batch['rejected_rows'];
                if ($n<1 || $n>BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS
                    || $a<0 || $r<0 || $a+$r!==$n) {
                    throw new RuntimeException('safir_r73c3_previous_batch_counters_invalid');
                }
                $priorRows += $n; $priorAccepted += $a; $priorRejected += $r;
            }
            if (count($previous)+1!==$requestedChunkIndex
                || (int)$file['imported_chunk_count']!==count($previous)
                || (int)$file['accepted_rows']!==$priorAccepted
                || (int)$file['rejected_rows']!==$priorRejected
                || $priorRows >= (int)$file['source_rows']) {
                throw new RuntimeException('safir_r73c3_nonsequential_or_drifted_chunks');
            }
            if (($previous===[] && $file['state_code']!=='verified')
                || ($previous!==[] && $file['state_code']!=='importing')) {
                throw new RuntimeException('safir_r73c3_file_progress_state_inconsistent');
            }
            // Full-file verified by Stage::verify above. Open the same private
            // source and rehash after reading this bounded chunk.
            $path=$privateRoot.'/'.$stageId.'/source.csv';
            if (is_link($path) || !is_file($path)) {
                throw new RuntimeException('safir_r73c3_staged_source_unavailable');
            }
            $input=fopen($path,'rb');
            if (!is_resource($input)) {
                throw new RuntimeException('safir_r73c3_staged_source_open_failed');
            }
            $selected=null;
            try {
                foreach (BaleSafirDurableCsvChunkReader::chunks(
                    $input, $frozen, $receipt['chunk_size'], $receipt['delimiter']
                ) as $chunk) {
                    if ($chunk['index']===$requestedChunkIndex) {
                        $selected=$chunk;
                        break;
                    }
                }
            } finally {
                fclose($input);
            }
            if (!is_array($selected)
                || $selected['record_count']!==count($selected['rows'])
                || $selected['index']!==$requestedChunkIndex) {
                throw new RuntimeException('safir_r73c3_chunk_missing_or_changed');
            }
            $lastHash=hash_file('sha256',$path);
            if (!is_string($lastHash)
                || !hash_equals((string)$receipt['source_sha256'],$lastHash)) {
                throw new RuntimeException('safir_r73c3_staged_source_changed_during_import');
            }

            // The original R72B importer cannot be called here: it does not
            // persist import_file_id/chunk_index. Use the same frozen policy
            // and database uniqueness/suppression checks under campaign lock.
            $plan=BaleSafirDurableCampaignPolicy::planChunk($frozen,$selected['rows']);
            $check=$this->db->prepare(
                'SELECT id FROM safir_campaign_import_batches '
                . 'WHERE campaign_id=? AND source_sha256=? LIMIT 1'
            );
            $check->execute([$campaignId,$selected['sha256']]);
            if ($check->fetchColumn()!==false) {
                throw new RuntimeException('safir_r73c3_duplicate_chunk_sha256');
            }
            $batch=$this->db->prepare(
                'INSERT INTO safir_campaign_import_batches '
                . '(campaign_id,source_sha256,source_filename,state_code,'
                . "import_file_id,chunk_index) VALUES (?,?,?,'processing',?,?)"
            );
            $batch->execute([
                $campaignId,$selected['sha256'],$stageId.'.csv',
                $fileId,$requestedChunkIndex
            ]);
            if ($batch->rowCount()!==1 || !ctype_digit($this->db->lastInsertId())) {
                throw new RuntimeException('safir_r73c3_batch_insert_failed');
            }
            $batchId=(int)$this->db->lastInsertId();
            if ($batchId < 1) {
                throw new RuntimeException('safir_r73c3_batch_id_invalid');
            }
            $reasons=['invalid_phone'=>0,'invalid_fields'=>0,
                      'duplicate_phone'=>0,'suppressed_phone'=>0];
            foreach ($plan['skipped'] as $skipped) {
                $reason=$skipped['reason'];
                if (!array_key_exists($reason,$reasons)) {
                    throw new RuntimeException('safir_r73c3_unknown_skip_reason');
                }
                $reasons[$reason]++;
            }
            $suppressed=$this->db->prepare(
                'SELECT 1 FROM safir_suppressed_phones '
                . 'WHERE phone_normalized=? LIMIT 1'
            );
            $duplicate=$this->db->prepare(
                'SELECT 1 FROM safir_campaign_recipients '
                . 'WHERE campaign_id=? AND phone_normalized=? LIMIT 1'
            );
            $insert=$this->db->prepare(
                'INSERT INTO safir_campaign_recipients '
                . '(campaign_id,import_batch_id,source_row_number,phone_normalized,'
                . "text_fields_json,state_code) VALUES (?,?,?,?,?,'ready')"
            );
            $seen=[]; $accepted=0;
            foreach ($plan['ready'] as $row) {
                $phone=$row['phone_normalized'];
                if (isset($seen[$phone])) {
                    $reasons['duplicate_phone']++;
                    continue;
                }
                $seen[$phone]=true;
                $suppressed->execute([$phone]);
                if ($suppressed->fetchColumn()!==false) {
                    $reasons['suppressed_phone']++;
                    continue;
                }
                $duplicate->execute([$campaignId,$phone]);
                if ($duplicate->fetchColumn()!==false) {
                    $reasons['duplicate_phone']++;
                    continue;
                }
                $insert->execute([
                    $campaignId,$batchId,$row['source_row_number'],
                    $phone,$row['text_fields_json']
                ]);
                if ($insert->rowCount()!==1) {
                    throw new RuntimeException('safir_r73c3_recipient_insert_failed');
                }
                $accepted++;
            }
            $sourceRows=$selected['record_count'];
            $rejected=$sourceRows-$accepted;
            if (array_sum($reasons)!==$rejected
                || $sourceRows<1
                || $sourceRows>BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS
                || $priorRows+$sourceRows>(int)$file['source_rows']) {
                throw new RuntimeException('safir_r73c3_import_chunk_accounting_invalid');
            }
            $finish=$this->db->prepare(
                'UPDATE safir_campaign_import_batches SET '
                . "state_code='complete',source_rows=?,accepted_rows=?,"
                . 'rejected_rows=?,finished_at=NOW(6) '
                . "WHERE id=? AND campaign_id=? AND state_code='processing'"
            );
            $finish->execute([$sourceRows,$accepted,$rejected,$batchId,$campaignId]);
            if ($finish->rowCount()!==1) {
                throw new RuntimeException('safir_r73c3_batch_finalize_failed');
            }
            $advance=$this->db->prepare(
                'UPDATE safir_campaign_import_files SET '
                . "state_code='importing',imported_chunk_count=?,"
                . 'accepted_rows=?,rejected_rows=?,started_at=COALESCE(started_at,NOW(6)) '
                . 'WHERE id=? AND campaign_id=? AND state_code=? '
                . 'AND imported_chunk_count=?'
            );
            $advance->execute([
                $requestedChunkIndex,$priorAccepted+$accepted,
                $priorRejected+$rejected,$fileId,$campaignId,
                $file['state_code'],count($previous)
            ]);
            if ($advance->rowCount()!==1) {
                throw new RuntimeException('safir_r73c3_file_progress_update_failed');
            }
            return [
                'file_id'=>$fileId,'chunk_index'=>$requestedChunkIndex,
                'chunk_sha256'=>$selected['sha256'],'source_rows'=>$sourceRows,
                'accepted_rows'=>$accepted,'rejected_rows'=>$rejected,
                'reasons'=>$reasons
            ];
        });
    }

    private static function validateRequest(
        int $actorId,string $ref,string $root,string $stageId
    ): void {
        if ($actorId<1 || !preg_match('/^[a-f0-9]{32}$/D',$ref)
            || !preg_match('/^safir-r73c-file-[a-f0-9]{32}$/D',$stageId)
            || $root==='' || $root[0]!=='/') {
            throw new InvalidArgumentException('safir_r73c3_request_invalid');
        }
    }

    /** @return array{int,array<string,mixed>} */
    private function lockedCampaign(int $actorId,string $reference): array
    {
        $stmt=$this->db->prepare(
            'SELECT id,owner_user_id,state_code,template_code,'
            . 'template_sha256,template_snapshot_json '
            . 'FROM safir_campaigns WHERE public_reference=? FOR UPDATE'
        );
        $stmt->execute([$reference]);
        $campaign=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($campaign)
            || (int)$campaign['owner_user_id']!==$actorId
            || $campaign['state_code']!=='draft') {
            throw new RuntimeException('safir_r73c3_campaign_owner_or_draft_invalid');
        }
        $json=(string)$campaign['template_snapshot_json'];
        $digest=(string)$campaign['template_sha256'];
        if (!preg_match('/^[a-f0-9]{64}$/D',$digest)
            || !hash_equals($digest,hash('sha256',$json))) {
            throw new RuntimeException('safir_r73c3_frozen_template_hash_invalid');
        }
        $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('safir_r73c3_frozen_template_not_object');
        }
        $frozen=BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($decoded);
        if (!hash_equals($digest,$frozen['sha256'])
            || $campaign['template_code']!==$frozen['code']) {
            throw new RuntimeException('safir_r73c3_frozen_template_changed');
        }
        $id=(int)$campaign['id'];
        if ($id<1) {
            throw new RuntimeException('safir_r73c3_campaign_id_invalid');
        }
        return [$id,$frozen];
    }

    private function validateReceipt(array $receipt): void
    {
        if (($receipt['version']??null)!=='safir_csv_file_receipt_v1'
            || !is_int($receipt['chunk_count']??null)
            || $receipt['chunk_count']<1
            || !is_int($receipt['source_rows']??null)
            || $receipt['source_rows']<1
            || !is_int($receipt['source_bytes']??null)
            || $receipt['source_bytes']<1
            || ($receipt['chunk_count']>$receipt['source_rows'])
            || !is_int($receipt['preliminary_ready_rows']??null)
            || !is_int($receipt['preliminary_skipped_rows']??null)
            || $receipt['preliminary_ready_rows']+$receipt['preliminary_skipped_rows']!==$receipt['source_rows']) {
            throw new RuntimeException('safir_r73c3_csv_file_receipt_invalid');
        }
        foreach (['source_sha256','template_sha256','chunk_manifest_sha256'] as $key) {
            if (!is_string($receipt[$key]??null)
                || !preg_match('/^[a-f0-9]{64}$/D',$receipt[$key])) {
                throw new RuntimeException('safir_r73c3_file_hash_invalid');
            }
        }
    }

    private static function matchFile(array $file,array $receipt): void
    {
        foreach ([
            'source_sha256'=>'source_sha256',
            'template_sha256'=>'template_sha256',
            'chunk_manifest_sha256'=>'chunk_manifest_sha256',
            'source_bytes'=>'source_bytes',
            'source_rows'=>'source_rows',
            'expected_chunk_count'=>'chunk_count',
            'preliminary_ready_rows'=>'preliminary_ready_rows',
            'preliminary_skipped_rows'=>'preliminary_skipped_rows',
        ] as $col=>$key) {
            if (!array_key_exists($col,$file)
                || !array_key_exists($key,$receipt)
                || (string)$file[$col] !== (string)$receipt[$key]) {
                throw new RuntimeException('safir_r73c3_file_source_snapshot_mismatch');
            }
        }
    }

    private function transaction(callable $job): mixed
    {
        if ($this->db->inTransaction()) {
            throw new RuntimeException('safir_r73c3_outer_transaction_forbidden');
        }
        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('safir_r73c3_begin_transaction_failed');
        }
        try {
            $answer=$job();
            if (!$this->db->commit()) {
                throw new RuntimeException('safir_r73c3_commit_failed');
            }
            return $answer;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
