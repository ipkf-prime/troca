<?php

namespace App\Services;

class IdentityNormalizer extends BaseService
{
    public function email(string $value): ?string
    {
        $value = strtolower(trim($this->englishDigits($value)));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    public function mobile(string $value): ?string
    {
        $value = preg_replace('/[\s\-\(\)]+/', '', trim($this->englishDigits($value))) ?? '';
        $value = ltrim($value, '+');

        if (preg_match('/^09\d{9}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^9\d{9}$/', $value) === 1) {
            return '0' . $value;
        }

        if (preg_match('/^989\d{9}$/', $value) === 1) {
            return '0' . substr($value, 2);
        }

        return null;
    }

    public function username(string $value): ?string
    {
        $value = strtolower(trim($this->englishDigits($value)));

        return preg_match('/^[a-z0-9_\.]{3,100}$/', $value) === 1 ? $value : null;
    }

    public function englishDigits(string $value): string
    {
        return strtr($value, [
            "\u{06F0}" => '0',
            "\u{06F1}" => '1',
            "\u{06F2}" => '2',
            "\u{06F3}" => '3',
            "\u{06F4}" => '4',
            "\u{06F5}" => '5',
            "\u{06F6}" => '6',
            "\u{06F7}" => '7',
            "\u{06F8}" => '8',
            "\u{06F9}" => '9',
            "\u{0660}" => '0',
            "\u{0661}" => '1',
            "\u{0662}" => '2',
            "\u{0663}" => '3',
            "\u{0664}" => '4',
            "\u{0665}" => '5',
            "\u{0666}" => '6',
            "\u{0667}" => '7',
            "\u{0668}" => '8',
            "\u{0669}" => '9',
        ]);
    }
}
