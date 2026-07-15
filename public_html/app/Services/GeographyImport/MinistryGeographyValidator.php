<?php

namespace App\Services\GeographyImport;

class MinistryGeographyValidator
{
    public function __construct(private readonly PersianTextNormalizer $normalizer)
    {
    }

    /** @param MinistryGeographyRow[] $rows */
    public function validate(array $rows, array $mappings, array $placeholderValues, string $countryRoot): array
    {
        $mappingByType = [];

        foreach ($mappings as $mapping) {
            $mappingByType[$this->normalizer->text($mapping['source_type_value'] ?? '')] = $mapping;
        }

        $placeholders = [];

        foreach ($placeholderValues as $placeholder) {
            $placeholders[$this->normalizer->text($placeholder)] = true;
        }

        $validated = [];
        $codeGroups = [];
        $identifierGroups = [];

        foreach ($rows as $index => $row) {
            $type = $this->normalizer->text($row->sourceType);
            $mapping = $mappingByType[$type] ?? null;
            $title = $this->normalizer->text($row->approvedTitle);
            $code = $this->normalizer->code($row->hierarchyCode);
            $identifier = $this->normalizer->code($row->nationalIdentifier);
            $issues = [];
            $rawPayload = $row->rawPayload();
            $interpreted = [];

            foreach ([
                'province_title' => $row->provinceTitle,
                'county_title' => $row->countyTitle,
                'district_title' => $row->districtTitle,
            ] as $field => $value) {
                $normalizedValue = $this->normalizer->text($value);

                if ($normalizedValue !== '' && isset($placeholders[$normalizedValue])) {
                    $interpreted[$field] = null;
                    $issues[] = $this->issue(
                        'PLACEHOLDER_VALUE_IGNORED',
                        'warning',
                        $field,
                        'A configured source placeholder was normalized to null.'
                    );
                } else {
                    $interpreted[$field] = $normalizedValue !== '' ? $normalizedValue : null;
                }
            }

            $rawPayload['interpreted_descriptive_fields'] = $interpreted;

            if ($row->parseError !== null) {
                $issues[] = $this->issue('PARSE_ERROR', 'error', null, 'The source row could not be parsed cleanly.');
            }

            if ($mapping === null) {
                $issues[] = $this->issue('UNKNOWN_SOURCE_TYPE', 'error', 'source_type', 'The source type is not configured.');
                $issues[] = $this->issue('UNSUPPORTED_ROW', 'error', 'source_type', 'The row cannot be interpreted by this source adapter.');
            }

            if ($title === '') {
                $issues[] = $this->issue('EMPTY_TITLE', 'error', 'approved_title', 'The approved title is empty.');
            }

            $derivedParent = null;
            $levelCode = $mapping['geographic_level_code'] ?? null;

            if ($code === '') {
                $issues[] = $this->issue('MISSING_HIERARCHY_CODE', 'error', 'hierarchy_code', 'The hierarchy code is missing.');
            } elseif (preg_match('/^\d+$/D', $code) !== 1) {
                $issues[] = $this->issue('INVALID_HIERARCHY_CODE', 'error', 'hierarchy_code', 'The hierarchy code must contain digits only.');
            } elseif ($mapping !== null) {
                $expectedLength = (int) ($mapping['expected_code_length'] ?? 0);
                $parentPrefixLength = (int) ($mapping['parent_prefix_length'] ?? 0);

                if ($expectedLength > 0 && strlen($code) !== $expectedLength) {
                    $issues[] = $this->issue(
                        'INVALID_CODE_LENGTH',
                        'error',
                        'hierarchy_code',
                        'The hierarchy code length does not match source metadata.',
                        ['expected_length' => $expectedLength]
                    );
                }

                if ($levelCode === 'province') {
                    $derivedParent = $countryRoot !== '' ? $countryRoot : null;
                } elseif ($parentPrefixLength > 0 && strlen($code) > $parentPrefixLength) {
                    $derivedParent = substr($code, 0, $parentPrefixLength);
                } else {
                    $issues[] = $this->issue(
                        'INVALID_PARENT_PREFIX',
                        'error',
                        'hierarchy_code',
                        'A parent prefix could not be derived from source metadata.'
                    );
                }
            }

            if ($identifier !== '' && preg_match('/^\d+$/D', $identifier) !== 1) {
                $issues[] = $this->issue(
                    'INVALID_NATIONAL_IDENTIFIER',
                    'warning',
                    'national_identifier',
                    'The national identifier contains unsupported characters.'
                );
            }

            $validated[$index] = [
                'row' => $row,
                'mapping' => $mapping,
                'normalized_title' => $title,
                'source_code' => $code,
                'national_identifier' => $identifier,
                'derived_level_code' => $levelCode,
                'derived_parent_code' => $derivedParent,
                'raw_payload' => $rawPayload,
                'issues' => $issues,
            ];

            if ($code !== '') {
                $codeGroups[$code][] = $index;
            }

            if ($identifier !== '') {
                $identifierGroups[$identifier][] = $index;
            }
        }

        foreach ($codeGroups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $validated[$index]['issues'][] = $this->issue(
                    'DUPLICATE_HIERARCHY_CODE',
                    'error',
                    'hierarchy_code',
                    'The hierarchy code occurs more than once in this snapshot.',
                    ['occurrences' => count($indexes)]
                );
            }
        }

