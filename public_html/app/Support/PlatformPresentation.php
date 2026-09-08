<?php

declare(strict_types=1);

namespace App\Support;

final class PlatformPresentation
{
    public const LOCALE = 'fa-IR';

    public const DIRECTION = 'rtl';


    public static function digits(mixed $value): string
    {
        return AdminFormat::digits(
            $value
        );
    }


    public static function normalizeDigits(
        mixed $value
    ): string {
        return JalaliDateInput::englishDigits(
            (string) (
                $value
                ?? ''
            )
        );
    }


    public static function jalaliDate(
        mixed $value
    ): string {
        return AdminFormat::jalaliDate(
            $value
        );
    }


    public static function jalaliDateTime(
        mixed $value
    ): string {
        return AdminFormat::jalaliDateTime(
            $value
        );
    }


    public static function jalaliInputFromGregorian(
        mixed $value
    ): string {
        $jalali =
            JalaliDateInput::fromGregorian(
                $value
            );

        return self::digits(
            $jalali
        );
    }


    public static function gregorianDateFromJalali(
        mixed $value
    ): ?string {
        return JalaliDateInput::toGregorian(
            $value
        );
    }
}
