<?php

declare(strict_types=1);

namespace App\Services\UiContent;


/**
 * Last-resort presentation.
 *
 * Normal content belongs to the dynamic database-backed
 * content engine. This catalog exists only so a database or
 * schema failure never degrades to a blank/unsafe error page.
 */
final class UiContentEmergencyCatalog
{
    public function forHttpStatus(
        int $status
    ): array {

        [$title, $body, $severity] =
            match ($status) {

                403 => [
                    'دسترسی مجاز نیست',
                    'امکان دسترسی به این بخش با سطح دسترسی فعلی وجود ندارد.',
                    'warning',
                ],

                404 => [
                    'مورد درخواستی یافت نشد',
                    'ممکن است مورد موردنظر وجود نداشته باشد یا دسترسی به آن برای شما فراهم نباشد.',
                    'information',
                ],

                419 => [
                    'اعتبار نشست پایان یافته است',
                    'برای ادامه، صفحه را دوباره باز کنید و در صورت نیاز مجدداً وارد سامانه شوید.',
                    'warning',
                ],

                423 => [
                    'این عملیات فعلاً در دسترس نیست',
                    'وضعیت فعلی منبع اجازه انجام این عملیات را نمی‌دهد.',
                    'warning',
                ],

                429 => [
                    'تعداد درخواست‌ها بیش از حد مجاز است',
                    'کمی بعد دوباره تلاش کنید.',
                    'warning',
                ],

                503 => [
                    'سرویس موقتاً در دسترس نیست',
                    'سامانه در حال حاضر قادر به ارائه این خدمت نیست. کمی بعد دوباره تلاش کنید.',
                    'warning',
                ],

                default => [
                    'خطایی در پردازش درخواست رخ داد',
                    'درخواست شما در حال حاضر قابل پردازش نیست.',
                    'danger',
                ],
            };


        return [
            'available' =>
                true,

            'visible' =>
                true,

            'title' =>
                $title,

            'body' =>
                $body,

            'icon_code' =>
                'circle-alert',

            'severity_code' =>
                $severity,

            'layout_variant' =>
                'system-message',

            /*
             * Semantic action code only.
             * No arbitrary DB-controlled URL.
             */
            'primary_action_code' =>
                'back',

            'primary_action_label' =>
                'بازگشت',

            'secondary_action_code' =>
                null,

            'secondary_action_label' =>
                null,

            'metadata' =>
                [],

            'source' =>
                'builtin_emergency',
        ];
    }
}
