<?php

namespace App\Services\GeographyCrosswalk;

class GeographyCrosswalkPolicy
{
    public const CROSSWALK_TYPE = 'ministry_to_statistical_center';
    public const ALGORITHM_VERSION = 'ministry-sci-v1';

    public const ISSUE_CODES = [
        'EXACT_HIERARCHY_CANDIDATE',
        'SAFE_NORMALIZATION_CANDIDATE',
        'MULTIPLE_TARGET_CANDIDATES',
        'NO_TARGET_CANDIDATE',
        'PARENT_CANDIDATE_UNRESOLVED',
        'LEVEL_MISMATCH',
        'TITLE_MISMATCH',
        'NUMBERED_URBAN_UNIT_EXCLUDED',
        'SETTLEMENT_NOT_IN_MINISTRY_SCOPE',
        'SOURCE_ROW_INVALID',
        'MINISTRY_HISTORY_VARIATION',
        'SCI_HIERARCHY_CONFLICT',
    ];

    public const LEVELS = [
        [
            'source_kind' => 'province_observation',
            'target_level' => 'province',
            'parent_source_kind' => null,
            'candidate_kind' => 'province',
            'city_candidate' => false,
        ],
        [
            'source_kind' => 'county_observation',
            'target_level' => 'county',
            'parent_source_kind' => 'province_observation',
            'candidate_kind' => 'county',
            'city_candidate' => false,
        ],
        [
            'source_kind' => 'district_observation',
            'target_level' => 'district',
            'parent_source_kind' => 'county_observation',
            'candidate_kind' => 'district',
            'city_candidate' => false,
        ],
        [
            'source_kind' => 'rural_district_observation',
            'target_level' => 'rural_district',
            'parent_source_kind' => 'district_observation',
            'candidate_kind' => 'rural_district',
            'city_candidate' => false,
        ],
        [
            'source_kind' => 'statistical_urban_unit',
            'target_level' => 'city',
            'parent_source_kind' => 'district_observation',
            'candidate_kind' => 'official_city_candidate',
            'city_candidate' => true,
        ],
    ];

    public function isNumberedStatisticalUrbanUnit(string $normalizedTitle): bool
    {
        return preg_match('/\d+\s*$/u', $normalizedTitle) === 1;
    }

    public function deterministicStatus(
        int $candidateCount,
        bool $rawTitleExact,
        bool $parentProbable,
        bool $cityCandidate
    ): string {
        if ($candidateCount !== 1) {
            return 'ambiguous';
        }

        if ($cityCandidate || !$rawTitleExact || $parentProbable) {
            return 'probable';
        }

        return 'exact';
    }
}
