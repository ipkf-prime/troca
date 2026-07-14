<?php

namespace App\Services\GeographyImport;

class PersianTextNormalizer
{
    public function text(mixed $value): string
    {
        $value = strtr((string) ($value ?? ''), [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $value = preg_replace('/[\x{200C}\x{200D}]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/[\x{200B}\x{200E}\x{200F}\x{2060}\x{FEFF}]+/u', '', $value) ?? $value;

        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    public function code(mixed $value): string
    {
        return trim($this->text($value));
    }

    public function header(mixed $value): string
    {
        $value = strtolower($this->text($value));

        return preg_replace('/[\s_\-:،,()（）]+/u', '', $value) ?? $value;
    }
}
