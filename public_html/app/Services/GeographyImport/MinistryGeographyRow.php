<?php

namespace App\Services\GeographyImport;

class MinistryGeographyRow
{
    public function __construct(
        public readonly int $sourceRowNumber,
        public readonly string $sourceOrdinal,
        public readonly string $sourceType,
        public readonly string $approvedTitle,
        public readonly string $provinceTitle,
        public readonly string $countyTitle,
        public readonly string $districtTitle,
        public readonly string $hierarchyCode,
        public readonly string $nationalIdentifier,
        public readonly string $sourceNote,
        public readonly ?string $parseError = null
    ) {
    }

    public function rawPayload(): array
    {
        return [
            'source_ordinal' => $this->sourceOrdinal,
            'source_type' => $this->sourceType,
            'approved_title' => $this->approvedTitle,
            'province_title' => $this->provinceTitle,
            'county_title' => $this->countyTitle,
            'district_title' => $this->districtTitle,
            'hierarchy_code' => $this->hierarchyCode,
            'national_identifier' => $this->nationalIdentifier,
            'source_note' => $this->sourceNote,
        ];
    }
}
