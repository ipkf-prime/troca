<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

/**
 * TICKETING_ACCESS_GRANT_EVALUATOR_V1
 *
 * Pure authorization decision engine.
 *
 * Canonical semantics:
 *
 * - Grants compose with OR.
 * - Active dimension rules inside one Grant compose with AND.
 * - Active resource rules inside one Grant compose with AND.
 * - Values inside one rule use ANY or ALL.
 * - Descendant matching is explicit.
 * - Empty restricted grants fail closed.
 * - Grants containing only inactive restrictions fail closed.
 * - Malformed rules fail closed.
 * - Full data access requires explicit is_unrestricted.
 *
 * Runtime DB/query integration is deferred to A5L5.
 */
final class TicketingAccessGrantEvaluator
{
    public function matches(
        array $grants,
        array $subjectFacts,
        array $resourceContext = []
    ): bool {
        foreach ($grants as $grant) {
            if (!is_array($grant)) {
                continue;
            }

            if (
                (string) ($grant['status'] ?? 'active')
                !== 'active'
            ) {
                continue;
            }

            $rawDimensionRules =
                is_array(
                    $grant['dimension_rules']
                    ?? null
                )
                    ? $grant['dimension_rules']
                    : [];

            $rawResourceRules =
                is_array(
                    $grant['resource_rules']
                    ?? null
                )
                    ? $grant['resource_rules']
                    : [];

            $dimensionRules =
                $this->activeRules(
                    $rawDimensionRules
                );

            $resourceRules =
                $this->activeRules(
                    $rawResourceRules
                );

            /*
             * Malformed rule structures never authorize.
             */
            if (
                $dimensionRules === null
                || $resourceRules === null
            ) {
                continue;
            }

            /*
             * Important fail-closed rule:
             *
             * A restricted Grant with no ACTIVE rules does not
             * become unrestricted merely because its old rules
             * were disabled.
             */
            if (
                $dimensionRules === []
                && $resourceRules === []
            ) {
                if (!empty($grant['is_unrestricted'])) {
                    return true;
                }

                continue;
            }

            if (
                !$this->dimensionRulesMatch(
                    $dimensionRules,
                    $subjectFacts
                )
            ) {
                continue;
            }

            if (
                !$this->resourceRulesMatch(
                    $resourceRules,
                    $resourceContext
                )
            ) {
                continue;
            }

            /*
             * One complete Grant matched.
             *
             * Grants compose with OR.
             */
            return true;
        }

        return false;
    }


    private function activeRules(
        array $rules
    ): ?array {
        $active = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                return null;
            }

            if (
                (string) ($rule['status'] ?? 'active')
                !== 'active'
            ) {
                continue;
            }

