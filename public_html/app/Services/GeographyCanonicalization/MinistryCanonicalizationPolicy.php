<?php

namespace App\Services\GeographyCanonicalization;

final class MinistryCanonicalizationPolicy
{
    public const SOURCE_CODE = 'iran_ministry_of_interior';
    public const CANONICALIZATION_TYPE = 'ministry_official_administrative';
    public const ALGORITHM_VERSION = 'ministry-canonical-v1';
    public const HIERARCHY_TYPE = 'official_administrative';
    public const RELATION_TYPE = 'administrative_parent';
    public const CODING_SYSTEM = 'iran_moi_administrative';
    public const HIERARCHY_CODE_SET = 'administrative_location_code';
    public const HIERARCHY_IDENTIFIER = 'ministry_hierarchy_code';
    public const NATIONAL_IDENTIFIER = 'ministry_national_identifier';
    public const COUNTRY_CODE = 'IR';
    public const COUNTRY_ISO = 'IR';
    public const COUNTRY_TITLE = 'ایران';
    public const FINGERPRINT_PREFIX_LENGTH = 16;
    public const APPLY_CHUNK_SIZE = 250;

    public const LEVELS = [
        'province' => ['order' => 20, 'parent' => 'country'],
        'county' => ['order' => 30, 'parent' => 'province'],
        'district' => ['order' => 40, 'parent' => 'county'],
        'rural_district' => ['order' => 50, 'parent' => 'district'],
        'city' => ['order' => 60, 'parent' => 'district'],
    ];

    public static function levelSupported(?string $level): bool
    {
        return $level !== null && array_key_exists($level, self::LEVELS);
    }
}
