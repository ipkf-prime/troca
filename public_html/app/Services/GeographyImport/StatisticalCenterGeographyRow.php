<?php

namespace App\Services\GeographyImport;

class StatisticalCenterGeographyRow
{
    public function __construct(
        public readonly int $sourceRowNumber,
        public readonly string $provinceCode,
        public readonly string $provinceTitle,
        public readonly string $countyCode,
        public readonly string $countyTitle,
        public readonly string $districtCode,
        public readonly string $districtTitle,
        public readonly string $ruralOrCityCode,
        public readonly string $ruralDistrictTitle,
        public readonly string $settlementCode,
        public readonly string $sourceTitle,
        public readonly string $coderec,
        public readonly string $diag,
        public readonly array $rawColumns,
        public readonly ?string $parseError = null
    ) {
    }

    public function rawPayload(): array
    {
        return ['raw_columns' => $this->rawColumns];
    }
}
