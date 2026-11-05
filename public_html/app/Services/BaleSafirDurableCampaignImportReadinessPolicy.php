<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * SAFIR R73C4A — PURE, fail-closed whole-campaign IMPORT READINESS assessment.
 *
 * Source-only; NOT an approval, campaign state transition, send permission,
 * provider worker, migration, route or deployment.
 *
 * Caller MUST load every file/batch and real recipient counts from one
 * transactionally consistent DB snapshot, must rehash all private file stages,
 * must perform current canonical approver RBAC and recheck suppressions again
 * immediately before any future queue/send. A PASS here alone authorizes NO
 * campaign transition or HTTP message.
 */
final class BaleSafirDurableCampaignImportReadinessPolicy
{
    /**
     * @param array<string,mixed> $campaign canonical persisted campaign row
     * @param list<array<string,mixed>> $files all persisted campaign import-file receipts
     * @param list<array<string,mixed>> $batches all persisted campaign import batches,
     *                                         including legacy/unlinked batches
     * @param array<int,array<string,mixed>> $recipientStats indexed by batch_id,
     *                                         must include zero-accepted batches
     * @return array{file_count:int,chunk_count:int,source_rows:int,ready_rows:int,rejected_rows:int}
     */
    public static function requireCompleteImport(
        array $campaign,
        array $files,
        array $batches,
        array $recipientStats,
        int $sendAttemptCount,
        int $currentlySuppressedReadyRecipients
    ): array {
        $campaignId=self::positive($campaign['id'] ?? null,'campaign_id');
        self::positive($campaign['owner_user_id'] ?? null,'owner_user_id');
        if (($campaign['state_code'] ?? null) !== 'draft'
            || ($campaign['approved_at'] ?? null) !== null
            || ($campaign['approved_by_user_id'] ?? null) !== null) {
            throw new RuntimeException('safir_r73c4_campaign_not_unapproved_draft');
        }
        if ($sendAttemptCount !== 0 || $currentlySuppressedReadyRecipients !== 0) {
            throw new RuntimeException('safir_r73c4_preapproval_delivery_or_suppression_exists');
        }
        $json=$campaign['template_snapshot_json'] ?? null;
        $expectedHash=$campaign['template_sha256'] ?? null;
        if (!is_string($json) || !is_string($expectedHash)
            || !preg_match('/^[a-f0-9]{64}$/D',$expectedHash)
            || !hash_equals($expectedHash,hash('sha256',$json))) {
            throw new RuntimeException('safir_r73c4_campaign_template_hash_changed');
        }
        $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('safir_r73c4_campaign_template_invalid');
        }
        $frozen=BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($decoded);
        if (!hash_equals($frozen['sha256'],$expectedHash)
            || ($campaign['template_code'] ?? null) !== $frozen['code']) {
            throw new RuntimeException('safir_r73c4_campaign_template_policy_changed');
        }

