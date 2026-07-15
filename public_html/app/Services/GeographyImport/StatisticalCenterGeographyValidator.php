<?php

namespace App\Services\GeographyImport;

class StatisticalCenterGeographyValidator
{
    public function __construct(private readonly PersianTextNormalizer $normalizer)
    {
    }

    public function validate(
        StatisticalCenterGeographyRow $row,
        array $mappingsByType,
        string $countryRoot
    ): array {
        $recordType = $this->normalizer->code($row->coderec);
        $mapping = $mappingsByType[$recordType] ?? null;
        $codes = [
            'province' => $this->normalizer->code($row->provinceCode),
            'county' => $this->normalizer->code($row->countyCode),
            'district' => $this->normalizer->code($row->districtCode),
            'rural_or_city' => $this->normalizer->code($row->ruralOrCityCode),
            'settlement' => $this->normalizer->code($row->settlementCode),
        ];
        $rawLocalCode = $this->localCode($row, $recordType);
        $localCode = $this->normalizer->code($rawLocalCode);
        $rawTitle = $this->sourceTitle($row, $recordType);
        $normalizedTitle = $this->normalizer->text($rawTitle);
        $diag = trim($row->diag);
        $issues = [];

        if ($row->parseError !== null) {
            $issues[] = $this->issue('PARSE_ERROR', 'error', null, 'The source row could not be parsed cleanly.');
        }

        if ($mapping === null) {
            $issues[] = $this->issue(
                'UNKNOWN_CODEREC',
                'warning',
                'CODEREC',
                'The source record classifier is not configured.'
            );
            $issues[] = $this->issue(
                'UNSUPPORTED_SOURCE_RECORD',
                'warning',
                'CODEREC',
                'The source observation is preserved for review without a canonical interpretation.'
            );
        }

        if ($normalizedTitle === '') {
            $issues[] = $this->issue('EMPTY_SOURCE_TITLE', 'error', 'name', 'The source observation title is empty.');
        }

        if ($localCode === '') {
            $issues[] = $this->issue(
                match ($recordType) {
                    '1' => 'MISSING_PROVINCE_CODE',
                    '2' => 'MISSING_COUNTY_CODE',
                    '3' => 'MISSING_DISTRICT_CODE',
                    default => 'MISSING_SOURCE_LOCAL_CODE',
                },
                'error',
                $mapping['code_field'] ?? null,
                'The source-local code is missing.'
            );
        } elseif (preg_match('/^\d+$/D', $localCode) !== 1) {
            $issues[] = $this->issue(
                'INVALID_SOURCE_CODE_FORMAT',
                'error',
                $mapping['code_field'] ?? null,
                'The normalized source-local code must contain digits only.'
            );
        }

        foreach ($this->requiredContextFields($recordType) as $field) {
            if ($codes[$field] === '') {
                $issues[] = $this->issue(
                    match ($field) {
                        'province' => 'MISSING_PROVINCE_CODE',
                        'county' => 'MISSING_COUNTY_CODE',
                        'district' => 'MISSING_DISTRICT_CODE',
                        default => 'MISSING_SOURCE_LOCAL_CODE',
                    },
                    'error',
                    $field . '_code',
                    'A required hierarchy context code is missing.',
                    ['context' => $field]
                );
            } elseif (preg_match('/^\d+$/D', $codes[$field]) !== 1) {
                $issues[] = $this->issue(
                    'INVALID_HIERARCHY_CONTEXT_CODE',
                    'error',
                    $field . '_code',
                    'A normalized hierarchy context code must contain digits only.',
                    ['context' => $field]
                );
            }
        }

        if ($recordType === '5') {
            $issues[] = $this->issue(
                'STATISTICAL_URBAN_UNIT',
                'warning',
                'CODEREC',
                'CODEREC 5 is a statistical urban unit and is not automatically an official city.'
            );

            $issues[] = preg_match('/\d+\s*$/u', $normalizedTitle) === 1
                ? $this->issue(
                    'NUMBERED_URBAN_SUBDIVISION',
                    'warning',
                    'name',
                    'A numbered statistical urban subdivision requires review.'
                )
                : $this->issue(
                    'POSSIBLE_OFFICIAL_CITY_CANDIDATE',
                    'warning',
                    'name',
                    'The observation may correspond to a city, but automatic matching is disabled.'
                );
        }

        if ($diag !== '') {
            $issues[] = $recordType === '8'
                ? $this->issue(
                    'DIAG_PRESENT',
                    'info',
                    'DIAG',
                    'The opaque DIAG source classifier was preserved without interpretation.'
                )
                : $this->issue(
                    'DIAG_WITH_UNEXPECTED_CODEREC',
                    'warning',
                    'DIAG',
                    'DIAG is present outside the expected record classifier and requires review.'
                );
        }

        [$compositeKey, $parentKey, $parentRecordType] = $this->contextKeys(
            $recordType,
            $codes,
            $localCode,
            $mapping
        );
        $rawPayload = $row->rawPayload();
        $rawPayload['interpreted'] = [
            'record_type' => $recordType,
            'source_entity_kind' => $mapping['source_entity_kind'] ?? 'unsupported_source_observation',
            'normalized_source_code' => $localCode,
            'normalized_title' => $normalizedTitle,
            'source_composite_key' => $compositeKey,
            'source_parent_composite_key' => $parentKey,
            'diag_interpreted' => false,
        ];
        $hasError = false;
        $hasWarning = false;

        foreach ($issues as $issue) {
            $hasError = $hasError || $issue['severity'] === 'error';
            $hasWarning = $hasWarning || $issue['severity'] === 'warning';
        }

        return [
            'source_row_number' => $row->sourceRowNumber,
            'source_record_type' => $recordType,
            'source_code' => trim($rawLocalCode),
            'source_title' => trim($rawTitle),
            'normalized_title' => $normalizedTitle,
            'derived_level_code' => $mapping['derived_level_code'] ?? null,
            'derived_parent_code' => $recordType === '1' ? $countryRoot : $parentKey,
            'row_checksum' => hash(
                'sha256',
                json_encode(
                    $rawPayload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ) ?: ''
            ),
            'validation_status' => $hasError ? 'invalid' : ($hasWarning ? 'warning' : 'valid'),
            'raw_payload' => $rawPayload,
            'issues' => $issues,
            'source_entity_kind' => $mapping['source_entity_kind'] ?? 'unsupported_source_observation',
            'source_local_code' => trim($rawLocalCode) !== '' ? trim($rawLocalCode) : null,
            'source_composite_key' => $compositeKey,
            'source_parent_composite_key' => $parentKey,
            'source_parent_record_type' => $parentRecordType,
            'source_classifier_code' => $diag !== '' ? $diag : null,
            'normalized_source_code' => $localCode !== '' ? $localCode : null,
        ];
    }

