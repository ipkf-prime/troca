<?php
declare(strict_types=1);

namespace App\Services;

use Generator;
use InvalidArgumentException;

/**
 * R73A: bounded RFC-4180-style UTF-8 CSV reader for SAFIR import.
 * Pure parsing only: no DB, Session, file uploads, routes, worker or network.
 *
 * The caller owns the stream and MUST validate uploads + full file completion.
 * Chunks are provisional until the complete file has been consumed successfully.
 * SHA256 covers actual source-record bytes, header bytes and chunk ordinal.
 * This is not a send queue or an approval workflow.
 */
final class BaleSafirDurableCsvChunkReader
{
    public const MAX_COLUMNS = 64;
    public const MAX_RECORD_BYTES = 65536;
    public const MAX_FIELD_BYTES = 8192;
    public const MAX_HEADER_BYTES = 16384;

    /**
     * @param resource $stream binary readable stream at byte 0
     * @param array $frozen output from BaleSafirDurableCampaignPolicy::freezeApprovedTemplate()
     * @return Generator<int,array{index:int,sha256:string,first_row:int,last_row:int,rows:array,record_count:int}>
     */
    public static function chunks($stream, array $frozen, int $chunkSize = 500, string $delimiter = ','): Generator
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream'
            || !in_array($delimiter, [',', ';'], true)
            || $chunkSize < 1 || $chunkSize > BaleSafirDurableCampaignPolicy::MAX_CHUNK_ROWS
            || !is_string($frozen['phone_column'] ?? null)
            || !is_array($frozen['mappings'] ?? null)
            || !is_string($frozen['sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $frozen['sha256'])) {
            throw new InvalidArgumentException('safir_csv_reader_contract_invalid');
        }
        $meta = stream_get_meta_data($stream);
        if (!empty($meta['seekable']) && ftell($stream) !== 0) {
            throw new InvalidArgumentException('safir_csv_stream_not_at_start');
        }

        $header = null;
        $rawHeader = '';
        $batch = [];
        $ordinal = 0;
        $chunkIndex = 1;
        $ctx = null;

        foreach (self::records($stream, $delimiter) as $record) {
            if ($header === null) {
                if (strlen($record['raw']) > self::MAX_HEADER_BYTES) {
                    throw new InvalidArgumentException('safir_csv_header_too_large');
                }
                $rawHeader = $record['raw'];
                $header = $record['cells'];
                if (isset($header[0]) && str_starts_with($header[0], "\xEF\xBB\xBF")) {
                    $header[0] = substr($header[0], 3);
                }
                if (count($header) < 1 || count($header) > self::MAX_COLUMNS
                    || count(array_unique($header)) !== count($header)) {
                    throw new InvalidArgumentException('safir_csv_header_duplicate_or_invalid');
                }
                foreach ($header as $label) {
                    if ($label === '' || trim($label) !== $label
                        || strlen($label) > 120) {
                        throw new InvalidArgumentException('safir_csv_header_invalid_column');
                    }
                }
                $required = [$frozen['phone_column']];
                foreach ($frozen['mappings'] as $columns) {
                    if (!is_array($columns)) {
                        throw new InvalidArgumentException('safir_csv_mapping_invalid');
                    }
                    foreach ($columns as $col) {
                        $required[] = $col;
                    }
                }
                foreach (array_unique($required) as $name) {
                    if (!in_array($name, $header, true)) {
                        throw new InvalidArgumentException('safir_csv_mapping_missing');
                    }
                }
                continue;
            }

            $ordinal++;
            $rowNumber = $ordinal + 1;
            if ($rowNumber > 4294967295) {
                throw new InvalidArgumentException('safir_csv_source_row_overflow');
            }
            if (count($record['cells']) !== count($header)) {
                throw new InvalidArgumentException('safir_csv_column_count_mismatch');
            }
            if ($ctx === null) {
                $ctx = self::chunkDigest($rawHeader, $chunkIndex);
            }
            $raw = $record['raw'];
            hash_update($ctx, pack('N', strlen($raw)));
            hash_update($ctx, $raw);
            $batch[] = ['row' => $rowNumber, 'columns' => array_combine($header, $record['cells'])];

            if (count($batch) === $chunkSize) {
                yield self::snapshot($chunkIndex, $ctx, $batch);
                $chunkIndex++;
                $batch = [];
                $ctx = null;
            }
        }

        if ($header === null) {
            throw new InvalidArgumentException('safir_csv_missing_header');
        }
        if ($batch !== []) {
            yield self::snapshot($chunkIndex, $ctx, $batch);
        }
        if ($ordinal === 0) {
            throw new InvalidArgumentException('safir_csv_missing_data_rows');
        }
    }

