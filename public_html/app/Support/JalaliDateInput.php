<?php

namespace App\Support;

use DateTimeImmutable;

class JalaliDateInput
{
    public static function fromGregorian(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    (int) ($errors['warning_count'] ?? 0) > 0
                    || (int) ($errors['error_count'] ?? 0) > 0
                )
            )
        ) {
            return '';
        }

        [$year, $month, $day] = self::gregorianToJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );

        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    public static function toGregorian(mixed $value): ?string
    {
        $value = self::englishDigits(
            trim((string) ($value ?? ''))
        );

        if ($value === '') {
            return null;
        }

        if (
            preg_match(
                '/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/',
                $value,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if (
            $year < 1200
            || $year > 1600
            || $month < 1
            || $month > 12
            || $day < 1
            || $day > 31
        ) {
            return null;
        }

        [$gy, $gm, $gd] = self::jalaliToGregorian(
            $year,
            $month,
            $day
        );
        $gregorian = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);

        // Round-trip validation rejects impossible Jalali dates.
        if (self::fromGregorian($gregorian) !== sprintf(
            '%04d/%02d/%02d',
            $year,
            $month,
            $day
        )) {
            return null;
        }

        return $gregorian;
    }

    public static function englishDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function gregorianToJalali(
        int $year,
        int $month,
        int $day
    ): array {
        $gregorianMonthDays = [
            31, 28, 31, 30, 31, 30,
            31, 31, 30, 31, 30, 31,
        ];
        $jalaliMonthDays = [
            31, 31, 31, 31, 31, 31,
            30, 30, 30, 30, 30, 29,
        ];

        $year -= 1600;
        $month -= 1;
        $day -= 1;

        $gregorianDayNumber = 365 * $year
            + intdiv($year + 3, 4)
            - intdiv($year + 99, 100)
            + intdiv($year + 399, 400);

        for ($index = 0; $index < $month; $index++) {
            $gregorianDayNumber += $gregorianMonthDays[$index];
        }

        if (
            $month > 1
            && (
                ($year % 4 === 0 && $year % 100 !== 0)
                || $year % 400 === 0
            )
        ) {
            $gregorianDayNumber++;
        }

        $gregorianDayNumber += $day;
        $jalaliDayNumber = $gregorianDayNumber - 79;
        $jalaliCycle = intdiv($jalaliDayNumber, 12053);
        $jalaliDayNumber %= 12053;
        $jalaliYear = 979
            + 33 * $jalaliCycle
            + 4 * intdiv($jalaliDayNumber, 1461);
        $jalaliDayNumber %= 1461;

        if ($jalaliDayNumber >= 366) {
            $jalaliYear += intdiv(
                $jalaliDayNumber - 1,
                365
            );
            $jalaliDayNumber =
                ($jalaliDayNumber - 1) % 365;
        }

        for (
            $index = 0;
            $index < 11
            && $jalaliDayNumber >= $jalaliMonthDays[$index];
            $index++
        ) {
            $jalaliDayNumber -= $jalaliMonthDays[$index];
        }

        return [
            $jalaliYear,
            $index + 1,
            $jalaliDayNumber + 1,
        ];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function jalaliToGregorian(
        int $year,
        int $month,
        int $day
    ): array {
        $year += 1595;
        $days = -355668
            + 365 * $year
            + intdiv($year, 33) * 8
            + intdiv(($year % 33) + 3, 4)
            + $day
            + (
                $month < 7
                    ? ($month - 1) * 31
                    : ($month - 7) * 30 + 186
            );

        $gregorianYear = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $gregorianYear += 100 * intdiv(--$days, 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gregorianYear += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gregorianYear += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gregorianDay = $days + 1;
        $monthDays = [
            0,
            31,
            self::gregorianLeap($gregorianYear) ? 29 : 28,
            31,
            30,
            31,
            30,
            31,
            31,
            30,
            31,
            30,
            31,
        ];

        for (
            $gregorianMonth = 1;
            $gregorianMonth <= 12
            && $gregorianDay > $monthDays[$gregorianMonth];
            $gregorianMonth++
        ) {
            $gregorianDay -= $monthDays[$gregorianMonth];
        }

        return [
            $gregorianYear,
            $gregorianMonth,
            $gregorianDay,
        ];
    }

    private static function gregorianLeap(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0)
            || $year % 400 === 0;
    }
}
