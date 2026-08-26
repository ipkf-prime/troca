<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class ExposeTicketingModuleShellNavigation
    extends Migration
{
    private const PERMISSION =
        'ticketing.ticket.view';


    public function up(): void
    {
        if (
            !$this->tableExists(
                'admin_navigation_items'
            )
        ) {
            return;
        }

        $statement =
            $this->db->prepare("
                INSERT INTO admin_navigation_items
                (
                    parent_id,
                    shell_key,
                    item_key,
                    item_type,
                    placement_code,
                    hide_when_badge_empty,
                    title,
                    description,
                    route_path,
                    target_application,
                    icon_code,
                    color_code,
                    permission_mode,
                    permission_codes_json,
                    badge_source,
                    active_paths_json,
                    sort_order,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    NULL,
                    'ticketing',
                    :item_key,
                    'link',
                    'sidebar',
                    0,
                    :title,
                    :description,
                    :route_path,
                    'ticketing',
                    :icon_code,
                    'green',
                    'any',
                    :permissions,
                    NULL,
                    :active_paths,
                    :sort_order,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    parent_id = NULL,
                    shell_key =
                        VALUES(shell_key),
                    item_type =
                        VALUES(item_type),
                    placement_code =
                        VALUES(placement_code),
                    hide_when_badge_empty = 0,
                    title =
                        VALUES(title),
                    description =
                        VALUES(description),
                    route_path =
                        VALUES(route_path),
                    target_application =
                        VALUES(target_application),
                    icon_code =
                        VALUES(icon_code),
                    color_code =
                        VALUES(color_code),
                    permission_mode =
                        VALUES(permission_mode),
                    permission_codes_json =
                        VALUES(permission_codes_json),
                    badge_source = NULL,
                    active_paths_json =
                        VALUES(active_paths_json),
                    sort_order =
                        VALUES(sort_order),
                    is_active = 1,
                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        foreach (
            $this->items()
            as $item
        ) {
            $statement->execute([
                'item_key' =>
                    $item['key'],

                'title' =>
                    $item['title'],

                'description' =>
                    $item['description'],

                'route_path' =>
                    $item['route'],

                'icon_code' =>
                    $item['icon'],

                'permissions' =>
                    $this->json([
                        self::PERMISSION,
                    ]),

                'active_paths' =>
                    $this->json(
                        $item['active_paths']
                    ),

                'sort_order' =>
                    $item['sort_order'],
            ]);
        }
    }


    public function down(): void
    {
    }


    private function items(): array
    {
        return [
            [
                'key' =>
                    'ticketing-dashboard',

                'title' =>
                    'داشبورد تیکتینگ',

                'description' =>
                    'نمای کلی تیکت‌ها و درخواست‌های پشتیبانی',

                'route' =>
                    '/admin/ticketing',

                'icon' =>
                    'dashboard',

                'active_paths' => [
                    '/admin/ticketing',
                ],

                'sort_order' => 10,
            ],

            [
                'key' =>
                    'ticketing-my-tickets',

                'title' =>
                    'تیکت‌های من',

                'description' =>
                    'مشاهده و پیگیری تیکت‌های ثبت‌شده',

                'route' =>
                    '/admin/ticketing/tickets',

                'icon' =>
                    'file-lines',

                'active_paths' => [
                    '/admin/ticketing/tickets',
                ],

                'sort_order' => 20,
            ],

            [
                'key' =>
                    'ticketing-create',

                'title' =>
                    'تیکت جدید',

                'description' =>
                    'ثبت درخواست پشتیبانی جدید',

                'route' =>
                    '/admin/ticketing/tickets/create',

                'icon' =>
                    'circle-check',

                'active_paths' => [
                    '/admin/ticketing/tickets/create',
                ],

                'sort_order' => 30,
            ],
        ];
    }


    private function json(
        array $value
    ): string {
        return json_encode(
            array_values($value),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }


    private function tableExists(
        string $table
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
