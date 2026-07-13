<?php

namespace App\Support;

class AdminFormat
{
    public static function digits(mixed $value): string
    {
        return strtr((string) ($value ?? ''), [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }

    public static function jalaliDateTime(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '';
        }

        try {
            $timezoneName = trim((string) \IPKF\Support\Env::get('APP_TIMEZONE', 'Asia/Tehran'));
            $timezone = new \DateTimeZone($timezoneName !== '' ? $timezoneName : 'Asia/Tehran');
            $date = (new \DateTimeImmutable($value))->setTimezone($timezone);
            [$year, $month, $day] = self::gregorianToJalali(
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j')
            );

            return self::digits(sprintf('%04d/%02d/%02d %s', $year, $month, $day, $date->format('H:i')));
        } catch (\Throwable) {
            return self::digits($value);
        }
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private static function gregorianToJalali(int $year, int $month, int $day): array
    {
        $gregorianMonthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jalaliMonthDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $year -= 1600;
        $month -= 1;
        $day -= 1;

        $gregorianDayNumber = 365 * $year
            + intdiv($year + 3, 4)
            - intdiv($year + 99, 100)
            + intdiv($year + 399, 400);

        for ($i = 0; $i < $month; $i++) {
            $gregorianDayNumber += $gregorianMonthDays[$i];
        }

        if ($month > 1 && (($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0)) {
            $gregorianDayNumber++;
        }

        $gregorianDayNumber += $day;
        $jalaliDayNumber = $gregorianDayNumber - 79;
        $jalaliCycle = intdiv($jalaliDayNumber, 12053);
        $jalaliDayNumber %= 12053;
        $jalaliYear = 979 + 33 * $jalaliCycle + 4 * intdiv($jalaliDayNumber, 1461);
        $jalaliDayNumber %= 1461;

        if ($jalaliDayNumber >= 366) {
            $jalaliYear += intdiv($jalaliDayNumber - 1, 365);
            $jalaliDayNumber = ($jalaliDayNumber - 1) % 365;
        }

        for ($i = 0; $i < 11 && $jalaliDayNumber >= $jalaliMonthDays[$i]; $i++) {
            $jalaliDayNumber -= $jalaliMonthDays[$i];
        }

        return [$jalaliYear, $i + 1, $jalaliDayNumber + 1];
    }
}
