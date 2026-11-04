<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * SAFIR R73B: source-only full-file CSV verification receipt.
 * Never connects to a DB, authorizes a send, or writes an import batch.
 * A receipt describes the bytes verified now; it is NOT an approval token.
 */
final class BaleSafirDurableCsvFileReceipt
{
    /**
     * @param resource $stream readable, seekable binary stream positioned at 0
     * @param array $frozen R72A frozen approved dynamic template
     * @return array<string,int|string>
     */
    public static function inspect($stream, array $frozen, int $chunkSize = 500, string $delimiter = ','): array
    {
        self::assertStream($stream);
        $snapshot = $frozen['snapshot_json'] ?? null;
        $declaredHash = $frozen['sha256'] ?? null;
        if (!is_string($snapshot) || !is_string($declaredHash)
            || !preg_match('/^[a-f0-9]{64}$/D', $declaredHash)
            || !hash_equals(hash('sha256', $snapshot), $declaredHash)) {
            throw new InvalidArgumentException('safir_csv_receipt_template_hash_invalid');
        }
        try {
            $live = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('safir_csv_receipt_snapshot_invalid', 0, $exception);
        }
        if (!is_array($live)) {
            throw new InvalidArgumentException('safir_csv_receipt_snapshot_invalid');
        }
        $reFrozen = BaleSafirDurableCampaignPolicy::freezeApprovedTemplate($live);
        if ($reFrozen !== $frozen) {
            throw new InvalidArgumentException('safir_csv_receipt_template_mismatch');
        }
        if ($chunkSize < 1 || $chunkSize > BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS
            || !in_array($delimiter, [',', ';'], true)) {
            throw new InvalidArgumentException('safir_csv_receipt_options_invalid');
        }

        // Hash the entire file before parsing; the full file will be hashed
        // again after parsing to detect in-place changes during validation.
        $begin = self::stat($stream);
        [$firstDigest, $size] = self::fingerprint($stream);
        if ($size !== $begin['size']) {
            throw new InvalidArgumentException('safir_csv_receipt_source_changed');
        }
        self::rewind($stream);

        $manifest = hash_init('sha256');
        hash_update($manifest, "IPKF-SAFIR-CSV-FILE-RECEIPT-V1\0");
        hash_update($manifest, hex2bin($declaredHash));
        hash_update($manifest, $delimiter);
        hash_update($manifest, pack('N', $chunkSize));
        $chunks = 0;
        $sourceRows = 0;
        $preliminaryReady = 0;
        $preliminarySkipped = 0;
        foreach (BaleSafirDurableCsvChunkReader::chunks($stream, $frozen, $chunkSize, $delimiter) as $chunk) {
            $plan = BaleSafirDurableCampaignPolicy::planChunk($frozen, $chunk['rows']);
            if ($chunk['index'] !== $chunks + 1
                || $chunk['record_count'] !== $plan['ready_count'] + $plan['skipped_count']
                || $chunk['first_row'] !== $sourceRows + 2
                || $chunk['last_row'] !== $sourceRows + $chunk['record_count'] + 1) {
                throw new InvalidArgumentException('safir_csv_receipt_sequence_mismatch');
            }
            $chunks++;
            $sourceRows += $chunk['record_count'];
            $preliminaryReady += $plan['ready_count'];
            $preliminarySkipped += $plan['skipped_count'];
            hash_update($manifest, pack('N', $chunk['index']));
            hash_update($manifest, hex2bin($chunk['sha256']));
            hash_update($manifest, pack('N', $chunk['record_count']));
            hash_update($manifest, pack('N', $plan['ready_count']));
            hash_update($manifest, pack('N', $plan['skipped_count']));
            unset($chunk, $plan);
        }
        if ($chunks === 0 || $sourceRows !== $preliminaryReady + $preliminarySkipped) {
            throw new InvalidArgumentException('safir_csv_receipt_empty_or_incomplete');
        }
        $end = self::stat($stream);
        self::rewind($stream);
        [$secondDigest, $secondSize] = self::fingerprint($stream);
        $final = self::stat($stream);
        if ($size !== $secondSize || !hash_equals($firstDigest, $secondDigest)
            || $begin !== $end || $begin !== $final) {
            throw new InvalidArgumentException('safir_csv_receipt_source_changed');
        }
        self::rewind($stream);
        return [
            'version' => 'safir_csv_file_receipt_v1',
            'source_sha256' => $firstDigest,
            'source_bytes' => $size,
            'template_sha256' => $declaredHash,
            'delimiter' => $delimiter,
            'chunk_size' => $chunkSize,
            'chunk_count' => $chunks,
            'source_rows' => $sourceRows,
            'preliminary_ready_rows' => $preliminaryReady,
            'preliminary_skipped_rows' => $preliminarySkipped,
            'chunk_manifest_sha256' => hash_final($manifest),
        ];
    }

    /** Reject a mismatched or partially verified file; makes no DB changes. */
    public static function verify($stream, array $frozen, array $receipt): void
    {
        $size = $receipt['chunk_size'] ?? null;
        $delimiter = $receipt['delimiter'] ?? null;
        if (!is_int($size) || !is_string($delimiter)) {
            throw new InvalidArgumentException('safir_csv_receipt_invalid');
        }
        $fresh = self::inspect($stream, $frozen, $size, $delimiter);
        if ($fresh !== $receipt) {
            throw new InvalidArgumentException('safir_csv_receipt_no_longer_matches');
        }
    }

    private static function assertStream($stream): void
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('safir_csv_receipt_stream_invalid');
        }
        $meta = stream_get_meta_data($stream);
        if (empty($meta['seekable']) || ftell($stream) !== 0) {
            throw new InvalidArgumentException('safir_csv_receipt_seekable_start_required');
        }
    }

    private static function stat($stream): array
    {
        $info = fstat($stream);
        if (!is_array($info) || !isset($info['dev'], $info['ino'], $info['size'], $info['mtime'])) {
            throw new InvalidArgumentException('safir_csv_receipt_stream_stat_unavailable');
        }
        return ['dev' => $info['dev'], 'ino' => $info['ino'],
                'size' => $info['size'], 'mtime' => $info['mtime']];
    }

    private static function rewind($stream): void
    {
        if (!rewind($stream)) {
            throw new InvalidArgumentException('safir_csv_receipt_seek_failed');
        }
    }

    /** @return array{string,int} */
    private static function fingerprint($stream): array
    {
        $context = hash_init('sha256');
        $count = hash_update_stream($context, $stream);
        if (!is_int($count)) {
            throw new InvalidArgumentException('safir_csv_receipt_read_failed');
        }
        return [hash_final($context), $count];
    }
}
