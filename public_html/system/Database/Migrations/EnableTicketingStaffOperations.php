<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

final class EnableTicketingStaffOperations
    extends Migration
{
    private const PERMISSIONS = [
        [
            'ticketing.staff.cartable.view',
            'staff_cartable',
            'view',
            'مشاهده کارتابل پشتیبانی',
            'مشاهده صف‌ها و تیکت‌های قابل رسیدگی کارشناسان پشتیبانی.',
            'page',
            210,
            0,
        ],

        [
            'ticketing.ticket.takeover',
            'ticket',
            'takeover',
            'تحویل گرفتن تیکت',
            'تحویل گرفتن مسئولیت تیکت از صف یا سطح مجاز پشتیبانی.',
            'operation',
            220,
            0,
        ],

        [
            'ticketing.ticket.transfer',
            'ticket',
            'transfer',
            'انتقال تیکت',
            'انتقال تیکت به کارشناس دیگر در تیم جاری.',
            'operation',
            230,
            0,
        ],

        [
            'ticketing.ticket.escalate',
            'ticket',
            'escalate',
            'ارجاع تیکت به سطح بالاتر',
            'ارجاع تیکت در مسیر سلسله‌مراتبی پشتیبانی به سطح بالاتر.',
            'operation',
            240,
            0,
        ],
    ];


    public function up(): void
    {
        $this->ensurePermissions();

        $this->assignSuperAdminPermissions();

        $this->grantFrom(
            'ticketing.project.manage',
            array_column(
                self::PERMISSIONS,
                0
            )
        );

        $this->ensureRoutes();

        $this->ensureNavigation();
    }


    public function down(): void
    {
    }


    private function ensurePermissions(): void
    {
        if (
            !$this->tableExists(
                'permissions'
            )
        ) {
            return;
        }


        $statement =
            $this->db->prepare("
                INSERT INTO permissions
                (
                    code,
                    module,
                    resource,
                    action,

                    title,
                    description,

                    display_group,
                    display_type,
                    sort_order,
                    is_sensitive,

                    is_active,

                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    'ticketing',
                    ?,
                    ?,

                    ?,
                    ?,

                    'تیکتینگ',
                    ?,
                    ?,
                    ?,

                    1,

                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )

                ON DUPLICATE KEY UPDATE
                    module =
                        VALUES(module),

                    resource =
                        VALUES(resource),

                    action =
                        VALUES(action),

                    title =
                        VALUES(title),

                    description =
                        VALUES(description),

                    display_group =
                        VALUES(display_group),

                    display_type =
                        VALUES(display_type),

                    sort_order =
                        VALUES(sort_order),

                    is_sensitive =
                        VALUES(is_sensitive),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");


        foreach (
            self::PERMISSIONS
            as $permission
        ) {
            $statement->execute([
                $permission[0],
                $permission[1],
                $permission[2],

                $permission[3],
                $permission[4],

                $permission[5],
                $permission[6],
                $permission[7],
            ]);
        }
    }


    private function assignSuperAdminPermissions(): void
    {
        if (
            !$this->tableExists(
                'roles'
            )
            ||
            !$this->tableExists(
                'permissions'
            )
            ||
            !$this->tableExists(
                'role_permissions'
            )
        ) {
            return;
        }


        $codes =
            array_column(
                self::PERMISSIONS,
                0
            );

        $marks =
            implode(
                ',',
                array_fill(
                    0,
                    count($codes),
                    '?'
                )
            );


        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO
                    role_permissions
                (
                    role_id,
                    permission_id,
                    created_at
                )

                SELECT
                    role.id,
                    permission.id,
                    CURRENT_TIMESTAMP

                FROM roles role

                CROSS JOIN
                    permissions permission

                WHERE role.code =
                        'super_admin'

                  AND role.is_active = 1

                  AND permission.code
                        IN ({$marks})

                  AND permission.is_active = 1
            ");

        $statement->execute(
            $codes
        );
    }


    private function grantFrom(
        string $source,
        array $targets
    ): void {
        if (
            !$this->tableExists(
                'role_permissions'
            )
            ||
            !$this->tableExists(
                'permissions'
            )
            ||
            $targets === []
        ) {
            return;
        }


        $marks =
            implode(
                ',',
                array_fill(
                    0,
                    count($targets),
                    '?'
                )
            );


        $statement =
            $this->db->prepare("
                INSERT IGNORE INTO
                    role_permissions
                (
                    role_id,
                    permission_id,
                    created_at
                )

                SELECT DISTINCT
                    current.role_id,
                    target.id,
                    CURRENT_TIMESTAMP

                FROM role_permissions current

                INNER JOIN
                    permissions source_permission
                    ON source_permission.id =
                        current.permission_id

                CROSS JOIN
                    permissions target

                WHERE
                    source_permission.code = ?

                    AND target.code
                        IN ({$marks})

                    AND target.is_active = 1
            ");

        $statement->execute([
            $source,
            ...$targets,
        ]);
    }


    private function ensureRoutes(): void
    {
        if (
            !$this->tableExists(
                'admin_route_permissions'
            )
        ) {
            return;
        }


        $routes = [
            [
                '/admin/ticketing/staff',
                'GET',
                [
                    'ticketing.staff.cartable.view',
                ],
                100,
            ],

            [
                '/admin/ticketing/staff/{public_reference}/takeover',
                'POST',
                [
                    'ticketing.ticket.takeover',
                ],
                110,
            ],

            [
                '/admin/ticketing/staff/{public_reference}/transfer',
                'POST',
                [
                    'ticketing.ticket.transfer',
                ],
                110,
            ],

            [
                '/admin/ticketing/staff/{public_reference}/escalate',
                'POST',
                [
                    'ticketing.ticket.escalate',
                ],
                110,
            ],
        ];


        $statement =
            $this->db->prepare("
                INSERT INTO admin_route_permissions
                (
                    route_pattern,
                    http_method,

                    permission_mode,
                    permission_codes_json,

                    priority,
                    is_active,

                    created_at,
                    updated_at
                )
                VALUES
                (
                    ?,
                    ?,

                    'any',
                    ?,

                    ?,
                    1,

                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )

                ON DUPLICATE KEY UPDATE
                    permission_mode =
                        'any',

                    permission_codes_json =
                        VALUES(
                            permission_codes_json
                        ),

                    priority =
                        VALUES(priority),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");


        foreach ($routes as $route) {

            $statement->execute([
                $route[0],
                $route[1],

                $this->permissionsJson(
                    $route[2]
                ),

                $route[3],
            ]);
        }
    }


    private function ensureNavigation(): void
    {
        if (
            !$this->tableExists(
                'admin_navigation_items'
            )
        ) {
            return;
        }


        $permissions =
            $this->permissionsJson([
                'ticketing.staff.cartable.view',
            ]);


        $activePaths =
            json_encode(
                [
                    '/admin/ticketing/staff',
                    '/admin/ticketing/staff/*',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );


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
                    'ticketing-staff',

                    'link',
                    'sidebar',
                    0,

                    'کارتابل پشتیبانی',
                    'صف‌ها و عملیات کارشناسان پشتیبانی',

                    '/admin/ticketing/staff',
                    'ticketing',

                    'inbox',
                    NULL,

                    'any',
                    ?,

                    NULL,
                    ?,

                    25,
                    1,

                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )

                ON DUPLICATE KEY UPDATE
                    parent_id = NULL,

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
                        VALUES(
                            target_application
                        ),

                    icon_code =
                        VALUES(icon_code),

                    color_code =
                        VALUES(color_code),

                    permission_mode =
                        VALUES(permission_mode),

                    permission_codes_json =
                        VALUES(
                            permission_codes_json
                        ),

                    badge_source = NULL,

                    active_paths_json =
                        VALUES(
                            active_paths_json
                        ),

                    sort_order =
                        VALUES(sort_order),

                    is_active = 1,

                    updated_at =
                        CURRENT_TIMESTAMP
            ");


        $statement->execute([
            $permissions,
            $activePaths,
        ]);
    }


    private function permissionsJson(
        array $permissions
    ): string {
        return json_encode(
            array_values(
                $permissions
            ),
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

                WHERE table_schema =
                        DATABASE()

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