    private static function chunkDigest(string $rawHeader, int $chunkIndex): mixed
    {
        $ctx = hash_init('sha256');
        hash_update($ctx, "IPKF-SAFIR-CSV-CHUNK-V1\0");
        hash_update($ctx, pack('N', strlen($rawHeader)));
        hash_update($ctx, $rawHeader);
        hash_update($ctx, pack('N', $chunkIndex));
        return $ctx;
    }

    private static function snapshot(int $index, mixed $ctx, array $rows): array
    {
        return [
            'index' => $index,
            'sha256' => hash_final($ctx),
            'first_row' => $rows[0]['row'],
            'last_row' => $rows[count($rows) - 1]['row'],
            'record_count' => count($rows),
            'rows' => $rows,
        ];
    }

    /**
     * Bounded byte-by-byte RFC4180 parser. Quoted delimiters and newlines are
     * supported. Raw bytes are retained for per-chunk source hashes.
     * States: 0=field start, 1=unquoted, 2=quoted, 3=closing quote.
     *
     * @return Generator<int,array{cells:array,raw:string}>
     */
    private static function records($stream, string $delimiter): Generator
    {
        $state = 0;
        $field = '';
        $cells = [];
        $raw = '';

        $finishField = static function () use (&$field, &$cells): void {
            if (!preg_match('//u', $field)) {
                throw new InvalidArgumentException('safir_csv_invalid_utf8');
            }
            // Null, terminal control and isolated carriage return are not valid
            // message variables; quoted CRLF and LF remain supported.
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $field)) {
                throw new InvalidArgumentException('safir_csv_control_character');
            }
            $cells[] = $field;
            $field = '';
            if (count($cells) > self::MAX_COLUMNS) {
                throw new InvalidArgumentException('safir_csv_too_many_columns');
            }
        };

        while (!feof($stream)) {
            $block = fread($stream, 16384);
            if ($block === false) {
                throw new InvalidArgumentException('safir_csv_read_failure');
            }
            if ($block === '') {
                if (!feof($stream)) {
                    throw new InvalidArgumentException('safir_csv_empty_read');
                }
                break;
            }
            for ($i = 0, $length = strlen($block); $i < $length; $i++) {
                $ch = $block[$i];
                $raw .= $ch;
                if (strlen($raw) > self::MAX_RECORD_BYTES) {
                    throw new InvalidArgumentException('safir_csv_record_too_large');
                }

                if ($state === 2) {
                    if ($ch === '"') {
                        $state = 3;
                    } else {
                        $field .= $ch;
                    }
                } elseif ($state === 3) {
                    if ($ch === '"') {
                        $field .= '"';
                        $state = 2;
                    } elseif ($ch === $delimiter) {
                        $finishField();
                        $state = 0;
                    } elseif ($ch === "\n") {
                        $finishField();
                        if (count($cells) !== 1 || $cells[0] !== '') {
                            yield ['cells' => $cells, 'raw' => $raw];
                        }
                        $cells = []; $raw = ''; $state = 0;
                    } elseif ($ch !== "\r") {
                        throw new InvalidArgumentException('safir_csv_junk_after_quote');
                    }
                } elseif ($ch === $delimiter) {
                    $finishField();
                    $state = 0;
                } elseif ($ch === "\n") {
                    if ($state === 1 && str_ends_with($field, "\r")) {
                        $field = substr($field, 0, -1);
                    }
                    $finishField();
                    if (count($cells) !== 1 || $cells[0] !== '') {
                        yield ['cells' => $cells, 'raw' => $raw];
                    }
                    $cells = []; $raw = ''; $state = 0;
                } elseif ($state === 0 && $ch === '"') {
                    $state = 2;
                } elseif ($ch === '"') {
                    throw new InvalidArgumentException('safir_csv_unquoted_quote');
                } else {
                    $field .= $ch;
                    $state = 1;
                }
                if (strlen($field) > self::MAX_FIELD_BYTES) {
                    throw new InvalidArgumentException('safir_csv_field_too_large');
                }
            }
        }

        if ($state === 2) {
            throw new InvalidArgumentException('safir_csv_unterminated_quote');
        }
        if ($raw !== '') {
            if ($state === 1 && str_ends_with($field, "\r")) {
                throw new InvalidArgumentException('safir_csv_bare_carriage_return');
            }
            $finishField();
            if (count($cells) !== 1 || $cells[0] !== '') {
                yield ['cells' => $cells, 'raw' => $raw];
            }
        }
    }
}
