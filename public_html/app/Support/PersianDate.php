<?php

namespace App\Support;

final class PersianDate
{
    public static function normalizeDigits(string $value): string
    {
        return \IPKF\Support\PersianDate::normalizeDigits($value);
    }

    public static function toGregorianDate(?string $value): ?string
    {
        return \IPKF\Support\PersianDate::toGregorianDate($value);
    }

    public static function fromGregorianDate(?string $value, bool $persianDigits = true): string
    {
        return \IPKF\Support\PersianDate::fromGregorianDate($value, $persianDigits);
    }
}
