<?php

namespace App\Services\Automation;

class CoreReferenceType
{
    public const PERSON = 'person';
    public const USER = 'user';
    public const ORGANIZATION = 'organization';
    public const ORG_UNIT = 'org_unit';
    public const POSITION = 'position';
    public const APPOINTMENT = 'appointment';
    public const FISCAL_YEAR = 'fiscal_year';
    public const GEOGRAPHIC_LOCATION = 'geographic_location';

    public static function all(): array
    {
        return [
            self::PERSON,
            self::USER,
            self::ORGANIZATION,
            self::ORG_UNIT,
            self::POSITION,
            self::APPOINTMENT,
            self::FISCAL_YEAR,
            self::GEOGRAPHIC_LOCATION,
        ];
    }
}
