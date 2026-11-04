<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * R73C1: PRIVATE, fail-closed CSV staging. SOURCE-ONLY. No DB, worker,
 * notifications, session state, HTTP/provider, or production activation.
 *
 * The private root is provided by the operator; it must be a real, non-symlink
 * 0700 directory with a 0600 private-root marker. Never pass a public web path.
 * Staged files are rehashed on every use; Unix permissions are NOT a claim of
 * immutable storage. R73C2 will integrate an explicit durable DB receipt.
 */
final class BaleSafirDurableCsvPrivateStage
{
    public const ROOT_MARKER = '.safir_private_stage_root_v1';
    public const ROOT_MARKER_BODY = "SAFIR_R73C_PRIVATE_STAGE_ROOT_V1\n";
    public const DEFAULT_MAX_FILE_BYTES = 268435456; // 256 MiB per staged file
    private const STAGE_PREFIX = 'safir-r73c-file-';

    /**
     * @param array $frozen R72A immutable approved template snapshot
     * @return array{stage_id:string,source_sha256:string,source_bytes:int,source_rows:int,chunk_count:int,preliminary_ready_rows:int,preliminary_skipped_rows:int,stage_verified:bool}
     */
    public static function stage(
        string $sourcePath,
        string $privateRoot,
        array $frozen,
        string $expectedSha256,
        int $chunkSize = 500,
        string $delimiter = ',',
        int $maxFileBytes = self::DEFAULT_MAX_FILE_BYTES
    ): array {
        $root = self::validateRoot($privateRoot);
        if (!preg_match('/^[a-f0-9]{64}$/D', $expectedSha256)
            || $maxFileBytes < 1024 || $maxFileBytes > 2147483648
            || $sourcePath === '' || $sourcePath[0] !== '/'
            || is_link($sourcePath) || !is_file($sourcePath) || !is_readable($sourcePath)
            || !in_array($delimiter, [',', ';'], true)
            || $chunkSize < 1 || $chunkSize > BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS) {
            throw new InvalidArgumentException('safir_r73c_stage_invalid_input');
        }
        $handle = fopen($sourcePath, 'rb');
        if (!is_resource($handle)) {
            throw new RuntimeException('safir_r73c_source_unavailable');
        }
        $id = self::STAGE_PREFIX . bin2hex(random_bytes(16));
        $dir = $root . '/' . $id;
        $created = false;
        $published = false;
        try {
            if (!mkdir($dir, 0700)) {
                throw new RuntimeException('safir_r73c_stage_directory_failed');
            }
            $created = true;
            chmod($dir, 0700);
            $target = $dir . '/source.csv';
            $output = fopen($target, 'x+b');
            if (!is_resource($output)) {
                throw new RuntimeException('safir_r73c_stage_write_unavailable');
            }
            try {
                chmod($target, 0600);
                $hash = hash_init('sha256');
                $total = 0;
                while (!feof($handle)) {
                    $block = fread($handle, 65536);
                    if ($block === false || ($block === '' && !feof($handle))) {
                        throw new RuntimeException('safir_r73c_stage_source_read_failed');
                    }
                    if ($block === '') {
                        break;
                    }
                    $length = strlen($block);
                    $total += $length;
                    if ($total > $maxFileBytes) {
                        throw new InvalidArgumentException('safir_r73c_stage_source_too_large');
                    }
                    hash_update($hash, $block);
                    $offset = 0;
                    while ($offset < $length) {
                        $written = fwrite($output, substr($block, $offset));
                        if (!is_int($written) || $written <= 0) {
                            throw new RuntimeException('safir_r73c_stage_write_failed');
                        }
                        $offset += $written;
                    }
                }
                if ($total === 0 || !fflush($output)
                    || (function_exists('fsync') && !fsync($output))) {
                    throw new RuntimeException('safir_r73c_stage_sync_failed');
                }
                $sha = hash_final($hash);
                if (!hash_equals($sha, $expectedSha256)) {
                    throw new InvalidArgumentException('safir_r73c_stage_source_sha256_mismatch');
                }
            } finally {
                fclose($output);
            }
            $stagedSha = hash_file('sha256', $target);
            if (!is_string($stagedSha) || !hash_equals($expectedSha256, $stagedSha)) {
                throw new RuntimeException('safir_r73c_staged_file_checksum_changed');
            }
            $input = fopen($target, 'rb');
            if (!is_resource($input)) {
                throw new RuntimeException('safir_r73c_stage_verify_unavailable');
            }
            try {
                $receipt = BaleSafirDurableCsvFileReceipt::inspect(
                    $input, $frozen, $chunkSize, $delimiter
                );
            } finally {
                fclose($input);
            }
            if (!hash_equals($expectedSha256, (string) $receipt['source_sha256'])
                || $total !== $receipt['source_bytes']) {
                throw new RuntimeException('safir_r73c_receipt_source_changed');
            }
            $manifest = [
                'version' => 'safir_r73c_private_stage_v1',
                'stage_id' => $id,
                'stage_relative_file' => 'source.csv',
                'receipt' => $receipt,
                'status' => 'verified_not_imported',
            ];
            $json = json_encode($manifest, JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $manifestFile = $dir . '/receipt.json';
            $out = fopen($manifestFile, 'xb');
            if (!is_resource($out)) {
                throw new RuntimeException('safir_r73c_manifest_write_failed');
            }
            try {
                chmod($manifestFile, 0600);
                if (fwrite($out, $json) !== strlen($json) || !fflush($out)
                    || (function_exists('fsync') && !fsync($out))) {
                    throw new RuntimeException('safir_r73c_manifest_sync_failed');
                }
            } finally {
                fclose($out);
            }
            $published = true;
            return [
                'stage_id' => $id,
                'source_sha256' => $receipt['source_sha256'],
                'source_bytes' => $receipt['source_bytes'],
                'source_rows' => $receipt['source_rows'],
                'chunk_count' => $receipt['chunk_count'],
                'preliminary_ready_rows' => $receipt['preliminary_ready_rows'],
                'preliminary_skipped_rows' => $receipt['preliminary_skipped_rows'],
                'stage_verified' => true,
            ];
        } finally {
            fclose($handle);
            if ($created && !$published) {
                // Only clean up the freshly created private UUID directory.
                // If an unexpected file appears, leave it private for review.
                foreach (['receipt.json', 'source.csv'] as $name) {
                    $path = $dir . '/' . $name;
                    if (is_file($path) && !is_link($path)) {
                        @unlink($path);
                    }
                }
                @rmdir($dir);
            }
        }
    }

    /** Rehash and reparse the private copy before any later DB use. */
    public static function verify(string $privateRoot, string $stageId, array $frozen): array
    {
        $root = self::validateRoot($privateRoot);
        if (!preg_match('/^safir-r73c-file-[a-f0-9]{32}$/D', $stageId)) {
            throw new InvalidArgumentException('safir_r73c_stage_id_invalid');
        }
        $dir = $root . '/' . $stageId;
        if (is_link($dir) || !is_dir($dir) || realpath($dir) !== $dir
            || (fileperms($dir) & 0077) !== 0) {
            throw new RuntimeException('safir_r73c_stage_not_private');
        }
        $file = $dir . '/source.csv';
        $manifestPath = $dir . '/receipt.json';
        foreach ([$file, $manifestPath] as $path) {
            if (is_link($path) || !is_file($path) || !is_readable($path)
                || (fileperms($path) & 0077) !== 0) {
                throw new RuntimeException('safir_r73c_stage_file_not_private');
            }
        }
        $raw = file_get_contents($manifestPath);
        if (!is_string($raw) || strlen($raw) > 16384) {
            throw new RuntimeException('safir_r73c_manifest_unavailable');
        }
        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest)
            || ($manifest['version'] ?? '') !== 'safir_r73c_private_stage_v1'
            || ($manifest['stage_id'] ?? '') !== $stageId
            || ($manifest['stage_relative_file'] ?? '') !== 'source.csv'
            || ($manifest['status'] ?? '') !== 'verified_not_imported'
            || !is_array($manifest['receipt'] ?? null)) {
            throw new RuntimeException('safir_r73c_manifest_invalid');
        }
        $input = fopen($file, 'rb');
        if (!is_resource($input)) {
            throw new RuntimeException('safir_r73c_stage_read_unavailable');
        }
        try {
            BaleSafirDurableCsvFileReceipt::verify($input, $frozen, $manifest['receipt']);
        } finally {
            fclose($input);
        }
        return $manifest['receipt'];
    }

    private static function validateRoot(string $root): string
    {
        if ($root === '' || $root[0] !== '/' || is_link($root)
            || !is_dir($root) || realpath($root) !== $root
            || (fileperms($root) & 0077) !== 0) {
            throw new InvalidArgumentException('safir_r73c_private_root_invalid');
        }
        foreach ([$_SERVER['DOCUMENT_ROOT'] ?? null, defined('BASE_PATH') ? BASE_PATH : null] as $webRoot) {
            if (is_string($webRoot) && $webRoot !== '') {
                $realWeb = realpath($webRoot);
                if (is_string($realWeb)
                    && ($root === $realWeb || str_starts_with($root . '/', rtrim($realWeb, '/') . '/'))) {
                    throw new InvalidArgumentException('safir_r73c_webroot_storage_forbidden');
                }
            }
        }
        $marker = $root . '/' . self::ROOT_MARKER;
        if (is_link($marker) || !is_file($marker)
            || (fileperms($marker) & 0077) !== 0
            || file_get_contents($marker) !== self::ROOT_MARKER_BODY) {
            throw new InvalidArgumentException('safir_r73c_private_root_marker_missing');
        }
        return $root;
    }
}
