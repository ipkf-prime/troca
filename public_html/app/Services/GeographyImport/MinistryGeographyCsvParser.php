<?php

namespace App\Services\GeographyImport;

use InvalidArgumentException;
use RuntimeException;

class MinistryGeographyCsvParser
{
    private const REQUIRED_HEADERS = [
        'source_type', 'approved_title', 'province_title', 'county_title',
        'district_title', 'hierarchy_code', 'national_identifier',
    ];

    private const HEADER_ALIASES = [
        'source_ordinal' => ['ردیف', 'شماره', 'شماره ردیف'],
        'source_type' => ['نوع', 'نوع تقسیمات', 'نوع عارضه'],
        'approved_title' => ['نام مصوب', 'نام', 'عنوان مصوب'],
        'province_title' => ['استان', 'نام استان'],
        'county_title' => ['شهرستان', 'نام شهرستان'],
        'district_title' => ['بخش', 'نام بخش'],
        'hierarchy_code' => ['کد سلسله مراتبی', 'کد سلسله‌مراتبی', 'کد سلسله مراتب', 'کد تقسیمات کشوری'],
        'national_identifier' => ['شناسه ملی', 'شناسه', 'کد شناسه ملی'],
        'source_note' => ['توضیح', 'توضیحات', 'یادداشت'],
    ];

    public function __construct(private readonly PersianTextNormalizer $normalizer)
    {
    }

    public function parse(string $path): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Source file could not be read.');
        }

        try {
            [$header, $delimiter, $headerLine] = $this->readHeader($handle);
            $headerMap = $this->mapHeaders($header);
            $this->assertRequiredHeaders($headerMap);

            $rows = [];
            $blankRows = 0;
            $rowNumber = $headerLine;
            $headerCount = count($header);

            while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $rowNumber++;

                if ($this->isBlankRow($values)) {
                    $blankRows++;
                    continue;
                }

                $parseError = count($values) === $headerCount
                    ? null
                    : 'CSV column count does not match the detected header.';
                $values = array_pad(array_slice($values, 0, $headerCount), $headerCount, '');

                foreach ($values as $value) {
                    if (preg_match('//u', (string) $value) !== 1) {
                        $parseError = 'CSV row contains invalid UTF-8.';
                        break;
                    }
                }

                $field = static fn (string $key): string => isset($headerMap[$key])
                    ? trim((string) ($values[$headerMap[$key]] ?? ''))
                    : '';

                $rows[] = new MinistryGeographyRow(
                    $rowNumber,
                    $field('source_ordinal'),
                    $field('source_type'),
                    $field('approved_title'),
                    $field('province_title'),
                    $field('county_title'),
                    $field('district_title'),
                    $field('hierarchy_code'),
                    $field('national_identifier'),
                    $field('source_note'),
                    $parseError
                );
            }

            return [
                'rows' => $rows,
                'blank_rows' => $blankRows,
                'schema_signature' => hash('sha256', implode('|', array_keys($headerMap))),
            ];
        } finally {
            fclose($handle);
        }
    }

    private function readHeader($handle): array
    {
        $lineNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;

            if (trim($line) === '') {
                continue;
            }

            if (preg_match('//u', $line) !== 1) {
                throw new InvalidArgumentException('CSV header must be UTF-8.');
            }

            $delimiter = $this->detectDelimiter($line);

            return [str_getcsv(rtrim($line, "\r\n"), $delimiter, '"', ''), $delimiter, $lineNumber];
        }

        throw new InvalidArgumentException('CSV file is empty.');
    }

    private function detectDelimiter(string $line): string
    {
        $scores = [];

        foreach ([',', ';', "\t"] as $delimiter) {
            $scores[$delimiter] = count(str_getcsv($line, $delimiter, '"', ''));
        }

        arsort($scores);
        $delimiter = (string) array_key_first($scores);

        if (($scores[$delimiter] ?? 0) < 2) {
            throw new InvalidArgumentException('CSV delimiter could not be detected.');
        }

        return $delimiter;
    }

    private function mapHeaders(array $header): array
    {
        $aliases = [];

        foreach (self::HEADER_ALIASES as $canonical => $values) {
            foreach ($values as $value) {
                $aliases[$this->normalizer->header($value)] = $canonical;
            }
        }

        $mapped = [];

        foreach ($header as $index => $value) {
            $canonical = $aliases[$this->normalizer->header($value)] ?? null;

            if ($canonical !== null && !isset($mapped[$canonical])) {
                $mapped[$canonical] = $index;
            }
        }

        return $mapped;
    }

    private function assertRequiredHeaders(array $headerMap): void
    {
        if (array_diff(self::REQUIRED_HEADERS, array_keys($headerMap)) !== []) {
            throw new InvalidArgumentException('Required source headers are missing.');
        }
    }

    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
