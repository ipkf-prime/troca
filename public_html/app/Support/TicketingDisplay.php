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

            'ticket_requester_replied' =>
                'پاسخ درخواست‌کننده ثبت شد',
            'ticket_staff_replied' =>
                'پاسخ کارشناس ثبت شد',
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

            'ticket_requester_updated' =>
                'توضیح درخواست‌کننده ثبت شد',

            'ticket_requester_resolved' =>
                'درخواست‌کننده تیکت را حل‌شده اعلام کرد',

            'ticket_priority_changed' =>
                'اولویت تیکت تغییر کرد',
        ];

        $code =
            strtolower(
                trim($code)
            );

        return
            $map[$code]
            ?? 'رویداد سیستمی';
    }



    /*
     * TICKETING_OPERATIONAL_DETAIL_HISTORY_T3C1
     */
    public static function slaEventTitle(
        string $code
    ): string {
        $map = [
            'sla_initialized' =>
                'SLA فعال شد',

            'sla_response_met' =>
                'زمان پاسخ رعایت شد',

            'sla_response_breached' =>
                'زمان پاسخ نقض شد',

            'sla_resolution_met' =>
                'زمان حل رعایت شد',

            'sla_resolution_breached' =>
                'زمان حل نقض شد',

            'sla_paused' =>
                'SLA متوقف شد',

            'sla_resumed' =>
                'SLA ادامه یافت',

            'sla_auto_escalated' =>
                'ارجاع خودکار SLA انجام شد',

            'sla_auto_escalation_blocked' =>
                'ارجاع خودکار SLA متوقف شد',

            'sla_auto_escalation_limit_reached' =>
                'حداکثر دفعات ارجاع خودکار SLA تکمیل شد',
        ];

        $code =
            strtolower(
                trim($code)
            );

        return
            $map[$code]
            ?? 'رویداد SLA';
    }


    public static function eventSummary(
        array $event
    ): string {
        $code =
            strtolower(
                trim(
                    (string) (
                        $event['event_code']
                        ?? ''
                    )
                )
            );

        $payload =
            self::payload(
                $event['payload_json']
                ?? null
            );


        if ($code === 'ticket_priority_changed') {
            $old =
                trim(
                    (string) (
                        $payload[
                            'old_priority_title'
                        ]
                        ?? $payload[
                            'old_priority_code'
                        ]
                        ?? ''
                    )
                );

            $new =
                trim(
                    (string) (
                        $payload[
                            'new_priority_title'
                        ]
                        ?? $payload[
                            'new_priority_code'
                        ]
                        ?? ''
                    )
                );

            $reason =
                trim(
                    (string) (
                        $payload['reason']
                        ?? ''
                    )
                );

            $parts = [];

            if (
                $old !== ''
                || $new !== ''
            ) {
                $parts[] =
                    ($old !== '' ? $old : '—')
                    . ' ← '
                    . ($new !== '' ? $new : '—');
            }

            if ($reason !== '') {
                $parts[] =
                    'دلیل: '
                    . $reason;
            }

            return
                implode(
                    ' · ',
                    $parts
                );
        }


        if ($code === 'ticket_escalated') {
            $from =
                is_array(
                    $payload['from']
                    ?? null
                )
                    ? $payload['from']
                    : [];

            $to =
                is_array(
                    $payload['to']
                    ?? null
                )
                    ? $payload['to']
                    : [];

            $fromLayer =
                (int) (
                    $from['layer_id']
                    ?? 0
                );

            $toLayer =
                (int) (
                    $to['layer_id']
                    ?? 0
                );

            if (
                $fromLayer > 0
                && $toLayer > 0
            ) {
                return
                    'ارجاع از سطح '
                    . AdminFormat::digits(
                        (string) $fromLayer
                    )
                    . ' به سطح '
                    . AdminFormat::digits(
                        (string) $toLayer
                    );
            }
        }


        if ($code === 'ticket_transferred') {
            $previous =
                (int) (
                    $payload[
                        'previous_assignee_project_member_id'
                    ]
                    ?? 0
                );

            $next =
                (int) (
                    $payload[
                        'assignee_project_member_id'
                    ]
                    ?? 0
                );

            if (
                $previous > 0
                || $next > 0
            ) {
                return
                    'تغییر کارشناس از عضویت '
                    . (
                        $previous > 0
                            ? AdminFormat::digits(
                                (string) $previous
                            )
                            : '—'
                    )
                    . ' به '
                    . (
                        $next > 0
                            ? AdminFormat::digits(
                                (string) $next
                            )
                            : '—'
                    );
            }
        }


        if ($code === 'ticket_taken_over') {
            $previous =
                (int) (
                    $payload[
                        'previous_assignee_project_member_id'
                    ]
                    ?? 0
                );

            $next =
                (int) (
                    $payload[
                        'assignee_project_member_id'
                    ]
                    ?? 0
                );

            if ($next > 0) {
                return
                    'مالکیت عملیاتی به عضویت '
                    . AdminFormat::digits(
                        (string) $next
                    )
                    . (
                        $previous > 0
                            ? ' منتقل شد؛ مالک قبلی: '
                                . AdminFormat::digits(
                                    (string) $previous
                                )
                            : ''
                    );
            }
        }


        if ($code === 'ticket_assigned') {
            $memberId =
                (int) (
                    $payload[
                        'project_member_id'
                    ]
                    ?? 0
                );

            $mode =
                trim(
                    (string) (
                        $payload[
                            'assignment_mode_code'
                        ]
                        ?? ''
                    )
                );

            $parts = [];

            if ($memberId > 0) {
                $parts[] =
                    'عضویت '
                    . AdminFormat::digits(
                        (string) $memberId
                    );
            }

            if ($mode !== '') {
                $parts[] =
                    self::assignmentModeTitle(
                        $mode
                    );
            }

            return
                implode(
                    ' · ',
                    $parts
                );
        }


        if ($code === 'ticket_closed') {
            $automation =
                trim(
                    (string) (
                        $payload[
                            'automation_key'
                        ]
                        ?? ''
                    )
                );

            if (
                $automation
                === 'ticketing.ticket.auto_close'
            ) {
                $delay =
                    (int) (
                        $payload[
                            'delay_hours'
                        ]
                        ?? 0
                    );

                return
                    'بستن خودکار سامانه'
                    . (
                        $delay > 0
                            ? ' پس از '
                                . AdminFormat::digits(
                                    (string) $delay
                                )
                                . ' ساعت'
                            : ''
                    );
            }
        }


        if (
            $code
            === 'ticket_requester_updated'
        ) {
            return
                'درخواست‌کننده توضیح تکمیلی ثبت کرد.';
        }


        if (
            $code
            === 'ticket_requester_resolved'
        ) {
            return
                'درخواست‌کننده اعلام کرد مشکل برطرف شده است.';
        }


        return '';
    }


    public static function slaEventSummary(
        array $event
    ): string {
        $code =
            strtolower(
                trim(
                    (string) (
                        $event['event_code']
                        ?? ''
                    )
                )
            );

        $payload =
            self::payload(
                $event['payload_json']
                ?? null
            );


        if ($code === 'sla_initialized') {
            $response =
                trim(
                    (string) (
                        $payload[
                            'response_due_at'
                        ]
                        ?? ''
                    )
                );

            $resolution =
                trim(
                    (string) (
                        $payload[
                            'resolution_due_at'
                        ]
                        ?? ''
                    )
                );

            $parts = [];

            if ($response !== '') {
                $parts[] =
                    'مهلت پاسخ: '
                    . (
                        AdminFormat::jalaliDateTime(
                            $response
                        )
                        ?: $response
                    );
            }

            if ($resolution !== '') {
                $parts[] =
                    'مهلت حل: '
                    . (
                        AdminFormat::jalaliDateTime(
                            $resolution
                        )
                        ?: $resolution
                    );
            }

            return
                implode(
                    ' · ',
                    $parts
                );
        }


        if (
            $code
            === 'sla_auto_escalated'
        ) {
            $number =
                (int) (
                    $payload[
                        'auto_escalation_number'
                    ]
                    ?? 0
                );

            $targetNode =
                (int) (
                    $payload[
                        'target_node_id'
                    ]
                    ?? 0
                );

            $next =
                trim(
                    (string) (
                        $payload[
                            'next_action_at'
                        ]
                        ?? ''
                    )
                );

            $parts = [];

            if ($number > 0) {
                $parts[] =
                    'ارجاع شماره '
                    . AdminFormat::digits(
                        (string) $number
                    );
            }

            if ($targetNode > 0) {
                $parts[] =
                    'گره مقصد '
                    . AdminFormat::digits(
                        (string) $targetNode
                    );
            }

            if ($next !== '') {
                $parts[] =
                    'اقدام بعدی: '
                    . (
                        AdminFormat::jalaliDateTime(
                            $next
                        )
                        ?: $next
                    );
            }

            return
                implode(
                    ' · ',
                    $parts
                );
        }


        if (
            $code
            === 'sla_auto_escalation_blocked'
        ) {
            $reason =
                trim(
                    (string) (
                        $payload[
                            'reason_code'
                        ]
                        ?? ''
                    )
                );

            $reasonMap = [
                'no_escalation_path' =>
                    'مسیر ارجاع بالاتری تعریف نشده است',

                'no_escalation_route' =>
                    'مسیر عملیاتی معتبر برای ارجاع پیدا نشد',
            ];

            return
                $reasonMap[$reason]
                ?? (
                    $reason !== ''
                        ? 'دلیل توقف: '
                            . $reason
                        : 'ارجاع خودکار ادامه پیدا نکرد.'
                );
        }


        foreach (
            [
                'response_due_at' =>
                    'مهلت پاسخ',

                'resolution_due_at' =>
                    'مهلت حل',

                'first_response_at' =>
                    'اولین پاسخ',
            ]
            as $field => $title
        ) {
            $value =
                trim(
                    (string) (
                        $payload[$field]
                        ?? ''
                    )
                );

            if ($value !== '') {
                return
                    $title
                    . ': '
                    . (
                        AdminFormat::jalaliDateTime(
                            $value
                        )
                        ?: $value
                    );
            }
        }


        return '';
    }


    private static function payload(
        mixed $value
    ): array {
        if (is_array($value)) {
            return $value;
        }

        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($value === '') {
            return [];
        }

        $decoded =
            json_decode(
                $value,
                true
            );

        return
            is_array($decoded)
                ? $decoded
                : [];
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

            'escalation' =>
                'ارجاع سطح بالاتر',
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
