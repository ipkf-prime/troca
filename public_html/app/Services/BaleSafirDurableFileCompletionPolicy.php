<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/**
 * SAFIR R73C3A — pure file terminal-accounting contract.
 *
 * No SQL, sends, routes, cron, callbacks or production integration.
 * A complete file requires precisely N committed, ordered, completed batches,
 * exact per-batch recipient counts, exact file/source counters, and no gaps.
 * Call only under a campaign-level database row lock in the future repository.
 */
final class BaleSafirDurableFileCompletionPolicy
{
    /**
     * @param array<string,mixed> $file Locked file receipt; state_code=importing
     * @param list<array<string,mixed>> $batches Locked DB rows (untrusted until checked).
     * @param array<int,int> $recipientCounts Actual recipient rows keyed by batch ID.
     * @return array{imported_chunk_count:int,source_rows:int,accepted_rows:int,rejected_rows:int}
     */
    public static function requireExactCompletion(
        array $file,
        array $batches,
        array $recipientCounts
    ): array {
        $fileId = self::positive($file['id'] ?? null, 'file_id');
        $campaignId = self::positive($file['campaign_id'] ?? null, 'campaign_id');
        $sourceRows = self::positive($file['source_rows'] ?? null, 'source_rows');
        $chunkCount = self::positive($file['expected_chunk_count'] ?? null, 'expected_chunk_count');
        $prelimReady = self::nonnegative($file['preliminary_ready_rows'] ?? null, 'preliminary_ready_rows');
        $prelimSkipped = self::nonnegative($file['preliminary_skipped_rows'] ?? null, 'preliminary_skipped_rows');
        if (($file['state_code'] ?? null) !== 'importing'
            || ($file['completed_at'] ?? null) !== null
            || ($file['failed_at'] ?? null) !== null
            || $prelimReady > $sourceRows
            || $prelimSkipped > $sourceRows
            || $prelimReady + $prelimSkipped !== $sourceRows) {
            throw new RuntimeException('safir_r73c3_file_receipt_not_importing');
        }
        if (count($batches) !== $chunkCount || $chunkCount > $sourceRows) {
            throw new RuntimeException('safir_r73c3_incomplete_file_chunk_count');
        }
        $used = [];
        $expectedIndex = 1;
        $sumSource = 0;
        $sumAccepted = 0;
        $sumRejected = 0;
        foreach ($batches as $batch) {
            if (!is_array($batch)
                || self::positive($batch['campaign_id'] ?? null, 'batch_campaign_id') !== $campaignId
                || self::positive($batch['import_file_id'] ?? null, 'batch_file_id') !== $fileId
                || self::positive($batch['chunk_index'] ?? null, 'chunk_index') !== $expectedIndex++
                || ($batch['state_code'] ?? null) !== 'complete'
                || empty($batch['finished_at'])) {
                throw new RuntimeException('safir_r73c3_batch_not_complete_or_not_contiguous');
            }
            $batchId = self::positive($batch['id'] ?? null, 'batch_id');
            if (isset($used[$batchId])) {
                throw new RuntimeException('safir_r73c3_repeated_batch_id');
            }
            $used[$batchId] = true;
            $rows = self::positive($batch['source_rows'] ?? null, 'batch_source_rows');
            if ($rows > BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS) {
                throw new RuntimeException('safir_r73c3_batch_size_exceeds_limit');
            }
            $accepted = self::nonnegative($batch['accepted_rows'] ?? null, 'batch_accepted_rows');
            $rejected = self::nonnegative($batch['rejected_rows'] ?? null, 'batch_rejected_rows');
            if ($accepted + $rejected !== $rows
                || !array_key_exists($batchId, $recipientCounts)
                || $recipientCounts[$batchId] !== $accepted) {
                throw new RuntimeException('safir_r73c3_batch_recipient_accounting_mismatch');
            }
            $sumSource += $rows;
            $sumAccepted += $accepted;
            $sumRejected += $rejected;
            if ($sumSource > $sourceRows || $sumAccepted > $sourceRows || $sumRejected > $sourceRows) {
                throw new RuntimeException('safir_r73c3_file_counters_overflow');
            }
        }
        if (count($recipientCounts) !== count($used)) {
            throw new RuntimeException('safir_r73c3_recipient_count_keys_mismatch');
        }
        foreach ($recipientCounts as $id => $number) {
            if (!is_int($id) || !isset($used[$id]) || !is_int($number) || $number < 0) {
                throw new RuntimeException('safir_r73c3_invalid_recipient_count');
            }
        }
        if ($sumSource !== $sourceRows
            || $sumAccepted + $sumRejected !== $sourceRows
            || $sumAccepted > $prelimReady
            || $sumRejected < $prelimSkipped) {
            throw new RuntimeException('safir_r73c3_file_totals_do_not_match_source');
        }

        return [
            'imported_chunk_count' => count($batches),
            'source_rows' => $sumSource,
            'accepted_rows' => $sumAccepted,
            'rejected_rows' => $sumRejected,
        ];
    }

    private static function positive(mixed $value, string $field): int
    {
        $int = self::nonnegative($value, $field);
        if ($int === 0) {
            throw new InvalidArgumentException('safir_r73c3_positive_required_' . $field);
        }
        return $int;
    }

    private static function nonnegative(mixed $value, string $field): int
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('safir_r73c3_negative_' . $field);
            }
            return $value;
        }
        if (is_string($value) && preg_match('/^(?:0|[1-9][0-9]*)$/D', $value)
            && strlen($value) <= strlen((string) PHP_INT_MAX)
            && ($value < PHP_INT_MAX || strlen($value) < strlen((string) PHP_INT_MAX))) {
            $int = (int) $value;
            if ($int >= 0 && (string) $int === $value) {
                return $int;
            }
        }
        throw new InvalidArgumentException('safir_r73c3_counter_invalid_' . $field);
    }
}