            $active[] = $rule;
        }

        return $active;
    }


    private function dimensionRulesMatch(
        array $rules,
        array $subjectFacts
    ): bool {
        foreach ($rules as $rule) {
            $dimensionKey =
                $this->dimensionKey($rule);

            if ($dimensionKey === '') {
                return false;
            }

            $selected =
                $this->selectedValues(
                    $rule['values']
                    ?? []
                );

            /*
             * A rule with no selected values is invalid and
             * therefore fail-closed.
             */
            if ($selected === []) {
                return false;
            }

            $facts =
                $this->factsForDimension(
                    $subjectFacts,
                    $dimensionKey
                );

            if ($facts === []) {
                return false;
            }

            $mode =
                strtolower(
                    trim(
                        (string) (
                            $rule['match_mode_code']
                            ?? 'any'
                        )
                    )
                );

            if (
                !in_array(
                    $mode,
                    [
                        'any',
                        'all',
                    ],
                    true
                )
            ) {
                return false;
            }

            $includeDescendants =
                !empty(
                    $rule['include_descendants']
                );

            $selectedMatches =
                static function (
                    string $selectedValue
                ) use (
                    $facts,
                    $includeDescendants
                ): bool {
                    foreach ($facts as $fact) {
                        $actual =
                            trim(
                                (string) (
                                    $fact['value']
                                    ?? ''
                                )
                            );

                        if (
                            $actual !== ''
                            && $actual === $selectedValue
                        ) {
                            return true;
                        }

                        if (!$includeDescendants) {
                            continue;
                        }

                        $ancestors =
                            is_array(
                                $fact['ancestors']
                                ?? null
                            )
                                ? array_map(
                                    'strval',
                                    $fact['ancestors']
                                )
                                : [];

                        if (
                            in_array(
                                $selectedValue,
                                $ancestors,
                                true
                            )
                        ) {
                            return true;
                        }
                    }

                    return false;
                };

            if ($mode === 'any') {
                $matched = false;

                foreach ($selected as $value) {
                    if ($selectedMatches($value)) {
                        $matched = true;
                        break;
                    }
                }

                if (!$matched) {
                    return false;
                }

                continue;
            }

            foreach ($selected as $value) {
                if (!$selectedMatches($value)) {
                    return false;
                }
            }
        }

        return true;
    }


    private function resourceRulesMatch(
        array $rules,
        array $resourceContext
    ): bool {
        foreach ($rules as $rule) {
            $type =
                strtolower(
                    trim(
                        (string) (
                            $rule['resource_type_code']
                            ?? ''
                        )
                    )
                );

            if ($type === '') {
                return false;
            }

            $selected =
                $this->selectedValues(
                    $rule['values']
                    ?? []
                );

            if ($selected === []) {
                return false;
            }

            $actual =
                $this->actualResourceValues(
                    $resourceContext[$type]
                    ?? null
                );

            if ($actual === []) {
                return false;
            }

            $mode =
                strtolower(
                    trim(
                        (string) (
                            $rule['match_mode_code']
                            ?? 'any'
                        )
                    )
                );

            if (
                !in_array(
                    $mode,
                    [
                        'any',
                        'all',
                    ],
                    true
                )
            ) {
                return false;
            }

            if ($mode === 'any') {
                if (
                    array_intersect(
                        $selected,
                        $actual
                    ) === []
                ) {
                    return false;
                }

                continue;
            }

            foreach ($selected as $value) {
                if (
                    !in_array(
                        $value,
                        $actual,
                        true
                    )
                ) {
                    return false;
                }
            }
        }

        return true;
    }


    private function dimensionKey(
        array $rule
    ): string {
        foreach ([
            'dimension_id',
            'dimension_reference',
            'dimension',
        ] as $key) {
            $value =
                trim(
                    (string) (
                        $rule[$key]
                        ?? ''
                    )
                );

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }


    private function factsForDimension(
        array $facts,
        string $dimensionKey
    ): array {
        $rows =
            $facts[$dimensionKey]
            ?? null;

        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (is_scalar($row)) {
                $value =
                    trim(
                        (string) $row
                    );

                if ($value !== '') {
                    $normalized[] = [
                        'value' => $value,
                        'ancestors' => [],
                    ];
                }

                continue;
            }

            if (!is_array($row)) {
                continue;
            }

            $value = '';

            foreach ([
                'value',
                'value_reference',
                'dimension_value_id',
            ] as $key) {
                $candidate =
                    trim(
                        (string) (
                            $row[$key]
                            ?? ''
                        )
                    );

                if ($candidate !== '') {
                    $value = $candidate;
                    break;
                }
            }

            if ($value === '') {
                continue;
            }

            $ancestors =
                is_array(
                    $row['ancestors']
                    ?? null
                )
                    ? array_values(
                        array_filter(
                            array_map(
                                static fn (
                                    mixed $ancestor
                                ): string =>
                                    trim(
                                        (string) $ancestor
                                    ),
                                $row['ancestors']
                            ),
                            static fn (
                                string $ancestor
                            ): bool =>
                                $ancestor !== ''
                        )
                    )
                    : [];

            $normalized[] = [
                'value' => $value,
                'ancestors' => $ancestors,
            ];
        }

        return $normalized;
    }


    private function selectedValues(
        mixed $raw
    ): array {
        if (!is_array($raw)) {
            return [];
        }

        $values = [];

        foreach ($raw as $row) {
            if (is_scalar($row)) {
                $value =
                    trim(
                        (string) $row
                    );

            } elseif (is_array($row)) {
                $value = '';

                foreach ([
                    'value',
                    'value_reference',
                    'dimension_value_id',
                    'resource_reference',
                ] as $key) {
                    $candidate =
                        trim(
                            (string) (
                                $row[$key]
                                ?? ''
                            )
                        );

                    if ($candidate !== '') {
                        $value = $candidate;
                        break;
                    }
                }

            } else {
                continue;
            }

            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }


    private function actualResourceValues(
        mixed $raw
    ): array {
        if ($raw === null) {
            return [];
        }

        $rows =
            is_array($raw)
                ? $raw
                : [$raw];

        $values = [];

        foreach ($rows as $row) {
            if (is_scalar($row)) {
                $value =
                    trim(
                        (string) $row
                    );

            } elseif (is_array($row)) {
                $value =
                    trim(
                        (string) (
                            $row['resource_reference']
                            ?? $row['value']
                            ?? ''
                        )
                    );

            } else {
                continue;
            }

            if ($value !== '') {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }
}
