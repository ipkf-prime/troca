<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * SAFIR R73C3A: disconnected, database-injected transactional terminalization.
 * No routes, approval, worker, provider or production deployment.
 * Requires independently migrated R73C2 schema and campaign-first row locks
 * in every participating import writer.
 */
final class BaleSafirDurableFileCompletionRepository
{
    public function __construct(private readonly PDO $db) {}

    /** @return array<string,int> */
    public function completeForOwner(
        int $actorId, string $reference, string $privateRoot, string $stageId
    ): array {
        if ($actorId < 1 || !preg_match('/^[a-f0-9]{32}$/D', $reference)
            || !preg_match('/^safir-r73c-file-[a-f0-9]{32}$/D', $stageId)
            || $privateRoot === '') {
            throw new InvalidArgumentException('safir_r73c3_invalid_request');
        }
        if ($this->db->inTransaction()) {
            throw new RuntimeException('safir_r73c3_outer_transaction_forbidden');
        }
        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('safir_r73c3_transaction_unavailable');
        }
        try {
            // Serialize against file chunk writers on the campaign row.
            $q = $this->db->prepare(
                'SELECT id,owner_user_id,state_code,template_code,'
                .'template_sha256,template_snapshot_json FROM safir_campaigns '
                .'WHERE public_reference=? FOR UPDATE'
            );
            $q->execute([$reference]);
            $campaign = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($campaign) ||
                (int)$campaign['owner_user_id'] !== $actorId ||
                $campaign['state_code'] !== 'draft') {
                throw new RuntimeException('safir_r73c3_owner_draft_guard');
            }
            $id = (int)$campaign['id'];
            if ($id < 1) {
                throw new RuntimeException('safir_r73c3_campaign_id_invalid');
            }
            $json = (string)$campaign['template_snapshot_json'];
            $hash = (string)$campaign['template_sha256'];
            if (!preg_match('/^[a-f0-9]{64}$/D', $hash) ||
                !hash_equals($hash, hash('sha256', $json))) {
                throw new RuntimeException('safir_r73c3_template_changed');
            }
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new RuntimeException('safir_r73c3_template_invalid');
            }
            $frozen = BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($decoded);
            if (!hash_equals($frozen['sha256'], $hash) ||
                $campaign['template_code'] !== $frozen['code']) {
                throw new RuntimeException('safir_r73c3_template_freeze_invalid');
            }
            // Rehash private CSV and verify its full chunk manifest.
            $receipt = BaleSafirDurableCsvPrivateStage::verify(
                $privateRoot, $stageId, $frozen
            );
            $q = $this->db->prepare(
                'SELECT id,campaign_id,state_code,completed_at,failed_at,'
                .'source_sha256,template_sha256,chunk_manifest_sha256,'
                .'source_bytes,source_rows,expected_chunk_count,'
                .'preliminary_ready_rows,preliminary_skipped_rows '
                .'FROM safir_campaign_import_files '
                .'WHERE campaign_id=? AND stage_id=? FOR UPDATE'
            );
            $q->execute([$id, $stageId]);
            $file = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($file)) {
                throw new RuntimeException('safir_r73c3_receipt_not_found');
            }
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
                if (!isset($receipt[$key]) ||
                    (string)$file[$col] !== (string)$receipt[$key]) {
                    throw new RuntimeException('safir_r73c3_receipt_source_mismatch');
                }
            }
            $q = $this->db->prepare(
                'SELECT id,campaign_id,import_file_id,chunk_index,state_code,'
                .'source_rows,accepted_rows,rejected_rows,finished_at '
                .'FROM safir_campaign_import_batches '
                .'WHERE campaign_id=? AND import_file_id=? '
                .'ORDER BY chunk_index,id FOR UPDATE'
            );
            $fileId = (int)$file['id'];
            $q->execute([$id,$fileId]);
            $batches = $q->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($batches)) {
                throw new RuntimeException('safir_r73c3_batches_unavailable');
            }
            $q = $this->db->prepare(
                'SELECT r.import_batch_id,COUNT(*) AS recipient_count '
                .'FROM safir_campaign_recipients r '
                .'INNER JOIN safir_campaign_import_batches b '
                .'ON b.id=r.import_batch_id AND b.campaign_id=r.campaign_id '
                .'WHERE b.campaign_id=? AND b.import_file_id=? '
                .'GROUP BY r.import_batch_id'
            );
            $q->execute([$id,$fileId]);
            $counts = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $batchId = (int)$row['import_batch_id'];
                if ($batchId < 1 || isset($counts[$batchId])) {
                    throw new RuntimeException('safir_r73c3_recipient_aggregation_invalid');
                }
                $counts[$batchId] = (int)$row['recipient_count'];
            }
            foreach ($batches as $batch) {
                $batchId = (int)$batch['id'];
                if (!array_key_exists($batchId,$counts)) {
                    $counts[$batchId]=0;
                }
            }
            $sum = BaleSafirDurableFileCompletionPolicy::requireExactCompletion(
                $file, $batches, $counts
            );
            $q = $this->db->prepare(
                "UPDATE safir_campaign_import_files SET state_code='complete',"
                .'completed_at=NOW(6),imported_chunk_count=?,'
                .'accepted_rows=?,rejected_rows=? '
                ."WHERE id=? AND campaign_id=? AND state_code='importing' "
                .'AND completed_at IS NULL'
            );
            $q->execute([
                $sum['imported_chunk_count'],$sum['accepted_rows'],
                $sum['rejected_rows'],$fileId,$id
            ]);
            if ($q->rowCount() !== 1) {
                throw new RuntimeException('safir_r73c3_terminal_update_failed');
            }
            if (!$this->db->commit()) {
                throw new RuntimeException('safir_r73c3_commit_failed');
            }
            return $sum;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