    private function localCode(StatisticalCenterGeographyRow $row, string $recordType): string
    {
        return match ($recordType) {
            '1' => $row->provinceCode,
            '2' => $row->countyCode,
            '3' => $row->districtCode,
            '4', '5' => $row->ruralOrCityCode,
            '6', '8' => $row->settlementCode,
            default => $row->settlementCode !== ''
                ? $row->settlementCode
                : ($row->ruralOrCityCode !== '' ? $row->ruralOrCityCode : $row->districtCode),
        };
    }

    private function sourceTitle(StatisticalCenterGeographyRow $row, string $recordType): string
    {
        return match ($recordType) {
            '1' => $row->provinceTitle !== '' ? $row->provinceTitle : $row->sourceTitle,
            '2' => $row->countyTitle !== '' ? $row->countyTitle : $row->sourceTitle,
            '3' => $row->districtTitle !== '' ? $row->districtTitle : $row->sourceTitle,
            '4' => $row->ruralDistrictTitle !== '' ? $row->ruralDistrictTitle : $row->sourceTitle,
            default => $row->sourceTitle !== '' ? $row->sourceTitle : $row->ruralDistrictTitle,
        };
    }

    private function requiredContextFields(string $recordType): array
    {
        return match ($recordType) {
            '1' => [],
            '2' => ['province'],
            '3' => ['province', 'county'],
            '4', '5', '6', '8' => ['province', 'county', 'district'],
            default => [],
        };
    }

    private function contextKeys(
        string $recordType,
        array $codes,
        string $localCode,
        ?array $mapping
    ): array {
        $province = $this->part('P', $codes['province']);
        $county = $this->append($province, 'C', $codes['county']);
        $district = $this->append($county, 'D', $codes['district']);
        $ruralOrCity = $this->append($district, 'R', $codes['rural_or_city']);

        return match ($recordType) {
            '1' => [$province, null, null],
            '2' => [$county, $province, '1'],
            '3' => [$district, $county, '2'],
            '4', '5' => [$ruralOrCity, $district, '3'],
            '6', '8' => [
                $this->append($codes['rural_or_city'] !== '' ? $ruralOrCity : $district, 'S', $localCode),
                $codes['rural_or_city'] !== '' ? $ruralOrCity : $district,
                $codes['rural_or_city'] !== '' ? '4,5' : '3',
            ],
            default => [
                $this->append($codes['rural_or_city'] !== '' ? $ruralOrCity : $district, 'U', $localCode),
                null,
                $mapping['parent_record_type'] ?? null,
            ],
        };
    }

    private function part(string $label, string $code): ?string
    {
        return $code === '' ? null : $label . ':' . $code;
    }

    private function append(?string $parent, string $label, string $code): ?string
    {
        if ($parent === null || $code === '') {
            return null;
        }

        return $parent . '|' . $label . ':' . $code;
    }

    private function issue(
        string $code,
        string $severity,
        ?string $field,
        string $message,
        array $metadata = []
    ): array {
        return compact('code', 'severity', 'field', 'message', 'metadata');
    }
}
