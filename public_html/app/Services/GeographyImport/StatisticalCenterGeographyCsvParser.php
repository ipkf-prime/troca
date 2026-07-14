<?php

namespace App\Services\GeographyImport;

use Generator;
use InvalidArgumentException;
use RuntimeException;

class StatisticalCenterGeographyCsvParser
{
    private const REQUIRED_HEADERS = [
        'province_code', 'province_title', 'county_code', 'county_title',
        'district_code', 'district_title', 'rural_or_city_code',
        'rural_district_title', 'settlement_code', 'source_title', 'coderec', 'diag',
    ];

    public function __construct(private readonly PersianTextNormalizer $normalizer)
    {
    }

    public function stream(string $path, array $headerAliases, string $delimiter = ','): Generator
    {
        if (strlen($delimiter) !== 1) {
            throw new InvalidArgumentException('Configured CSV delimiter is invalid.');
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Source file could not be read.');
        }

        try {
            [$header, $headerMap, $headerRowNumber] = $this->readHeader($handle, $headerAliases, $delimiter);
            $headerCount = count($header);
            $rowNumber = $headerRowNumber;
            $blankRows = 0;

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

                $field = static fn (string $key): string => trim((string) ($values[$headerMap[$key]] ?? ''));
                $rawColumns = [];

                foreach ($header as $index => $originalHeader) {
                    $rawColumns[(string) $originalHeader] = (string) ($values[$index] ?? '');
                }

                yield new StatisticalCenterGeographyRow(
                    $rowNumber,
                    $field('province_code'),
                    $field('province_title'),
                    $field('county_code'),
                    $field('county_title'),
                    $field('district_code'),
                    $field('district_title'),
                    $field('rural_or_city_code'),
                    $field('rural_district_title'),
                    $field('settlement_code'),
                    $field('source_title'),
                    $field('coderec'),
                    $field('diag'),
                    $rawColumns,
                    $parseError
                );
            }

            return [
                'blank_rows' => $blankRows,
                'schema_signature' => hash(
                    'sha256',
                    json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''
                ),
            ];
        } finally {
            fclose($handle);
        }
    }

    private function readHeader($handle, array $headerAliases, string $delimiter): array
    {
        $rowNumber = 0;

        while (($header = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rowNumber++;

            if ($this->isBlankRow($header)) {
                continue;
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? '')) ?? ($header[0] ?? '');

            foreach ($header as $value) {
                if (preg_match('//u', (string) $value) !== 1) {
                    throw new InvalidArgumentException('CSV header must be UTF-8.');
                }
            }

            $headerMap = $this->mapHeaders($header, $headerAliases);

            if (array_diff(self::REQUIRED_HEADERS, array_keys($headerMap)) !== []) {
                throw new InvalidArgumentException('MISSING_REQUIRED_HEADER');
            }

            return [$header, $headerMap, $rowNumber];
        }

        throw new InvalidArgumentException('CSV file is empty.');
    }

    private function mapHeaders(array $header, array $headerAliases): array
    {
        $aliases = [];

        foreach ($headerAliases as $canonical => $values) {
            foreach ((array) $values as $value) {
                $aliases[$this->normalizer->header($value)] = (string) $canonical;
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