        if ($files === [] || $batches === []) {
            throw new RuntimeException('safir_r73c4_no_completed_file_import');
        }
        $fileById=[];
        $stageIds=[];
        $sourceDigests=[];
        $expectedTotalChunks=0;
        $fileSource=0;
        $fileAccepted=0;
        $fileRejected=0;
        foreach ($files as $file) {
            $fileId=self::positive($file['id'] ?? null,'file_id');
            if (isset($fileById[$fileId])
                || self::positive($file['campaign_id'] ?? null,'file_campaign_id')!==$campaignId
                || ($file['state_code'] ?? null)!=='complete'
                || empty($file['completed_at'])
                || empty($file['verified_at'])
                || ($file['failed_at'] ?? null)!==null
                || ($file['failure_code'] ?? null)!==null
                || empty($file['started_at'])) {
                throw new RuntimeException('safir_r73c4_file_incomplete_or_wrong_campaign');
            }
            $stageId=$file['stage_id'] ?? null;
            $sourceHash=$file['source_sha256'] ?? null;
            $templateHash=$file['template_sha256'] ?? null;
            $chunkManifest=$file['chunk_manifest_sha256'] ?? null;
            if (!is_string($stageId)
                || !preg_match('/^safir-r73c-file-[a-f0-9]{32}$/D',$stageId)
                || isset($stageIds[$stageId])
                || !is_string($sourceHash)
                || !preg_match('/^[a-f0-9]{64}$/D',$sourceHash)
                || isset($sourceDigests[$sourceHash])
                || !is_string($templateHash)
                || !hash_equals($expectedHash,$templateHash)
                || !is_string($chunkManifest)
                || !preg_match('/^[a-f0-9]{64}$/D',$chunkManifest)) {
                throw new RuntimeException('safir_r73c4_file_immutable_identity_invalid');
            }
            $stageIds[$stageId]=true;
            $sourceDigests[$sourceHash]=true;
            self::positive($file['source_bytes'] ?? null,'file_source_bytes');
            $sourceRows=self::positive($file['source_rows'] ?? null,'file_source_rows');
            $chunks=self::positive($file['expected_chunk_count'] ?? null,'file_chunk_count');
            $imported=self::nonnegative($file['imported_chunk_count'] ?? null,'file_imported_count');
            $accepted=self::nonnegative($file['accepted_rows'] ?? null,'file_accepted');
            $rejected=self::nonnegative($file['rejected_rows'] ?? null,'file_rejected');
            $preReady=self::nonnegative($file['preliminary_ready_rows'] ?? null,'file_prelim_ready');
            $preSkipped=self::nonnegative($file['preliminary_skipped_rows'] ?? null,'file_prelim_skipped');
            if ($chunks>$sourceRows || $imported!==$chunks
                || $accepted+$rejected!==$sourceRows
                || $preReady+$preSkipped!==$sourceRows
                || $accepted>$preReady || $rejected<$preSkipped) {
                throw new RuntimeException('safir_r73c4_file_terminal_counters_invalid');
            }
            $fileById[$fileId]=[
                'chunks'=>$chunks, 'source_rows'=>$sourceRows,
                'accepted'=>$accepted, 'rejected'=>$rejected,
                'batchCount'=>0,'batchSource'=>0,'batchAccepted'=>0,'batchRejected'=>0,
                'indices'=>[],
            ];
            $expectedTotalChunks=self::sum($expectedTotalChunks,$chunks);
            $fileSource=self::sum($fileSource,$sourceRows);
            $fileAccepted=self::sum($fileAccepted,$accepted);
            $fileRejected=self::sum($fileRejected,$rejected);
        }
        if (count($batches)!==$expectedTotalChunks) {
            throw new RuntimeException('safir_r73c4_campaign_batch_count_mismatch');
        }
        $batchIds=[];
        $chunkDigests=[];
        $actualTotal=0;
        foreach ($batches as $batch) {
            $batchId=self::positive($batch['id'] ?? null,'batch_id');
            if (isset($batchIds[$batchId])
                || self::positive($batch['campaign_id'] ?? null,'batch_campaign_id')!==$campaignId
                || ($batch['state_code'] ?? null)!=='complete'
                || empty($batch['finished_at'])) {
                throw new RuntimeException('safir_r73c4_batch_incomplete_or_wrong_campaign');
            }
            $batchIds[$batchId]=true;
            // Explicitly REJECT old R72B unlinked batches even when otherwise
            // complete or counted as ready recipients.
            $fileId=self::positive($batch['import_file_id'] ?? null,'batch_import_file_id');
            if (!array_key_exists($fileId,$fileById)) {
                throw new RuntimeException('safir_r73c4_batch_points_outside_completed_files');
            }
            $index=self::positive($batch['chunk_index'] ?? null,'batch_chunk_index');
            if ($index>$fileById[$fileId]['chunks']
                || isset($fileById[$fileId]['indices'][$index])) {
                throw new RuntimeException('safir_r73c4_duplicate_or_out_of_range_chunk');
            }
            $fileById[$fileId]['indices'][$index]=true;
            $chunkHash=$batch['source_sha256'] ?? null;
            if (!is_string($chunkHash)
                || !preg_match('/^[a-f0-9]{64}$/D',$chunkHash)
                || isset($chunkDigests[$chunkHash])) {
                throw new RuntimeException('safir_r73c4_duplicate_or_invalid_chunk_hash');
            }
            $chunkDigests[$chunkHash]=true;
            $src=self::positive($batch['source_rows'] ?? null,'batch_source');
            $accept=self::nonnegative($batch['accepted_rows'] ?? null,'batch_accepted');
            $reject=self::nonnegative($batch['rejected_rows'] ?? null,'batch_rejected');
            if ($src>BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS
                || $accept+$reject!==$src) {
                throw new RuntimeException('safir_r73c4_batch_accounting_invalid');
            }
            $statistics=$recipientStats[$batchId] ?? null;
            if (!is_array($statistics)) {
                throw new RuntimeException('safir_r73c4_recipient_stats_missing');
            }
            $total=self::nonnegative($statistics['total'] ?? null,'recipients_total');
            $ready=self::nonnegative($statistics['ready'] ?? null,'recipients_ready');
            $assigned=self::nonnegative($statistics['request_ids_assigned'] ?? null,'request_ids_assigned');
            if ($total!==$accept || $ready!==$total || $assigned!==0) {
                throw new RuntimeException('safir_r73c4_recipient_count_or_state_invalid');
            }
            $actualTotal=self::sum($actualTotal,$total);
            $fileById[$fileId]['batchCount']++;
            $fileById[$fileId]['batchSource']=self::sum($fileById[$fileId]['batchSource'],$src);
            $fileById[$fileId]['batchAccepted']=self::sum($fileById[$fileId]['batchAccepted'],$accept);
            $fileById[$fileId]['batchRejected']=self::sum($fileById[$fileId]['batchRejected'],$reject);
        }
        if (count($recipientStats)!==count($batchIds)) {
            throw new RuntimeException('safir_r73c4_unexpected_recipient_batch_group');
        }
        foreach ($recipientStats as $batchId=>$statistics) {
            if (!is_int($batchId) || !isset($batchIds[$batchId])) {
                throw new RuntimeException('safir_r73c4_unlinked_recipient_group');
            }
        }
        foreach ($fileById as $file) {
            if ($file['batchCount']!==$file['chunks']
                || $file['batchSource']!==$file['source_rows']
                || $file['batchAccepted']!==$file['accepted']
                || $file['batchRejected']!==$file['rejected']) {
                throw new RuntimeException('safir_r73c4_cross_table_file_totals_mismatch');
            }
            for ($index=1;$index<=$file['chunks'];$index++) {
                if (!isset($file['indices'][$index])) {
                    throw new RuntimeException('safir_r73c4_missing_chunk');
                }
            }
        }
        if ($actualTotal===0 || $actualTotal!==$fileAccepted) {
            throw new RuntimeException('safir_r73c4_no_eligible_real_recipients');
        }
        return [
            'file_count'=>count($files),
            'chunk_count'=>$expectedTotalChunks,
            'source_rows'=>$fileSource,
            'ready_rows'=>$actualTotal,
            'rejected_rows'=>$fileRejected,
        ];
    }

    private static function sum(int $x,int $y): int {
        if ($x>PHP_INT_MAX-$y) {
            throw new RuntimeException('safir_r73c4_integer_overflow');
        }
        return $x+$y;
    }
    private static function positive(mixed $value,string $label): int {
        $n=self::nonnegative($value,$label);
        if ($n<1) throw new InvalidArgumentException('safir_r73c4_positive_required_'.$label);
        return $n;
    }
    private static function nonnegative(mixed $value,string $label): int {
        if (is_int($value) && $value>=0) return $value;
        if (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/D',$value)
            && strlen($value)<=strlen((string)PHP_INT_MAX)
            && (strlen($value)<strlen((string)PHP_INT_MAX)
                || strcmp($value,(string)PHP_INT_MAX)<=0)) {
            return (int)$value;
        }
        throw new InvalidArgumentException('safir_r73c4_nonnegative_counter_invalid_'.$label);
    }
}