        foreach ($validated as $index => &$item) {
            $mapping = $item['mapping'];
            $parentCode = $item['derived_parent_code'];

            if ($mapping === null
                || ($item['derived_level_code'] ?? null) === 'province'
                || $item['source_code'] === ''
                || $parentCode === null
            ) {
                continue;
            }

            $parentIndexes = $codeGroups[$parentCode] ?? [];

            if ($parentIndexes === []) {
                $item['issues'][] = $this->issue(
                    'MISSING_PARENT_CODE',
                    'error',
                    'hierarchy_code',
                    'The code-derived parent is missing from this snapshot.'
                );
                continue;
            }

            $expectedParentLevel = $mapping['parent_geographic_level_code'] ?? null;
            $compatibleParentFound = false;

            foreach ($parentIndexes as $parentIndex) {
                if ($parentIndex === $index) {
                    continue;
                }

                if (($validated[$parentIndex]['derived_level_code'] ?? null) === $expectedParentLevel) {
                    $compatibleParentFound = true;
                    break;
                }
            }

            if (!$compatibleParentFound) {
                $item['issues'][] = $this->issue(
                    'PARENT_LEVEL_MISMATCH',
                    'error',
                    'hierarchy_code',
                    'The code-derived parent has an incompatible configured level.',
                    ['expected_parent_level' => $expectedParentLevel]
                );
            }
        }
        unset($item);

        foreach ($identifierGroups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $titles = [];
            $parents = [];

            foreach ($indexes as $index) {
                $titles[] = $validated[$index]['normalized_title'];
                $parents[] = (string) ($validated[$index]['derived_parent_code'] ?? '');
            }

            $titleVaries = count(array_unique($titles)) > 1;
            $parentVaries = count(array_unique($parents)) > 1;

            foreach ($indexes as $index) {
                $validated[$index]['issues'][] = $this->issue(
                    'DUPLICATE_NATIONAL_IDENTIFIER',
                    'warning',
                    'national_identifier',
                    'The national identifier occurs more than once and requires review.',
                    ['occurrences' => count($indexes)]
                );

                if ($titleVaries) {
                    $validated[$index]['issues'][] = $this->issue(
                        'IDENTIFIER_TITLE_VARIATION',
                        'warning',
                        'national_identifier',
                        'The repeated identifier is associated with different normalized titles.'
                    );
                }

                if ($parentVaries) {
                    $validated[$index]['issues'][] = $this->issue(
                        'IDENTIFIER_PARENT_VARIATION',
                        'warning',
                        'national_identifier',
                        'The repeated identifier is associated with different code-derived parents.'
                    );
                }
            }
        }

        $counts = ['valid' => 0, 'warning' => 0, 'invalid' => 0];
        $sourceTypeCounts = [];
        $issueCounts = [];

        foreach ($validated as &$item) {
            $hasError = false;
            $hasWarning = false;

            foreach ($item['issues'] as $issue) {
                $issueCounts[$issue['code']] = ($issueCounts[$issue['code']] ?? 0) + 1;
                $hasError = $hasError || $issue['severity'] === 'error';
                $hasWarning = $hasWarning || $issue['severity'] === 'warning';
            }

            $status = $hasError ? 'invalid' : ($hasWarning ? 'warning' : 'valid');
            $item['validation_status'] = $status;
            $item['row_checksum'] = hash(
                'sha256',
                json_encode(
                    $item['raw_payload'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                ) ?: ''
            );
            $counts[$status]++;

            if ($item['derived_level_code'] !== null) {
                $sourceTypeCounts[$item['derived_level_code']] = ($sourceTypeCounts[$item['derived_level_code']] ?? 0) + 1;
            }
        }
        unset($item);

        ksort($issueCounts);
        ksort($sourceTypeCounts);

        return [
            'rows' => array_values($validated),
            'counts' => $counts,
            'source_type_counts' => $sourceTypeCounts,
            'issue_counts' => $issueCounts,
        ];
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
