<?php

namespace IPKF\Database\Seeds;

class TicketingDomainFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStatuses();
        $this->seedPriorities();
        $this->seedGeneralCategory();
    }

    private function seedStatuses(): void
    {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_statuses
                (
                    code,
                    title,
                    category,
                    color,
                    sort_order,
                    is_closed,
                    is_system,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?,
                    1, 1,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
                ON DUPLICATE KEY UPDATE
                    title =
                        VALUES(title),

                    category =
                        VALUES(category),

                    color =
                        VALUES(color),

                    sort_order =
                        VALUES(sort_order),

                    is_closed =
                        VALUES(is_closed),

                    is_system = 1,

                    is_active = 1,

                    updated_at =
                        UTC_TIMESTAMP()
            ");

        foreach (
            $this->statuses()
            as $status
        ) {
            $statement->execute(
                $status
            );
        }
    }

    private function seedPriorities(): void
    {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_priorities
                (
                    code,
                    title,
                    severity,
                    color,
                    sort_order,
                    is_system,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?, ?, ?, ?, ?,
                    1, 1,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
                ON DUPLICATE KEY UPDATE
                    title =
                        VALUES(title),

                    severity =
                        VALUES(severity),

                    color =
                        VALUES(color),

                    sort_order =
                        VALUES(sort_order),

                    is_system = 1,

                    is_active = 1,

                    updated_at =
                        UTC_TIMESTAMP()
            ");

        foreach (
            $this->priorities()
            as $priority
        ) {
            $statement->execute(
                $priority
            );
        }
    }

    private function seedGeneralCategory(): void
    {
        $statement =
            $this->db->prepare("
                INSERT INTO ticketing_categories
                (
                    public_reference,
                    parent_id,
                    code,
                    title,
                    description,
                    sort_order,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    'TKT-CAT-GENERAL',
                    NULL,
                    'general',
                    ?,
                    ?,
                    10,
                    1,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
                ON DUPLICATE KEY UPDATE
                    title =
                        VALUES(title),

                    description =
                        VALUES(description),

                    sort_order =
                        VALUES(sort_order),

                    is_active = 1,

                    updated_at =
                        UTC_TIMESTAMP()
            ");

        $statement->execute([
            'عمومی',
            'دسته پیش‌فرض درخواست‌های پشتیبانی',
        ]);
    }

    private function statuses(): array
    {
        return [
            [
                'new',
                'جدید',
                'open',
                '#2563eb',
                10,
                0,
            ],

            [
                'in_progress',
                'در حال بررسی',
                'open',
                '#16a34a',
                20,
                0,
            ],

            [
                'waiting_requester',
                'در انتظار پاسخ درخواست‌کننده',
                'waiting',
                '#d97706',
                30,
                0,
            ],

            [
                'waiting_internal',
                'در انتظار اقدام داخلی',
                'waiting',
                '#7c3aed',
                40,
                0,
            ],

            [
                'resolved',
                'حل‌شده',
                'closed',
                '#0f766e',
                50,
                1,
            ],

            [
                'closed',
                'بسته‌شده',
                'closed',
                '#475569',
                60,
                1,
            ],

            [
                'cancelled',
                'لغوشده',
                'closed',
                '#6b7280',
                70,
                1,
            ],
        ];
    }

    private function priorities(): array
    {
        return [
            [
                'low',
                'کم',
                10,
                '#64748b',
                10,
            ],

            [
                'normal',
                'عادی',
                20,
                '#2563eb',
                20,
            ],

            [
                'high',
                'زیاد',
                30,
                '#f97316',
                30,
            ],

            [
                'urgent',
                'فوری',
                40,
                '#dc2626',
                40,
            ],
        ];
    }
}
