<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * SAFIR R73C4B — standalone, non-approving, read-only PDO attestation.
 *
 * NOT registered to routes, worker, cron, production or provider. No database
 * UPDATE/INSERT/DELETE/DDL statements. Locks the campaign row (FOR UPDATE)
 * to coordinate with existing campaign-first import writers, then reads all
 * SAFIR rows from one REPEATABLE READ transaction. Stage::verify and a second
 * complete read of every private CSV check per-chunk DB identity and offsets.
 *
 * This is an owner's diagnostic readiness check, NOT canonical approver RBAC,
 * approval signature, authorization token or permission to send.
 */
final class BaleSafirDurableCampaignReadinessRepository
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @return array{file_count:int,chunk_count:int,source_rows:int,ready_rows:int,rejected_rows:int,state:string}
     */
    public function attestForOwner(
        int $actorId,
        string $campaignReference,
        string $privateRoot
    ): array {
        if ($actorId < 1 || !preg_match('/^[a-f0-9]{32}$/D', $campaignReference)
            || $privateRoot === '' || $privateRoot[0] !== '/') {
            throw new InvalidArgumentException('safir_r73c4b_invalid_request');
        }
        if ($this->db->inTransaction()) {
            throw new RuntimeException('safir_r73c4b_unexpected_outer_transaction');
        }

        // No committed mutations. Select a single consistent snapshot while
        // serializing with the original R72B / R73C3B campaign-first writers.
        $this->db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('safir_r73c4b_snapshot_unavailable');
        }
        try {
            $q = $this->db->prepare(
                'SELECT id,owner_user_id,state_code,approved_at,approved_by_user_id,'
                .'template_code,template_sha256,template_snapshot_json '
                .'FROM safir_campaigns WHERE public_reference=? FOR UPDATE'
            );
            $q->execute([$campaignReference]);
            $campaign=$q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($campaign)
                || (int)$campaign['owner_user_id'] !== $actorId
                || (int)$campaign['id'] < 1) {
                throw new RuntimeException('safir_r73c4b_owner_campaign_unavailable');
            }
            $campaignId=(int)$campaign['id'];
            $files=$this->rows(
                'SELECT id,campaign_id,stage_id,source_sha256,template_sha256,'
                .'chunk_manifest_sha256,source_bytes,source_rows,'
                .'expected_chunk_count,preliminary_ready_rows,'
                .'preliminary_skipped_rows,state_code,imported_chunk_count,'
                .'accepted_rows,rejected_rows,verified_at,started_at,'
                .'completed_at,failed_at,failure_code '
                .'FROM safir_campaign_import_files WHERE campaign_id=? ORDER BY id',
                [$campaignId]
            );
            $batches=$this->rows(
                'SELECT id,campaign_id,import_file_id,chunk_index,'
                .'source_sha256,state_code,source_rows,accepted_rows,'
                .'rejected_rows,finished_at '
                .'FROM safir_campaign_import_batches WHERE campaign_id=? '
                .'ORDER BY id',
                [$campaignId]
            );
            $groups=$this->rows(
                'SELECT import_batch_id,COUNT(*) AS total,'
                ."SUM(CASE WHEN state_code='ready' THEN 1 ELSE 0 END) AS ready,"
                .'SUM(CASE WHEN request_id IS NOT NULL THEN 1 ELSE 0 END) '
                .'AS request_ids_assigned,'
                .'SUM(CASE WHEN attempt_count <> 0 OR attempted_at IS NOT NULL '
                .'OR sent_at IS NOT NULL OR provider_message_id IS NOT NULL '
                .'OR provider_http_code IS NOT NULL '
                .'OR provider_error_code IS NOT NULL OR last_error IS NOT NULL '
                .'THEN 1 ELSE 0 END) AS delivery_metadata_assigned,'
                .'MIN(source_row_number) AS first_row,'
                .'MAX(source_row_number) AS last_row,'
                .'COUNT(DISTINCT source_row_number) AS unique_rows '
                .'FROM safir_campaign_recipients WHERE campaign_id=? '
                .'GROUP BY import_batch_id',
                [$campaignId]
            );
            $recipientStats=[];
            foreach ($groups as $group) {
                $batchId=(int)$group['import_batch_id'];
                if ($batchId < 1 || array_key_exists($batchId,$recipientStats)) {
                    throw new RuntimeException('safir_r73c4b_duplicate_recipient_group');
                }
                $recipientStats[$batchId]=$group;
            }
            foreach ($batches as $batch) {
                $batchId=(int)$batch['id'];
                if ($batchId < 1) {
                    throw new RuntimeException('safir_r73c4b_invalid_batch_id');
                }
                if (!array_key_exists($batchId,$recipientStats)) {
                    $recipientStats[$batchId]=[
                        'total'=>0,'ready'=>0,'request_ids_assigned'=>0,
                        'delivery_metadata_assigned'=>0,
                        'first_row'=>null,'last_row'=>null,'unique_rows'=>0
                    ];
                }
            }
            $attempts=(int)$this->scalar(
                'SELECT COUNT(*) FROM safir_campaign_send_attempts WHERE campaign_id=?',
                [$campaignId]
            );
            $nowSuppressed=(int)$this->scalar(
                'SELECT COUNT(*) FROM safir_campaign_recipients r '
                .'INNER JOIN safir_suppressed_phones s '
                .'ON s.phone_normalized=r.phone_normalized '
                ."WHERE r.campaign_id=? AND r.state_code='ready'",
                [$campaignId]
            );

            $summary=BaleSafirDurableCampaignImportReadinessPolicy::requireCompleteImport(
                $campaign,$files,$batches,$recipientStats,$attempts,$nowSuppressed
            );

            // All linked chunk identities must match actual source-file chunks.
            // No reliance solely on stored count/hash values or earlier receipt.
            $json=(string)$campaign['template_snapshot_json'];
            $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
            $frozen=BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($decoded);
            $perFile=[];
            foreach ($batches as $batch) {
                $fileId=(int)$batch['import_file_id'];
                $index=(int)$batch['chunk_index'];
                if (isset($perFile[$fileId][$index])) {
                    throw new RuntimeException('safir_r73c4b_chunk_collision');
                }
                $perFile[$fileId][$index]=$batch;
            }
            foreach ($files as $file) {
                $fileId=(int)$file['id'];
                $stageId=(string)$file['stage_id'];
                $receipt=BaleSafirDurableCsvPrivateStage::verify(
                    $privateRoot,$stageId,$frozen
                );
                foreach ([
                    'source_sha256'=>'source_sha256',
                    'template_sha256'=>'template_sha256',
                    'chunk_manifest_sha256'=>'chunk_manifest_sha256',
                    'source_bytes'=>'source_bytes',
                    'source_rows'=>'source_rows',
                    'expected_chunk_count'=>'chunk_count',
                    'preliminary_ready_rows'=>'preliminary_ready_rows',
                    'preliminary_skipped_rows'=>'preliminary_skipped_rows'
                ] as $col=>$key) {
                    if (!array_key_exists($key,$receipt)
                        || (string)$file[$col] !== (string)$receipt[$key]) {
                        throw new RuntimeException('safir_r73c4b_staged_receipt_db_drift');
                    }
                }
                $path=$privateRoot.'/'.$stageId.'/source.csv';
                if (is_link($path) || !is_file($path)) {
                    throw new RuntimeException('safir_r73c4b_private_csv_missing');
                }
                $fh=fopen($path,'rb');
                if (!is_resource($fh)) {
                    throw new RuntimeException('safir_r73c4b_private_csv_unreadable');
                }
                $seen=0;
                try {
                    foreach (BaleSafirDurableCsvChunkReader::chunks(
                        $fh,$frozen,$receipt['chunk_size'],$receipt['delimiter']
                    ) as $chunk) {
                        $index=$chunk['index'];
                        $dbChunk=$perFile[$fileId][$index] ?? null;
                        if (!is_array($dbChunk)
                            || !hash_equals((string)$dbChunk['source_sha256'],
                                            (string)$chunk['sha256'])
                            || (int)$dbChunk['source_rows']!==$chunk['record_count']) {
                            throw new RuntimeException('safir_r73c4b_chunk_source_db_mismatch');
                        }
                        $stat=$recipientStats[(int)$dbChunk['id']];
                        $actual=(int)$stat['total'];
                        if (($actual > 0)
                            && ((int)$stat['first_row'] < $chunk['first_row']
                                || (int)$stat['last_row'] > $chunk['last_row']
                                || (int)$stat['unique_rows'] !== $actual)) {
                            throw new RuntimeException('safir_r73c4b_recipient_source_row_invalid');
                        }
                        $seen++;
                    }
                } finally {
                    fclose($fh);
                }
                $finalHash=hash_file('sha256',$path);
                if ($seen!==(int)$file['expected_chunk_count']
                    || !is_string($finalHash)
                    || !hash_equals((string)$receipt['source_sha256'],$finalHash)) {
                    throw new RuntimeException('safir_r73c4b_source_changed_or_incomplete');
                }
            }
            // No state mutations; commit only closes the read transaction.
            if (!$this->db->commit()) {
                throw new RuntimeException('safir_r73c4b_snapshot_close_failed');
            }
            return $summary+['state'=>'import_ready_not_approved'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql,array $params): array {
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($result)) {
            throw new RuntimeException('safir_r73c4b_query_result_unavailable');
        }
        return $result;
    }

    private function scalar(string $sql,array $params): mixed {
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
