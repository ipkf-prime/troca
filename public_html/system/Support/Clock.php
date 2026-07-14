<?php

namespace IPKF\Support;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class Clock
{
    public const STORAGE_TIMEZONE = 'UTC';
    public const DATABASE_SESSION_TIMEZONE = '+00:00';
    public const STORAGE_POLICY = 'utc_instant';

    public static function nowUtc(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::utcTimezone());
    }

    public static function databaseTimestamp(?DateTimeImmutable $time = null): string
    {
        return ($time ?? self::nowUtc())
            ->setTimezone(self::utcTimezone())
            ->format('Y-m-d H:i:s');
    }

    public static function isoUtc(DateTimeImmutable $time): string
    {
        return $time
            ->setTimezone(self::utcTimezone())
            ->format('Y-m-d\TH:i:s\Z');
    }

    public static function parseStoredInstant(mixed $value): ?DateTimeImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', $value) === 1) {
                return (new DateTimeImmutable($value))->setTimezone(self::utcTimezone());
            }

            return new DateTimeImmutable($value, self::utcTimezone());
        } catch (Throwable) {
            return null;
        }
    }

    public static function displayTimezone(): DateTimeZone
    {
        $timezone = trim((string) Env::get('APP_TIMEZONE', 'Asia/Tehran'));

        try {
            return new DateTimeZone($timezone !== '' ? $timezone : 'Asia/Tehran');
        } catch (Throwable) {
            return new DateTimeZone('Asia/Tehran');
        }
    }

    public static function displayTimezoneName(): string
    {
        return self::displayTimezone()->getName();
    }

    public static function convertToDisplayTimezone(DateTimeImmutable $time): DateTimeImmutable
    {
        return $time->setTimezone(self::displayTimezone());
    }

    public static function formatDateTime(mixed $value, string $format = 'Y-m-d H:i:s'): ?string
    {
        $instant = self::parseStoredInstant($value);

        if ($instant === null) {
            return null;
        }

        return self::convertToDisplayTimezone($instant)->format($format);
    }

    public static function formatDate(mixed $value, string $format = 'Y-m-d'): ?string
    {
        $date = self::parseDateOnly($value);

        if ($date === null) {
            return null;
        }

        return $date->format($format);
    }

    public static function parseDateOnly(mixed $value): ?DateTimeImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches) !== 1) {
            return null;
        }

        try {
            return new DateTimeImmutable(
                sprintf('%04d-%02d-%02d 00:00:00', (int) $matches[1], (int) $matches[2], (int) $matches[3]),
                self::utcTimezone()
            );
        } catch (Throwable) {
            return null;
        }
    }

    public static function fixedInstantVerificationPassed(): bool
    {
        $instant = self::parseStoredInstant('2026-07-13 12:00:00');

        if ($instant === null) {
            return false;
        }

        $tehran = $instant->setTimezone(new DateTimeZone('Asia/Tehran'));

        if ($tehran->format('Y-m-d H:i:s') !== '2026-07-13 15:30:00') {
            return false;
        }

        $dateOnly = self::parseDateOnly('2026-07-13');

        return $dateOnly !== null && $dateOnly->format('Y-m-d') === '2026-07-13';
    }

    public static function utcTimezone(): DateTimeZone
    {
        return new DateTimeZone(self::STORAGE_TIMEZONE);
    }
}
