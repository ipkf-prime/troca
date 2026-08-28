<?php

declare(strict_types=1);

namespace App\Support;

final class TicketingDisplay
{
    public static function ticketNumberFromRow(
        array $ticket
    ): string {
        $canonical =
            trim(
                (string) (
                    $ticket['ticket_number']
                    ?? ''
                )
            );

        $projectTitle = '';

        foreach ([
            'support_project_title_snapshot',
            'project_title',
            'support_project_title',
        ] as $field) {
            $candidate =
                trim(
                    (string) (
                        $ticket[$field]
                        ?? ''
                    )
                );

            if ($candidate !== '') {
                $projectTitle = $candidate;
                break;
            }
        }

        return self::ticketNumber(
            $canonical,
            $projectTitle
        );
    }


    public static function ticketNumber(
        string $canonical,
        string $projectTitle
    ): string {
        $canonical =
            self::latinDigits(
                trim($canonical)
            );

        if (
            preg_match(
                '/(\d+)\s*$/',
                $canonical,
                $match
            ) !== 1
        ) {
            return '—';
        }

        return
            self::projectPrefix(
                $projectTitle
            )
            . '-'
            . AdminFormat::digits(
                $match[1]
            );
    }


    public static function statusTitle(
        string $code
    ): string {
        $map = [
            'new' =>
                'جدید',

            'in_progress' =>
                'در حال بررسی',

            'waiting_requester' =>
                'در انتظار پاسخ درخواست‌کننده',

            'waiting_internal' =>
                'در انتظار اقدام داخلی',

            'resolved' =>
                'حل‌شده',

            'closed' =>
                'بسته‌شده',

            'cancelled' =>
                'لغوشده',
        ];

        $code =
            strtolower(
                trim($code)
            );

        return
            $map[$code]
            ?? '—';
    }


    public static function eventTitle(
        string $code
    ): string {
        $map = [
            'ticket_created' =>
                'تیکت ثبت شد',

            'ticket_routed' =>
                'تیکت مسیریابی شد',

            'ticket_assigned' =>
                'تیکت به کارشناس تخصیص یافت',

            'ticket_reassigned' =>
                'کارشناس تیکت تغییر کرد',

            'ticket_taken_over' =>
                'تیکت تحویل گرفته شد',

            'ticket_transferred' =>
                'تیکت منتقل شد',

            'ticket_escalated' =>
                'تیکت به سطح بالاتر ارجاع شد',

            'ticket_status_changed' =>
                'وضعیت تیکت تغییر کرد',

            'message_added' =>
                'پاسخ ثبت شد',

            'attachment_added' =>
                'پیوست اضافه شد',

            'ticket_resolved' =>
                'تیکت حل شد',

            'ticket_closed' =>
                'تیکت بسته شد',

            'ticket_reopened' =>
                'تیکت بازگشایی شد',
        ];

        $code =
            strtolower(
                trim($code)
            );

        return
            $map[$code]
            ?? 'رویداد سیستمی';
    }


    public static function assignmentModeTitle(
        string $code
    ): string {
        $map = [
            'inherit' =>
                'مطابق مسیر',

            'manual' =>
                'دستی',

            'least_loaded' =>
                'کم‌بارترین کارشناس',

            'round_robin' =>
                'چرخشی',

            'fixed' =>
                'کارشناس ثابت',
        ];

        $code =
            strtolower(
                trim($code)
            );

        return
            $map[$code]
            ?? 'تعریف‌نشده';
    }


    public static function staffRoleTitle(
        string $code
    ): string {
        $map = [
            'agent' =>
                'کارشناس',

            'supervisor' =>
                'سرپرست',

            'manager' =>
                'مدیر',

            'lead' =>
                'سرپرست',

            'observer' =>
                'ناظر',
        ];

        $code =
            strtolower(
                trim($code)
            );

        return
            $map[$code]
            ?? 'تعریف‌نشده';
    }


    public static function latinDigits(
        string $value
    ): string {
        return strtr(
            $value,
            [
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
            ]
        );
    }


    private static function projectPrefix(
        string $title
    ): string {
        $title = trim($title);

        if (
            $title !== ''
            && preg_match(
                '/[\(（]\s*([^()（）]{1,24})\s*[\)）]\s*$/u',
                $title,
                $match
            ) === 1
        ) {
            $candidate =
                trim(
                    (string) $match[1]
                );

            if (
                $candidate !== ''
                && preg_match(
                    '/[\x{0600}-\x{06FF}]/u',
                    $candidate
                ) === 1
            ) {
                return $candidate;
            }
        }

        return 'تیکت';
    }
}
