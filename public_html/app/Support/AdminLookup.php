<?php

namespace App\Support;

class AdminLookup
{
    public const EMPTY = '—';
    public const UNKNOWN = 'نامشخص';

    public static function value(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? self::EMPTY : $value;
    }

    public static function title(mixed $title, bool $referenceExists = false): string
    {
        $title = trim((string) ($title ?? ''));

        if ($title !== '') {
            return $title;
        }

        return $referenceExists ? self::UNKNOWN : self::EMPTY;
    }

    public static function booleanYesNo(mixed $value): string
    {
        return ((int) $value) === 1 ? 'بله' : 'خیر';
    }

    public static function status(string $status): array
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'active' => ['code' => 'active', 'label' => 'فعال'],
            'inactive', 'disabled' => ['code' => 'inactive', 'label' => 'غیرفعال'],
            'blocked', 'locked' => ['code' => 'blocked', 'label' => 'مسدود'],
            'pending' => ['code' => 'pending', 'label' => 'در انتظار'],
            'revoked' => ['code' => 'inactive', 'label' => 'لغوشده'],
            'expired' => ['code' => 'inactive', 'label' => 'منقضی‌شده'],
            default => ['code' => 'unknown', 'label' => $normalized === '' ? self::EMPTY : self::UNKNOWN],
        };
    }

    public static function personType(mixed $title, mixed $code): string
    {
        $title = trim((string) ($title ?? ''));

        if ($title !== '') {
            return $title;
        }

        return match (strtolower(trim((string) ($code ?? '')))) {
            '' => self::EMPTY,
            'individual', 'person', 'real' => 'شخص حقیقی',
            'legal', 'company' => 'شخص حقوقی',
            'organization' => 'سازمان',
            'cooperative' => 'تعاونی',
            default => self::UNKNOWN,
        };
    }

    public static function orgUnitType(mixed $value): string
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return match ($normalized) {
            '' => self::EMPTY,
            'department' => 'اداره',
            'unit' => 'واحد',
            'section' => 'بخش',
            'branch' => 'شعبه',
            'office' => 'دفتر',
            'team' => 'تیم',
            'secretariat' => 'دبیرخانه',
            'management' => 'مدیریت',
            default => self::UNKNOWN,
        };
    }

    public static function scopeSummary(string $scopeType, array $labels = []): string
    {
        $scopeType = strtolower(trim($scopeType));

        return match ($scopeType) {
            '', 'global' => 'سراسری',
            'organization' => 'سازمان: ' . self::title($labels['organization_title'] ?? null, (bool) ($labels['organization_reference_exists'] ?? false)),
            'province' => 'استان: ' . self::title($labels['province_title'] ?? null, (bool) ($labels['province_reference_exists'] ?? false)),
            'city' => 'شهر: ' . self::title($labels['city_title'] ?? null, (bool) ($labels['city_reference_exists'] ?? false)),
            default => self::UNKNOWN,
        };
    }
}
