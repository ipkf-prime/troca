#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-/d/Documents/GitHub/troca}"
expected_branch="v0.6.1-notification-provider-management-dev"
expected_head="cb79e65"

cd "$repo_root"

branch="$(git branch --show-current)"
head="$(git rev-parse --short HEAD)"

if [[ "$branch" != "$expected_branch" ]]; then
  echo "Expected branch: $expected_branch; current: $branch" >&2
  exit 1
fi

if [[ "$head" != "$expected_head" ]]; then
  echo "Expected HEAD: $expected_head; current: $head" >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree or index is not clean." >&2
  git status --short --branch >&2
  exit 1
fi

migration="public_html/system/Database/Migrations/CreateAccessControlFoundation.php"
repo="public_html/app/Repositories/AccessControlRepository.php"
service="public_html/app/Services/AccessControlService.php"
policy="public_html/app/Services/NotificationSendAccessPolicyService.php"
permission_repo="public_html/app/Repositories/PermissionRepository.php"
authorization="public_html/app/Services/AuthorizationService.php"
send_service="public_html/app/Services/NotificationSendCenterService.php"
settings_service="public_html/app/Services/CommunicationSettingsService.php"
web_routes="public_html/routes/web.php"
access_view="public_html/resources/views/admin/access.php"
manager_view="public_html/resources/views/admin/access-control.php"
test_file="tests/AccessControlFoundationTest.php"
tool_file="tools/apply-access-control-foundation-v061.sh"

required=(
  "$permission_repo"
  "$authorization"
  "$send_service"
  "$settings_service"
  "$web_routes"
  "$access_view"
)

for file in "${required[@]}"; do
  [[ -f "$file" ]] || {
    echo "Missing required file: $file" >&2
    exit 1
  }
done

mapfile -t registries < <(
  grep -RIlF     "EnableNotificationSendExperienceAndBaleEnrollment()"     public_html     --include='*.php'     | grep -v '/system/Database/Migrations/'     || true
)

if [[ "${#registries[@]}" -lt 1 ]]; then
  echo "Migration registry was not found." >&2
  exit 1
fi

modified=(
  "$permission_repo"
  "$authorization"
  "$send_service"
  "$settings_service"
  "$web_routes"
  "$access_view"
  "${registries[@]}"
)

new_files=(
  "$migration"
  "$repo"
  "$service"
  "$policy"
  "$manager_view"
  "$test_file"
  "$tool_file"
)

rollback() {
  status=$?
  if [[ "$status" -ne 0 ]]; then
    echo
    echo "PATCH FAILED; RESTORING CLEAN TREE" >&2
    git restore --staged --worktree -- "${modified[@]}" >/dev/null 2>&1 || true
    rm -f -- "${new_files[@]}"
  fi
  exit "$status"
}
trap rollback EXIT

echo
echo "=== Add Access Control Schema ==="

cat > "$migration" <<'PHP'
<?php

namespace IPKF\Database\Migrations;

class CreateAccessControlFoundation extends Migration
{
    public function up(): void
    {
        $this->createOverrideTable();
        $this->createAuditTable();
        $this->extendPermissionCatalog();
        $this->seedPermissions();
        $this->seedDefaultGrants();
        $this->alignCommunicationRoutes();
        $this->alignSendNavigation();
    }

    public function down(): void
    {
    }

    private function createOverrideTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS user_permission_overrides (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                permission_id BIGINT UNSIGNED NOT NULL,
                role_assignment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                effect_code VARCHAR(10)
                    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                reason VARCHAR(500) NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY user_permission_overrides_unique
                    (user_id, permission_id, role_assignment_id),
                INDEX user_permission_overrides_user_index
                    (user_id, role_assignment_id),
                INDEX user_permission_overrides_permission_index
                    (permission_id, effect_code)
            )
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createAuditTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS access_control_change_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_user_id BIGINT UNSIGNED NOT NULL,
                target_type VARCHAR(30)
                    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                target_id BIGINT UNSIGNED NOT NULL,
                role_assignment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                change_type VARCHAR(60)
                    CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                old_value LONGTEXT NULL,
                new_value LONGTEXT NULL,
                reason VARCHAR(500) NULL,
                request_ip VARCHAR(64)
                    CHARACTER SET ascii COLLATE ascii_bin NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX access_control_change_logs_actor_index
                    (actor_user_id, id),
                INDEX access_control_change_logs_target_index
                    (target_type, target_id, id),
                INDEX access_control_change_logs_created_index
                    (created_at, id)
            )
            ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function extendPermissionCatalog(): void
    {
        $columns = [
            'parent_code' => "VARCHAR(150)
                CHARACTER SET ascii COLLATE ascii_bin NULL",
            'display_group' => "VARCHAR(150) NULL",
            'display_type' => "VARCHAR(30)
                CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'operation'",
            'sort_order' => "INT NOT NULL DEFAULT 0",
            'is_sensitive' => "TINYINT(1) NOT NULL DEFAULT 0",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists('permissions', $column)) {
                $this->db->exec(
                    "ALTER TABLE permissions
                     ADD COLUMN {$column} {$definition}"
                );
            }
        }
    }

    private function seedPermissions(): void
    {
        $items = [
            ['access.roles.manage', 'access', 'roles', 'manage',
                'مدیریت نقش‌ها و مجوزها',
                'مدیریت تمام مجوزهای منو، صفحه، تب و عملیات.',
                'نقش‌ها', 'operation', 10, 1],
            ['access.users.search', 'access', 'users', 'search',
                'جستجوی کاربران در مدیریت دسترسی',
                'جستجو با نام، نام کاربری، کد ملی، موبایل، نقش و سازمان.',
                'کاربران', 'search', 20, 1],
            ['access.users.manage', 'access', 'users', 'manage',
                'دسترسی اختصاصی کاربران',
                'ثبت اجازه یا ممانعت اختصاصی برای کاربر.',
                'کاربران', 'operation', 30, 1],
            ['access.audit.view', 'access', 'audit', 'view',
                'مشاهده تاریخچه دسترسی',
                'مشاهده تغییرات نقش و دسترسی کاربران.',
                'تاریخچه', 'page', 40, 1],
            ['notifications.send.view', 'communications',
                'notification_send', 'view',
                'مشاهده فرم ارسال اعلان',
                'نمایش منو و فرم ارسال اعلان.',
                'ارسال اعلان', 'page', 100, 0],
            ['notifications.recipients.search', 'communications',
                'notification_recipients', 'search',
                'جستجوی اشخاص و گیرندگان',
                'جستجو و انتخاب کاربران به عنوان گیرنده.',
                'ارسال اعلان', 'search', 110, 1],
            ['notifications.recipients.details', 'communications',
                'notification_recipients', 'view_details',
                'مشاهده مشخصات گیرندگان',
                'مشاهده نقش، سازمان، شهر و کانال گیرنده.',
                'ارسال اعلان', 'view', 120, 1],
            ['notifications.manual_targets.use', 'communications',
                'notification_manual_targets', 'use',
                'استفاده از مقصد دستی',
                'ورود مستقیم ایمیل، موبایل یا شناسه مقصد.',
                'ارسال اعلان', 'operation', 130, 1],
            ['notifications.send.request', 'communications',
                'notification_send', 'request',
                'ارسال اعلان با تأیید',
                'ثبت درخواست ارسال برای تأیید مدیر مجاز.',
                'ارسال اعلان', 'workflow', 140, 1],
            ['notifications.send.direct', 'communications',
                'notification_send', 'direct',
                'ارسال مستقیم اعلان',
                'ارسال واقعی اعلان بدون تأیید.',
                'ارسال اعلان', 'workflow', 150, 1],
            ['notifications.approvals.manage', 'communications',
                'notification_approval', 'manage',
                'تأیید یا رد ارسال اعلان',
                'بررسی درخواست ارسال اعلان دیگران.',
                'تأیید اعلان', 'workflow', 160, 1],
        ];

        $statement = $this->db->prepare("
            INSERT INTO permissions (
                code, module, resource, action, title, description,
                display_group, display_type, sort_order,
                is_sensitive, is_active, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                module = VALUES(module),
                resource = VALUES(resource),
                action = VALUES(action),
                title = VALUES(title),
                description = VALUES(description),
                display_group = VALUES(display_group),
                display_type = VALUES(display_type),
                sort_order = VALUES(sort_order),
                is_sensitive = VALUES(is_sensitive),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($items as $item) {
            $statement->execute($item);
        }
    }

    private function seedDefaultGrants(): void
    {
        $this->db->exec("
            INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
            SELECT roles.id, permissions.id, CURRENT_TIMESTAMP
            FROM roles CROSS JOIN permissions
            WHERE roles.code = 'super_admin'
              AND roles.is_active = 1
              AND permissions.is_active = 1
        ");

        $this->grantFrom(
            'access.manage',
            [
                'access.roles.manage',
                'access.users.search',
                'access.users.manage',
                'access.audit.view',
            ]
        );

        $this->grantFrom(
            'notifications.send.manage',
            [
                'notifications.send.view',
                'notifications.recipients.search',
                'notifications.recipients.details',
                'notifications.manual_targets.use',
                'notifications.send.request',
                'notifications.send.direct',
            ]
        );
    }

    private function grantFrom(string $source, array $targets): void
    {
        $marks = implode(', ', array_fill(0, count($targets), '?'));

        $statement = $this->db->prepare("
            INSERT IGNORE INTO role_permissions
                (role_id, permission_id, created_at)
            SELECT DISTINCT current.role_id, target.id,
                CURRENT_TIMESTAMP
            FROM role_permissions AS current
            INNER JOIN permissions AS source_permission
                ON source_permission.id = current.permission_id
            CROSS JOIN permissions AS target
            WHERE source_permission.code = ?
              AND target.code IN ({$marks})
              AND target.is_active = 1
        ");
        $statement->execute([$source, ...$targets]);
    }

    private function alignCommunicationRoutes(): void
    {
        if (!$this->tableExists('admin_route_permissions')) {
            return;
        }

        $this->upsertRoute(
            '/admin/communications/settings',
            'GET',
            [
                'notifications.providers.manage',
                'notifications.routing.manage',
                'notifications.preferences.self',
                'notifications.send.manage',
                'notifications.send.view',
                'notifications.reports.view',
                'messages.admin.manage',
            ],
            80
        );

        $this->upsertRoute(
            '/admin/communications/settings/send',
            'POST',
            [
                'notifications.send.manage',
                'notifications.send.direct',
                'notifications.send.request',
            ],
            90
        );
    }

    private function upsertRoute(
        string $path,
        string $method,
        array $permissions,
        int $priority
    ): void {
        $statement = $this->db->prepare("
            INSERT INTO admin_route_permissions (
                route_pattern, http_method, permission_mode,
                permission_codes_json, priority, is_active,
                created_at, updated_at
            )
            VALUES (?, ?, 'any', ?, ?, 1,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                permission_mode = 'any',
                permission_codes_json = VALUES(permission_codes_json),
                priority = VALUES(priority),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP
        ");
        $statement->execute([
            $path,
            $method,
            json_encode(
                $permissions,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            $priority,
        ]);
    }

    private function alignSendNavigation(): void
    {
        if (!$this->tableExists('admin_navigation_items')) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE admin_navigation_items
            SET permission_mode = 'any',
                permission_codes_json = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE route_path LIKE '%section=send%'
               OR title = 'ارسال اعلان'
        ");
        $statement->execute([
            json_encode(
                [
                    'notifications.send.view',
                    'notifications.send.manage',
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ? AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }
}
PHP

echo "ADDED: $migration"

for registry in "${registries[@]}"; do
  REGISTRY="$registry" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{REGISTRY};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!
";
my @lines = <$fh>;
close $fh;

my $anchor = 0;
my $already = 0;
my @out;

for my $line (@lines) {
    $already = 1 if index($line, 'CreateAccessControlFoundation()') >= 0;
    push @out, $line;

    if (index($line, 'EnableNotificationSendExperienceAndBaleEnrollment()') >= 0) {
        $anchor++;
        my ($indent) = $line =~ /^(\s*)/;
        push @out,
            $indent
            . 'new \IPKF\Database\Migrations\CreateAccessControlFoundation(),'
            . "
";
    }
}

die "Migration anchor count in $path: $anchor
"
    if !$already && $anchor != 1;

if (!$already) {
    open my $out, '>:encoding(UTF-8)', $path or die "$path: $!
";
    print {$out} @out;
    close $out;
}
PERL
  echo "UPDATED: $registry"
done

echo
echo "=== Add Access Control Repository ==="

cat > "$repo" <<'PHP'
<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

class AccessControlRepository extends BaseRepository
{
    public function page(string $query, int $userId): array
    {
        $permissions = $this->permissions();
        $roles = $this->roles();
        $selectedUser = $userId > 0
            ? $this->user($userId)
            : null;

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'groups' => $this->groupPermissions($permissions),
            'role_map' => $this->roleMap(),
            'users' => $this->users($query),
            'selected_user' => $selectedUser,
            'assignments' => $selectedUser !== null
                ? $this->assignments($userId)
                : [],
            'audit' => $this->audit(),
        ];
    }

    public function roles(): array
    {
        $order = Database::columnExists('roles', 'priority')
            ? 'priority ASC, id ASC'
            : 'id ASC';

        return $this->connection()->query("
            SELECT *
            FROM roles
            ORDER BY {$order}
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function permissions(): array
    {
        $group = Database::columnExists('permissions', 'display_group')
            ? "COALESCE(NULLIF(display_group, ''), resource)"
            : 'resource';
        $type = Database::columnExists('permissions', 'display_type')
            ? 'display_type'
            : "'operation'";
        $sort = Database::columnExists('permissions', 'sort_order')
            ? 'sort_order'
            : '0';
        $sensitive = Database::columnExists('permissions', 'is_sensitive')
            ? 'is_sensitive'
            : '0';

        return $this->connection()->query("
            SELECT id, code, module, resource, action,
                title, description,
                {$group} AS display_group,
                {$type} AS display_type,
                {$sort} AS sort_order,
                {$sensitive} AS is_sensitive
            FROM permissions
            WHERE is_active = 1
            ORDER BY module, sort_order, resource, action, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roleMap(): array
    {
        $rows = $this->connection()->query("
            SELECT role_permissions.role_id, permissions.code
            FROM role_permissions
            INNER JOIN permissions
                ON permissions.id = role_permissions.permission_id
            WHERE permissions.is_active = 1
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['role_id']][(string) $row['code']] = true;
        }

        return $map;
    }

    public function users(string $query): array
    {
        $query = trim($query);
        $where = ["users.status = 'active'"];
        $params = [];

        if (Database::columnExists('users', 'deleted_at')) {
            $where[] = 'users.deleted_at IS NULL';
        }

        if ($query !== '') {
            $where[] = "CONCAT_WS(
                ' ',
                COALESCE(persons.full_name, ''),
                COALESCE(persons.national_code, ''),
                COALESCE(persons.mobile, ''),
                COALESCE(users.username, ''),
                COALESCE(users.email, ''),
                COALESCE(users.mobile, ''),
                COALESCE((
                    SELECT GROUP_CONCAT(
                        DISTINCT roles.title
                        ORDER BY roles.priority, roles.id
                        SEPARATOR '، '
                    )
                    FROM user_role_assignments
                    INNER JOIN roles
                        ON roles.id = user_role_assignments.role_id
                    WHERE user_role_assignments.user_id = users.id
                      AND user_role_assignments.is_active = 1
                      AND roles.is_active = 1
                ), ''),
                COALESCE((
                    SELECT org_units.title
                    FROM user_org_assignments
                    INNER JOIN org_units
                        ON org_units.id = user_org_assignments.org_unit_id
                    WHERE user_org_assignments.user_id = users.id
                      AND user_org_assignments.status = 'active'
                    ORDER BY user_org_assignments.is_primary DESC,
                        user_org_assignments.id ASC
                    LIMIT 1
                ), '')
            ) LIKE ?";
            $params[] = '%' . $query . '%';
        }

        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    CONCAT('کاربر ', users.id)
                ) AS title,
                COALESCE(users.username, '') AS username,
                COALESCE(persons.national_code, '') AS national_code,
                COALESCE(
                    NULLIF(persons.mobile, ''),
                    NULLIF(users.mobile, ''),
                    ''
                ) AS mobile,
                COALESCE((
                    SELECT GROUP_CONCAT(
                        DISTINCT roles.title
                        ORDER BY roles.priority, roles.id
                        SEPARATOR '، '
                    )
                    FROM user_role_assignments
                    INNER JOIN roles
                        ON roles.id = user_role_assignments.role_id
                    WHERE user_role_assignments.user_id = users.id
                      AND user_role_assignments.is_active = 1
                      AND roles.is_active = 1
                ), '') AS role_titles,
                COALESCE((
                    SELECT org_units.title
                    FROM user_org_assignments
                    INNER JOIN org_units
                        ON org_units.id = user_org_assignments.org_unit_id
                    WHERE user_org_assignments.user_id = users.id
                      AND user_org_assignments.status = 'active'
                    ORDER BY user_org_assignments.is_primary DESC,
                        user_org_assignments.id ASC
                    LIMIT 1
                ), '') AS organization_title
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY title, users.id
            LIMIT 100
        ");
        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function user(int $userId): ?array
    {
        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    NULLIF(users.email, ''),
                    CONCAT('کاربر ', users.id)
                ) AS title,
                COALESCE(users.username, '') AS username,
                COALESCE(persons.national_code, '') AS national_code,
                COALESCE(
                    NULLIF(persons.mobile, ''),
                    NULLIF(users.mobile, ''),
                    ''
                ) AS mobile
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id = ?
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function assignments(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT
                user_role_assignments.id,
                roles.id AS role_id,
                roles.code AS role_code,
                roles.title AS role_title,
                user_role_assignments.scope_type
            FROM user_role_assignments
            INNER JOIN roles
                ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
            ORDER BY roles.priority, user_role_assignments.id
        ");
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function overrideMap(int $userId, int $assignmentId): array
    {
        if (!Database::tableExists('user_permission_overrides')) {
            return [];
        }

        $statement = $this->connection()->prepare("
            SELECT permissions.code,
                user_permission_overrides.effect_code
            FROM user_permission_overrides
            INNER JOIN permissions
                ON permissions.id =
                    user_permission_overrides.permission_id
            WHERE user_permission_overrides.user_id = ?
              AND user_permission_overrides.role_assignment_id = ?
              AND permissions.is_active = 1
        ");
        $statement->execute([$userId, $assignmentId]);

        $map = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $map[(string) $row['code']] =
                (string) $row['effect_code'];
        }

        return $map;
    }

    public function inheritedMap(int $userId, int $assignmentId): array
    {
        $filter = $assignmentId > 0
            ? ' AND user_role_assignments.id = ?'
            : '';
        $statement = $this->connection()->prepare("
            SELECT DISTINCT permissions.code
            FROM permissions
            INNER JOIN role_permissions
                ON role_permissions.permission_id = permissions.id
            INNER JOIN user_role_assignments
                ON user_role_assignments.role_id =
                    role_permissions.role_id
            INNER JOIN roles
                ON roles.id = user_role_assignments.role_id
            WHERE user_role_assignments.user_id = ?
              AND user_role_assignments.is_active = 1
              AND roles.is_active = 1
              AND permissions.is_active = 1
              {$filter}
        ");
        $params = [$userId];

        if ($assignmentId > 0) {
            $params[] = $assignmentId;
        }

        $statement->execute($params);

        return array_fill_keys(
            array_map(
                'strval',
                $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
            ),
            true
        );
    }

    public function notificationPolicy(int $userId, int $assignmentId): string
    {
        $effective = function (string $code) use ($userId, $assignmentId): bool {
            $overrides = $this->overrideMap($userId, $assignmentId);

            if (isset($overrides[$code])) {
                return $overrides[$code] === 'allow';
            }

            if ($assignmentId > 0) {
                $global = $this->overrideMap($userId, 0);

                if (isset($global[$code])) {
                    return $global[$code] === 'allow';
                }
            }

            return isset($this->inheritedMap($userId, $assignmentId)[$code]);
        };

        if (
            $effective('notifications.send.manage')
            || $effective('notifications.send.direct')
        ) {
            return 'direct';
        }

        if (
            $effective('notifications.send.view')
            && $effective('notifications.send.request')
        ) {
            return 'approval';
        }

        return 'none';
    }

    public function saveRolePermissions(
        int $roleId,
        array $codes,
        int $actorUserId,
        string $reason,
        string $ip
    ): void {
        $statement = $this->connection()->prepare("
            SELECT code, is_editable
            FROM roles
            WHERE id = ?
            LIMIT 1
        ");
        $statement->execute([$roleId]);
        $role = $statement->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($role)
            || ($role['code'] ?? '') === 'super_admin'
            || empty($role['is_editable'])
        ) {
            throw new RuntimeException('access_role_protected');
        }

        $allowed = array_map(
            'strval',
            $this->connection()->query("
                SELECT code FROM permissions WHERE is_active = 1
            ")->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
        $codes = array_values(array_unique(
            array_intersect($allowed, array_map('strval', $codes))
        ));
        $old = array_keys($this->roleMap()[$roleId] ?? []);
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $delete = $db->prepare("
                DELETE FROM role_permissions WHERE role_id = ?
            ");
            $delete->execute([$roleId]);

            $insert = $db->prepare("
                INSERT IGNORE INTO role_permissions
                    (role_id, permission_id, created_at)
                SELECT ?, id, CURRENT_TIMESTAMP
                FROM permissions
                WHERE code = ? AND is_active = 1
            ");

            foreach ($codes as $code) {
                $insert->execute([$roleId, $code]);
            }

            $this->log(
                $actorUserId,
                'role',
                $roleId,
                0,
                'role_permissions_replaced',
                $old,
                $codes,
                $reason,
                $ip
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function saveUserPolicy(
        int $userId,
        int $assignmentId,
        string $policy,
        bool $canSearch,
        bool $canViewDetails,
        bool $canUseManual,
        int $actorUserId,
        string $reason,
        string $ip
    ): void {
        if ($this->user($userId) === null) {
            throw new RuntimeException('access_user_not_found');
        }

        if ($assignmentId > 0) {
            $check = $this->connection()->prepare("
                SELECT COUNT(*)
                FROM user_role_assignments
                WHERE id = ? AND user_id = ? AND is_active = 1
            ");
            $check->execute([$assignmentId, $userId]);

            if ((int) $check->fetchColumn() < 1) {
                throw new RuntimeException('access_assignment_invalid');
            }
        }

        $effects = match ($policy) {
            'none' => [
                'notifications.send.view' => 'deny',
                'notifications.send.request' => 'deny',
                'notifications.send.direct' => 'deny',
                'notifications.send.manage' => 'deny',
            ],
            'approval' => [
                'notifications.send.view' => 'allow',
                'notifications.send.request' => 'allow',
                'notifications.send.direct' => 'deny',
                'notifications.send.manage' => 'deny',
            ],
            'direct' => [
                'notifications.send.view' => 'allow',
                'notifications.send.request' => 'allow',
                'notifications.send.direct' => 'allow',
            ],
            default => [],
        };

        if ($policy !== 'inherit') {
            $effects['notifications.recipients.search'] =
                $canSearch ? 'allow' : 'deny';
            $effects['notifications.recipients.details'] =
                $canViewDetails ? 'allow' : 'deny';
            $effects['notifications.manual_targets.use'] =
                $canUseManual ? 'allow' : 'deny';
        }

        $old = $this->overrideMap($userId, $assignmentId);
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $delete = $db->prepare("
                DELETE FROM user_permission_overrides
                WHERE user_id = ? AND role_assignment_id = ?
            ");
            $delete->execute([$userId, $assignmentId]);

            $insert = $db->prepare("
                INSERT INTO user_permission_overrides (
                    user_id, permission_id, role_assignment_id,
                    effect_code, reason, created_by_user_id,
                    updated_by_user_id, created_at, updated_at
                )
                SELECT ?, id, ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM permissions
                WHERE code = ? AND is_active = 1
            ");

            foreach ($effects as $code => $effect) {
                $insert->execute([
                    $userId,
                    $assignmentId,
                    $effect,
                    $reason,
                    $actorUserId,
                    $actorUserId,
                    $code,
                ]);
            }

            $this->log(
                $actorUserId,
                'user',
                $userId,
                $assignmentId,
                'notification_access_policy_replaced',
                $old,
                $effects,
                $reason,
                $ip
            );
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function audit(): array
    {
        if (!Database::tableExists('access_control_change_logs')) {
            return [];
        }

        return $this->connection()->query("
            SELECT access_control_change_logs.*,
                COALESCE(
                    NULLIF(persons.full_name, ''),
                    NULLIF(users.username, ''),
                    CONCAT('کاربر ',
                        access_control_change_logs.actor_user_id)
                ) AS actor_title
            FROM access_control_change_logs
            LEFT JOIN users
                ON users.id = access_control_change_logs.actor_user_id
            LEFT JOIN persons ON persons.id = users.person_id
            ORDER BY access_control_change_logs.id DESC
            LIMIT 100
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function groupPermissions(array $permissions): array
    {
        $groups = [];

        foreach ($permissions as $permission) {
            $module = (string) ($permission['module'] ?? 'core');
            $group = (string) (
                $permission['display_group']
                ?? $permission['resource']
                ?? 'عمومی'
            );
            $groups[$module][$group][] = $permission;
        }

        return $groups;
    }

    private function log(
        int $actorUserId,
        string $targetType,
        int $targetId,
        int $assignmentId,
        string $changeType,
        mixed $old,
        mixed $new,
        string $reason,
        string $ip
    ): void {
        $statement = $this->connection()->prepare("
            INSERT INTO access_control_change_logs (
                actor_user_id, target_type, target_id,
                role_assignment_id, change_type,
                old_value, new_value, reason, request_ip,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                CURRENT_TIMESTAMP)
        ");
        $statement->execute([
            $actorUserId,
            $targetType,
            $targetId,
            $assignmentId,
            $changeType,
            json_encode(
                $old,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            json_encode(
                $new,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            ),
            $reason !== '' ? $reason : null,
            $ip !== '' ? $ip : null,
        ]);
    }
}
PHP

echo "ADDED: $repo"

echo
echo "=== Add Access Control Service ==="

cat > "$service" <<'PHP'
<?php

namespace App\Services;

use App\Repositories\AccessControlRepository;
use RuntimeException;

class AccessControlService extends BaseService
{
    public function __construct(
        private ?AccessControlRepository $repository = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??= new AccessControlRepository();
        $this->authorization ??= new AuthorizationService();
    }

    public function page(int $actorUserId, array $filters): array
    {
        $this->authorize(
            $actorUserId,
            ['access.manage', 'access.roles.manage',
                'access.users.search', 'access.audit.view']
        );

        $tab = strtolower(trim((string) ($filters['tab'] ?? 'roles')));

        if (!in_array($tab, ['roles', 'users', 'audit'], true)) {
            $tab = 'roles';
        }

        $query = trim((string) ($filters['q'] ?? ''));
        $userId = max(0, (int) ($filters['user_id'] ?? 0));
        $assignmentId = max(
            0,
            (int) ($filters['assignment_id'] ?? 0)
        );
        $data = $this->repository->page($query, $userId);
        $assignments = $data['assignments'] ?? [];

        if (
            $assignmentId > 0
            && !in_array(
                $assignmentId,
                array_map(
                    static fn (array $row): int => (int) $row['id'],
                    $assignments
                ),
                true
            )
        ) {
            $assignmentId = 0;
        }

        $data['tab'] = $tab;
        $data['query'] = $query;
        $data['selected_user_id'] = $userId;
        $data['assignment_id'] = $assignmentId;
        $data['overrides'] = $userId > 0
            ? $this->repository->overrideMap($userId, $assignmentId)
            : [];
        $data['inherited'] = $userId > 0
            ? $this->repository->inheritedMap($userId, $assignmentId)
            : [];
        $data['notification_policy'] = $userId > 0
            ? $this->repository->notificationPolicy(
                $userId,
                $assignmentId
            )
            : 'none';

        return $data;
    }

    public function saveRole(
        int $actorUserId,
        array $input,
        string $ip
    ): int {
        $this->authorize(
            $actorUserId,
            ['access.manage', 'access.roles.manage']
        );

        $roleId = max(0, (int) ($input['role_id'] ?? 0));
        $codes = is_array($input['permissions'] ?? null)
            ? $input['permissions']
            : [];

        $this->repository->saveRolePermissions(
            $roleId,
            $codes,
            $actorUserId,
            trim((string) ($input['reason'] ?? '')),
            $ip
        );

        return $roleId;
    }

    public function saveUser(
        int $actorUserId,
        array $input,
        string $ip
    ): array {
        $this->authorize(
            $actorUserId,
            ['access.manage', 'access.users.manage']
        );

        $userId = max(0, (int) ($input['user_id'] ?? 0));
        $assignmentId = max(
            0,
            (int) ($input['role_assignment_id'] ?? 0)
        );
        $reason = trim((string) ($input['reason'] ?? ''));

        if (mb_strlen($reason, 'UTF-8') < 3) {
            throw new RuntimeException('access_reason_required');
        }

        $this->repository->saveUserPolicy(
            $userId,
            $assignmentId,
            strtolower(trim((string) (
                $input['notification_policy'] ?? 'inherit'
            ))),
            !empty($input['can_search_recipients']),
            !empty($input['can_view_recipient_details']),
            !empty($input['can_use_manual_targets']),
            $actorUserId,
            $reason,
            $ip
        );

        return [
            'user_id' => $userId,
            'assignment_id' => $assignmentId,
        ];
    }

    private function authorize(int $userId, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if ($this->authorization->hasPermission($userId, $permission)) {
                return;
            }
        }

        throw new RuntimeException('access_management_forbidden');
    }
}
PHP

echo "ADDED: $service"

cat > "$policy" <<'PHP'
<?php

namespace App\Services;

class NotificationSendAccessPolicyService extends BaseService
{
    public function __construct(
        private ?AuthorizationService $authorization = null
    ) {
        $this->authorization ??= new AuthorizationService();
    }

    public function resolve(int $userId): string
    {
        if (
            $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.direct'
            )
        ) {
            return 'direct';
        }

        if (
            $this->authorization->hasPermission(
                $userId,
                'notifications.send.view'
            )
            && $this->authorization->hasPermission(
                $userId,
                'notifications.send.request'
            )
        ) {
            return 'approval_required';
        }

        return 'hidden';
    }

    public function canSearch(int $userId): bool
    {
        return $this->authorization->hasPermission(
            $userId,
            'notifications.recipients.search'
        )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            );
    }

    public function canViewDetails(int $userId): bool
    {
        return $this->authorization->hasPermission(
            $userId,
            'notifications.recipients.details'
        )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            );
    }

    public function canUseManual(int $userId): bool
    {
        return $this->authorization->hasPermission(
            $userId,
            'notifications.manual_targets.use'
        )
            || $this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            );
    }
}
PHP

echo "ADDED: $policy"

echo
echo "=== Extend Authorization Precedence ==="

PERMISSION_FILE="$permission_repo" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{PERMISSION_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;

my $anchor = "    public function communicationMatrix(): array\n";
my $count = () = $text =~ /\Q$anchor\E/g;
die "Permission anchor count: $count\n" if $count != 1;

my $method = <<'METHOD';
    public function userPermissionOverride(
        int $userId,
        string $permissionCode,
        ?int $assignmentId = null
    ): ?string {
        if (!\IPKF\Database\Database::tableExists(
            'user_permission_overrides'
        )) {
            return null;
        }

        $assignmentId = max(0, (int) ($assignmentId ?? 0));

        $statement = $this->connection()->prepare("
            SELECT user_permission_overrides.effect_code
            FROM user_permission_overrides
            INNER JOIN permissions
                ON permissions.id =
                    user_permission_overrides.permission_id
            WHERE user_permission_overrides.user_id = ?
              AND permissions.code = ?
              AND permissions.is_active = 1
              AND user_permission_overrides.role_assignment_id
                    IN (0, ?)
            ORDER BY user_permission_overrides.role_assignment_id DESC
            LIMIT 1
        ");
        $statement->execute([
            $userId,
            $permissionCode,
            $assignmentId,
        ]);

        $effect = $statement->fetchColumn();

        return in_array($effect, ['allow', 'deny'], true)
            ? (string) $effect
            : null;
    }

METHOD

my $pos = index($text, $anchor);
substr($text, $pos, 0, $method);

open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: user override lookup"

AUTH_FILE="$authorization" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{AUTH_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;

my $old = <<'OLD';
    public function hasPermission(int $userId, string $permissionCode): bool
    {
        $assignmentId = $this->activeAssignmentId();

        if ($assignmentId !== null) {
            $assignment = $this->roles->assignmentForUser($userId, $assignmentId);

            if (($assignment['role_code'] ?? '') === 'super_admin') {
                return true;
            }
        }

        return $this->permissions->userHasPermission($userId, $permissionCode, $assignmentId);
    }
OLD

my $new = <<'NEW';
    public function hasPermission(
        int $userId,
        string $permissionCode
    ): bool {
        $assignmentId = $this->activeAssignmentId();

        if ($assignmentId !== null) {
            $assignment = $this->roles->assignmentForUser(
                $userId,
                $assignmentId
            );

            if (($assignment['role_code'] ?? '') === 'super_admin') {
                return true;
            }
        }

        $override = $this->permissions->userPermissionOverride(
            $userId,
            $permissionCode,
            $assignmentId
        );

        if ($override !== null) {
            return $override === 'allow';
        }

        return $this->permissions->userHasPermission(
            $userId,
            $permissionCode,
            $assignmentId
        );
    }
NEW

my $count = () = $text =~ /\Q$old\E/g;
die "Authorization anchor count: $count\n" if $count != 1;

my $pos = index($text, $old);
substr($text, $pos, length($old), $new);

open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: user override precedence"

echo
echo "=== Wire Notification Access Policy ==="

SETTINGS_FILE="$settings_service" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{SETTINGS_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;

my $old = <<'OLD';
        'send' => [
            'title' => 'ارسال اعلان',
            'permission' => 'notifications.send.manage',
        ],
OLD

my $new = <<'NEW';
        'send' => [
            'title' => 'ارسال اعلان',
            'permission' => 'notifications.send.view',
        ],
NEW

my $count = () = $text =~ /\Q$old\E/g;
die "Send section anchor count: $count\n" if $count != 1;

my $pos = index($text, $old);
substr($text, $pos, length($old), $new);

open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: send section visibility permission"

SEND_FILE="$send_service" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{SEND_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;
    my $count = () = $$ref =~ /\Q$old\E/g;
    die "$label anchor count: $count\n" if $count != 1;
    my $pos = index($$ref, $old);
    substr($$ref, $pos, length($old), $new);
    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    <<'OLD',
        private ?NotificationGatewayService $gateway = null,
        private ?AuthorizationService $authorization = null,
        private ?NotificationMediaUploadService $media = null
    ) {
OLD
    <<'NEW',
        private ?NotificationGatewayService $gateway = null,
        private ?AuthorizationService $authorization = null,
        private ?NotificationMediaUploadService $media = null,
        private ?NotificationSendAccessPolicyService $accessPolicy = null
    ) {
NEW
    'policy dependency'
);

replace_once(
    \$text,
    <<'OLD',
        $this->media ??=
            new NotificationMediaUploadService();
    }
OLD
    <<'NEW',
        $this->media ??=
            new NotificationMediaUploadService();
        $this->accessPolicy ??=
            new NotificationSendAccessPolicyService(
                $this->authorization
            );
    }
NEW
    'policy initialization'
);

replace_once(
    \$text,
    <<'OLD',
    public function page(int $userId): array
    {
        $this->authorize($userId);

        $recipients =
            $this->repository->recipientOptions();
OLD
    <<'NEW',
    public function page(int $userId): array
    {
        $policy = $this->accessPolicy->resolve($userId);

        if ($policy === 'hidden') {
            throw new RuntimeException(
                'notification_send_forbidden'
            );
        }

        $canSearch = $this->accessPolicy->canSearch($userId);
        $canViewDetails =
            $this->accessPolicy->canViewDetails($userId);
        $canUseManual =
            $this->accessPolicy->canUseManual($userId);

        $recipients = $canSearch
            ? $this->repository->recipientOptions()
            : [];
NEW
    'page access policy'
);

replace_once(
    \$text,
    <<'OLD',
            'immediate_limit' =>
                self::IMMEDIATE_LIMIT,
            'result' =>
                $this->consumeResult($userId),
OLD
    <<'NEW',
            'immediate_limit' =>
                self::IMMEDIATE_LIMIT,
            'access_policy_code' => $policy,
            'can_search_recipients' => $canSearch,
            'can_view_recipient_details' =>
                $canViewDetails,
            'can_use_manual_targets' =>
                $canUseManual,
            'result' =>
                $this->consumeResult($userId),
NEW
    'page permission flags'
);

replace_once(
    \$text,
    <<'OLD',
    ): array {
        $this->authorize($actorUserId);

        $messageType = strtolower(trim(
OLD
    <<'NEW',
    ): array {
        $policy = $this->accessPolicy->resolve(
            $actorUserId
        );

        if ($policy === 'hidden') {
            throw new RuntimeException(
                'notification_send_forbidden'
            );
        }

        if ($policy === 'approval_required') {
            throw new RuntimeException(
                'notification_send_approval_required'
            );
        }

        $messageType = strtolower(trim(
NEW
    'direct send enforcement'
);

replace_once(
    \$text,
    <<'OLD',
        $targets = [];
        $skipped = [];

        foreach (
OLD
    <<'NEW',
        if (
            !$this->accessPolicy->canSearch(
                $actorUserId
            )
            && $userIds !== []
        ) {
            throw new RuntimeException(
                'notification_send_recipient_search_forbidden'
            );
        }

        if (
            !$this->accessPolicy->canUseManual(
                $actorUserId
            )
            && (
                trim((string) (
                    $input['manual_email'] ?? ''
                )) !== ''
                || trim((string) (
                    $input['manual_sms'] ?? ''
                )) !== ''
                || trim((string) (
                    $input['manual_messenger'] ?? ''
                )) !== ''
            )
        ) {
            throw new RuntimeException(
                'notification_send_manual_target_forbidden'
            );
        }

        $targets = [];
        $skipped = [];

        foreach (
NEW
    'recipient operation enforcement'
);

my $old_auth = <<'OLD';
    private function authorize(int $userId): void
    {
        if (
            $userId < 1
            || !$this->authorization->hasPermission(
                $userId,
                'notifications.send.manage'
            )
        ) {
            throw new RuntimeException(
                'notification_send_forbidden'
            );
        }
    }

OLD

my $count = () = $text =~ /\Q$old_auth\E/g;
die "Legacy authorize anchor count: $count\n" if $count != 1;

my $pos = index($text, $old_auth);
substr($text, $pos, length($old_auth), '');

open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: notification access policy"

echo
echo "=== Add Access Control Routes ==="

WEB_FILE="$web_routes" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{WEB_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;

my $anchor = "\$router->get('/admin/theme', function";
my $count = () = $text =~ /\Q$anchor\E/g;
die "Theme route anchor count: $count\n" if $count != 1;

my $routes = <<'ROUTES';
$router->get('/admin/access-control', function (
    $request,
    $response
) use ($adminRender, $adminGuard) {
    $context = $adminGuard($response, '/admin/access');

    if (!is_array($context)) {
        return $context;
    }

    try {
        $page = (new \App\Services\AccessControlService())->page(
            (int) $context['user_id'],
            [
                'tab' => $request->input('tab', 'roles'),
                'q' => $request->input('q', ''),
                'user_id' => $request->input('user_id', 0),
                'assignment_id' =>
                    $request->input('assignment_id', 0),
            ]
        );
    } catch (\Throwable) {
        return $response->redirect(
            '/admin/access?status=forbidden'
        );
    }

    return $adminRender(
        $response,
        'access-control',
        [
            'title' => 'سطوح و نقش‌های دسترسی',
            'context' => $context,
            'page' => $page,
            'status' => trim((string) $request->input(
                'status',
                ''
            )),
        ]
    );
});

$router->post('/admin/access-control/roles', function (
    $request,
    $response
) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/access');

    if (!is_array($context)) {
        return $context;
    }

    if (!(new \IPKF\Security\Csrf())->check(
        (string) $request->input('_token', '')
    )) {
        return $response->redirect(
            '/admin/access-control?tab=roles&status=invalid_csrf'
        );
    }

    $roleId = max(0, (int) $request->input('role_id', 0));

    try {
        (new \App\Services\AccessControlService())->saveRole(
            (int) $context['user_id'],
            [
                'role_id' => $roleId,
                'permissions' =>
                    $request->input('permissions', []),
                'reason' => $request->input('reason', ''),
            ],
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        return $response->redirect(
            '/admin/access-control?tab=roles'
            . '&status=role_permissions_saved'
        );
    } catch (\Throwable $exception) {
        return $response->redirect(
            '/admin/access-control?tab=roles&status='
            . rawurlencode($exception->getMessage())
        );
    }
});

$router->post('/admin/access-control/users', function (
    $request,
    $response
) use ($adminGuard) {
    $context = $adminGuard($response, '/admin/access');

    if (!is_array($context)) {
        return $context;
    }

    if (!(new \IPKF\Security\Csrf())->check(
        (string) $request->input('_token', '')
    )) {
        return $response->redirect(
            '/admin/access-control?tab=users&status=invalid_csrf'
        );
    }

    $userId = max(0, (int) $request->input('user_id', 0));
    $assignmentId = max(
        0,
        (int) $request->input('role_assignment_id', 0)
    );

    try {
        (new \App\Services\AccessControlService())->saveUser(
            (int) $context['user_id'],
            [
                'user_id' => $userId,
                'role_assignment_id' => $assignmentId,
                'notification_policy' =>
                    $request->input(
                        'notification_policy',
                        'inherit'
                    ),
                'can_search_recipients' =>
                    $request->input(
                        'can_search_recipients',
                        ''
                    ),
                'can_view_recipient_details' =>
                    $request->input(
                        'can_view_recipient_details',
                        ''
                    ),
                'can_use_manual_targets' =>
                    $request->input(
                        'can_use_manual_targets',
                        ''
                    ),
                'reason' => $request->input('reason', ''),
            ],
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        return $response->redirect(
            '/admin/access-control?tab=users&user_id='
            . $userId
            . '&assignment_id='
            . $assignmentId
            . '&status=user_policy_saved'
        );
    } catch (\Throwable $exception) {
        return $response->redirect(
            '/admin/access-control?tab=users&user_id='
            . $userId
            . '&assignment_id='
            . $assignmentId
            . '&status='
            . rawurlencode($exception->getMessage())
        );
    }
});

ROUTES

my $pos = index($text, $anchor);
substr($text, $pos, 0, $routes);

open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: access control routes"

echo
echo "=== Add Access Control Entry Point ==="

ACCESS_FILE="$access_view" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{ACCESS_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;

my $anchor =
    "<?php if ((\$canManageCommunicationAccess ?? false) === true): ?>\n";
my $count = () = $text =~ /\Q$anchor\E/g;
die "Access view anchor count: $count\n" if $count != 1;

my $block = <<'BLOCK';
<?php if (($canManageCommunicationAccess ?? false) === true): ?>
    <section class="admin-section" style="margin-top:1rem">
        <div class="admin-section__header">
            <div>
                <h2>مرکز کنترل نقش و دسترسی</h2>
                <p class="admin-muted">
                    مدیریت تمام مجوزهای منو، زیرمنو، تب و عملیات،
                    همراه با سیاست اختصاصی ارسال اعلان برای کاربران.
                </p>
            </div>
            <a class="admin-button" href="/admin/access-control">
                ورود به مرکز کنترل دسترسی
            </a>
        </div>
    </section>
<?php endif; ?>

BLOCK

my $pos = index($text, $anchor);
substr($text, $pos, 0, $block);

open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: access control entry point"

echo
echo "=== Add Access Control UI ==="

cat > "$manager_view" <<'PHP'
<?php

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

$page = is_array($page ?? null) ? $page : [];
$status = trim((string) ($status ?? ''));
$tab = (string) ($page['tab'] ?? 'roles');
$roles = is_array($page['roles'] ?? null)
    ? $page['roles']
    : [];
$groups = is_array($page['groups'] ?? null)
    ? $page['groups']
    : [];
$roleMap = is_array($page['role_map'] ?? null)
    ? $page['role_map']
    : [];
$users = is_array($page['users'] ?? null)
    ? $page['users']
    : [];
$selectedUser = is_array($page['selected_user'] ?? null)
    ? $page['selected_user']
    : null;
$assignments = is_array($page['assignments'] ?? null)
    ? $page['assignments']
    : [];
$assignmentId = (int) ($page['assignment_id'] ?? 0);
$overrides = is_array($page['overrides'] ?? null)
    ? $page['overrides']
    : [];
$inherited = is_array($page['inherited'] ?? null)
    ? $page['inherited']
    : [];
$audit = is_array($page['audit'] ?? null)
    ? $page['audit']
    : [];
$csrf = (new \IPKF\Security\Csrf())->token();

$moduleTitles = [
    'access' => 'سطوح و نقش‌های دسترسی',
    'admin' => 'مدیریت سامانه',
    'account' => 'حساب کاربری',
    'communications' => 'پیام‌ها و اعلان‌ها',
    'messages' => 'پیام‌رسان داخلی',
    'automation' => 'اتوماسیون اداری',
    'work' => 'مدیریت کار',
    'core' => 'هسته سامانه',
];

$notices = [
    'role_permissions_saved' =>
        ['ok', 'مجوزهای نقش ذخیره شد.'],
    'user_policy_saved' =>
        ['ok', 'سیاست دسترسی کاربر ذخیره شد.'],
    'invalid_csrf' =>
        ['error', 'نشست فرم معتبر نیست.'],
    'access_reason_required' =>
        ['error', 'ثبت دلیل تغییر الزامی است.'],
    'access_role_protected' =>
        ['error', 'نقش مدیر کل محافظت شده است.'],
];

ob_start();
?>

<?php if (isset($notices[$status])): ?>
    <?php [$type, $message] = $notices[$status]; ?>
    <div class="<?= $type === 'ok'
        ? 'admin-notice'
        : 'admin-alert' ?>">
        <?= admin_h($message) ?>
    </div>
<?php elseif ($status !== ''): ?>
    <div class="admin-alert">
        عملیات انجام نشد:
        <code><?= admin_h($status) ?></code>
    </div>
<?php endif; ?>

<section class="acl-shell">
    <header class="acl-hero">
        <div>
            <span>مدیریت متمرکز RBAC</span>
            <h2>سطوح و نقش‌های دسترسی</h2>
            <p>
                دسترسی منوها، زیرمنوها، صفحات، تب‌ها و عملیات
                را برای نقش‌ها و کاربران تعیین کنید.
            </p>
        </div>
        <div class="acl-counts">
            <article>
                <span>نقش‌ها</span>
                <strong><?= admin_h(
                    \App\Support\AdminFormat::digits(count($roles))
                ) ?></strong>
            </article>
            <article>
                <span>گروه‌های مجوز</span>
                <strong><?= admin_h(
                    \App\Support\AdminFormat::digits(count($groups))
                ) ?></strong>
            </article>
        </div>
    </header>

    <nav class="acl-tabs">
        <?php foreach ([
            'roles' => 'نقش‌ها و مجوزها',
            'users' => 'دسترسی اختصاصی کاربران',
            'audit' => 'تاریخچه تغییرات',
        ] as $code => $title): ?>
            <a
                href="/admin/access-control?tab=<?= admin_h($code) ?>"
                class="<?= $tab === $code ? 'is-active' : '' ?>"
            >
                <?= admin_h($title) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($tab === 'roles'): ?>
        <section class="acl-panel">
            <header class="acl-panel__header">
                <div>
                    <h3>مجوزهای نقش‌ها</h3>
                    <p>
                        هر نقش را باز کنید و دسترسی تمام ماژول‌ها
                        و عملیات ثبت‌شده را ذخیره کنید.
                    </p>
                </div>
                <input
                    type="search"
                    placeholder="جستجو در مجوزها..."
                    data-acl-search
                >
            </header>

            <?php foreach ($roles as $role): ?>
                <?php
                $protected = ($role['code'] ?? '') === 'super_admin';
                ?>
                <details class="acl-role" <?= $protected ? 'open' : '' ?>>
                    <summary>
                        <span>
                            <strong><?= admin_h($role['title']) ?></strong>
                            <small dir="ltr"><?= admin_h($role['code']) ?></small>
                        </span>
                        <em>
                            <?= $protected
                                ? 'دسترسی کامل ثابت'
                                : 'ویرایش مجوزها' ?>
                        </em>
                    </summary>

                    <form
                        method="post"
                        action="/admin/access-control/roles"
                        data-acl-role-form
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= admin_h($csrf) ?>"
                        >
                        <input
                            type="hidden"
                            name="role_id"
                            value="<?= (int) $role['id'] ?>"
                        >

                        <?php foreach ($groups as $module => $moduleGroups): ?>
                            <section class="acl-module" data-acl-module>
                                <header>
                                    <div>
                                        <h4><?= admin_h(
                                            $moduleTitles[$module] ?? $module
                                        ) ?></h4>
                                        <small dir="ltr"><?= admin_h($module) ?></small>
                                    </div>
                                    <?php if (!$protected): ?>
                                        <div>
                                            <button
                                                type="button"
                                                data-acl-select
                                            >
                                                انتخاب همه
                                            </button>
                                            <button
                                                type="button"
                                                data-acl-clear
                                            >
                                                پاک‌کردن
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </header>

                                <?php foreach (
                                    $moduleGroups as $groupTitle => $items
                                ): ?>
                                    <div class="acl-group">
                                        <h5><?= admin_h($groupTitle) ?></h5>
                                        <div class="acl-grid">
                                            <?php foreach ($items as $permission): ?>
                                                <?php
                                                $code = (string) $permission['code'];
                                                $checked = isset(
                                                    $roleMap[(int) $role['id']][$code]
                                                );
                                                ?>
                                                <label
                                                    class="acl-item"
                                                    data-acl-item
                                                    data-search="<?= admin_h(
                                                        mb_strtolower(
                                                            implode(' ', [
                                                                $permission['title'] ?? '',
                                                                $code,
                                                                $permission['description'] ?? '',
                                                                $groupTitle,
                                                                $module,
                                                            ]),
                                                            'UTF-8'
                                                        )
                                                    ) ?>"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="<?= admin_h($code) ?>"
                                                        <?= $checked ? 'checked' : '' ?>
                                                        <?= $protected ? 'disabled' : '' ?>
                                                    >
                                                    <span>
                                                        <strong><?= admin_h(
                                                            $permission['title']
                                                        ) ?></strong>
                                                        <small dir="ltr"><?= admin_h($code) ?></small>
                                                        <?php if (
                                                            !empty($permission['is_sensitive'])
                                                        ): ?>
                                                            <em>حساس</em>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>

                        <div class="acl-save">
                            <label>
                                <span>دلیل تغییر مجوزها</span>
                                <input
                                    name="reason"
                                    maxlength="500"
                                    placeholder="برای ثبت در تاریخچه"
                                    <?= $protected ? 'readonly' : '' ?>
                                >
                            </label>
                            <button
                                class="admin-button"
                                type="submit"
                                <?= $protected ? 'disabled' : '' ?>
                            >
                                <?= $protected
                                    ? 'دسترسی کامل ثابت'
                                    : 'ذخیره مجوزهای نقش' ?>
                            </button>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
        </section>

    <?php elseif ($tab === 'users'): ?>
        <section class="acl-panel">
            <header class="acl-panel__header">
                <div>
                    <h3>جستجوی اشخاص و کاربران</h3>
                    <p>
                        نام، نام کاربری، کد ملی، موبایل، نقش
                        یا سازمان را جستجو کنید.
                    </p>
                </div>
            </header>

            <form method="get" action="/admin/access-control" class="acl-user-search">
                <input type="hidden" name="tab" value="users">
                <input
                    type="search"
                    name="q"
                    value="<?= admin_h($page['query'] ?? '') ?>"
                    placeholder="نام، کد ملی، موبایل، نقش یا سازمان"
                >
                <button class="admin-button" type="submit">جستجو</button>
            </form>

            <div class="acl-users">
                <?php foreach ($users as $user): ?>
                    <a
                        href="/admin/access-control?tab=users&user_id=<?= (int) $user['id'] ?>&q=<?= rawurlencode(
                            (string) ($page['query'] ?? '')
                        ) ?>"
                        class="<?= (int) $user['id']
                            === (int) ($page['selected_user_id'] ?? 0)
                                ? 'is-active'
                                : '' ?>"
                    >
                        <strong><?= admin_h($user['title']) ?></strong>
                        <span><?= admin_h(
                            implode(' • ', array_filter([
                                $user['organization_title'] ?? '',
                                $user['role_titles'] ?? '',
                            ]))
                        ) ?></span>
                        <small dir="ltr"><?= admin_h(
                            implode(' | ', array_filter([
                                $user['username'] ?? '',
                                $user['mobile'] ?? '',
                                $user['national_code'] ?? '',
                            ]))
                        ) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($selectedUser !== null): ?>
            <section class="acl-panel">
                <header class="acl-panel__header">
                    <div>
                        <span>کاربر انتخاب‌شده</span>
                        <h3><?= admin_h($selectedUser['title']) ?></h3>
                    </div>
                </header>

                <form method="get" action="/admin/access-control" class="acl-assignment">
                    <input type="hidden" name="tab" value="users">
                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= (int) $selectedUser['id'] ?>"
                    >
                    <label>
                        <span>محدوده اعمال استثنا</span>
                        <select
                            name="assignment_id"
                            onchange="this.form.submit()"
                        >
                            <option value="0">همه نقش‌های فعال کاربر</option>
                            <?php foreach ($assignments as $assignment): ?>
                                <option
                                    value="<?= (int) $assignment['id'] ?>"
                                    <?= (int) $assignment['id'] === $assignmentId
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= admin_h(
                                        $assignment['role_title']
                                        . ' — '
                                        . $assignment['scope_type']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>

                <form
                    method="post"
                    action="/admin/access-control/users"
                    class="acl-user-policy"
                >
                    <input
                        type="hidden"
                        name="_token"
                        value="<?= admin_h($csrf) ?>"
                    >
                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= (int) $selectedUser['id'] ?>"
                    >
                    <input
                        type="hidden"
                        name="role_assignment_id"
                        value="<?= $assignmentId ?>"
                    >

                    <section class="acl-policy">
                        <header>
                            <h4>سیاست ارسال اعلان</h4>
                            <p>
                                تعیین کنید کاربر فرم را نبیند،
                                با تأیید ارسال کند یا مستقیم ارسال داشته باشد.
                            </p>
                        </header>

                        <div>
                            <?php foreach ([
                                'none' => [
                                    'عدم دسترسی',
                                    'منو و فرم ارسال نمایش داده نمی‌شود.',
                                ],
                                'approval' => [
                                    'ارسال با تأیید',
                                    'ارسال پس از تأیید مدیر انجام می‌شود.',
                                ],
                                'direct' => [
                                    'ارسال مستقیم',
                                    'اعلان بدون تأیید ارسال می‌شود.',
                                ],
                                'inherit' => [
                                    'ارث‌بری از نقش',
                                    'سیاست نقش فعال حفظ می‌شود.',
                                ],
                            ] as $code => $definition): ?>
                                <label>
                                    <input
                                        type="radio"
                                        name="notification_policy"
                                        value="<?= admin_h($code) ?>"
                                        <?= $code === (
                                            $page['notification_policy'] ?? 'none'
                                        ) ? 'checked' : '' ?>
                                    >
                                    <span>
                                        <strong><?= admin_h($definition[0]) ?></strong>
                                        <small><?= admin_h($definition[1]) ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="acl-capabilities">
                        <?php
                        $capabilities = [
                            'can_search_recipients' => [
                                'notifications.recipients.search',
                                'جستجوی اشخاص و گیرندگان',
                            ],
                            'can_view_recipient_details' => [
                                'notifications.recipients.details',
                                'مشاهده نقش، سازمان و شهر گیرندگان',
                            ],
                            'can_use_manual_targets' => [
                                'notifications.manual_targets.use',
                                'ورود مقصد دستی',
                            ],
                        ];
                        ?>
                        <?php foreach ($capabilities as $name => $definition): ?>
                            <?php
                            $permissionCode = $definition[0];
                            $effective = isset($overrides[$permissionCode])
                                ? $overrides[$permissionCode] === 'allow'
                                : isset($inherited[$permissionCode]);
                            ?>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?= admin_h($name) ?>"
                                    value="1"
                                    <?= $effective ? 'checked' : '' ?>
                                >
                                <span>
                                    <strong><?= admin_h($definition[1]) ?></strong>
                                    <small dir="ltr"><?= admin_h(
                                        $permissionCode
                                    ) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </section>

                    <div class="acl-save">
                        <label>
                            <span>دلیل تغییر دسترسی</span>
                            <input
                                name="reason"
                                maxlength="500"
                                required
                                placeholder="مثلاً: مجوز ارسال مستقیم طبق ابلاغ مدیر"
                            >
                        </label>
                        <button class="admin-button" type="submit">
                            ذخیره سیاست دسترسی کاربر
                        </button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

    <?php elseif ($tab === 'audit'): ?>
        <section class="acl-panel">
            <header class="acl-panel__header">
                <div>
                    <h3>تاریخچه تغییرات دسترسی</h3>
                    <p>آخرین تغییرات نقش‌ها و سیاست کاربران.</p>
                </div>
            </header>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>زمان</th>
                            <th>مدیر</th>
                            <th>هدف</th>
                            <th>نوع تغییر</th>
                            <th>دلیل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit as $row): ?>
                            <tr>
                                <td dir="ltr"><?= admin_h($row['created_at']) ?></td>
                                <td><?= admin_h($row['actor_title']) ?></td>
                                <td>
                                    <?= admin_h($row['target_type']) ?>
                                    #<?= admin_h(
                                        \App\Support\AdminFormat::digits(
                                            $row['target_id']
                                        )
                                    ) ?>
                                </td>
                                <td><code><?= admin_h(
                                    $row['change_type']
                                ) ?></code></td>
                                <td><?= admin_h($row['reason'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</section>

<style>
.acl-shell{display:grid;gap:.8rem}
.acl-hero,.acl-panel{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:14px;padding:.85rem}
.acl-hero{align-items:center;background:linear-gradient(135deg,var(--admin-surface),var(--admin-primary-soft));display:flex;justify-content:space-between;gap:1rem}
.acl-hero h2,.acl-hero p,.acl-panel h3,.acl-panel p{margin:0}
.acl-hero>div:first-child{display:grid;gap:.18rem}
.acl-hero span{color:var(--admin-primary);font-size:.7rem;font-weight:800}
.acl-hero p,.acl-panel p{color:var(--admin-text-muted);font-size:.72rem}
.acl-counts{display:grid;grid-template-columns:repeat(2,minmax(95px,1fr));gap:.4rem}
.acl-counts article{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:9px;display:grid;padding:.45rem}
.acl-tabs{display:flex;flex-wrap:wrap;gap:.4rem}
.acl-tabs a{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:999px;color:var(--admin-text);font-size:.74rem;padding:.42rem .68rem;text-decoration:none}
.acl-tabs a.is-active{background:var(--admin-primary-soft);border-color:var(--admin-primary);color:var(--admin-primary);font-weight:800}
.acl-panel{display:grid;gap:.65rem}
.acl-panel__header,.acl-module>header,.acl-policy>header{align-items:center;display:flex;justify-content:space-between;gap:.6rem}
.acl-role{border:1px solid var(--admin-border);border-radius:11px;overflow:hidden}
.acl-role>summary{align-items:center;background:var(--admin-surface-muted);cursor:pointer;display:flex;justify-content:space-between;padding:.58rem}
.acl-role>summary span{display:grid;gap:.05rem}
.acl-role>summary small,.acl-role>summary em{color:var(--admin-text-muted);font-size:.62rem;font-style:normal}
.acl-role form{display:grid;gap:.55rem;padding:.6rem}
.acl-module{border:1px solid var(--admin-border);border-radius:10px;display:grid;gap:.4rem;padding:.5rem}
.acl-module>header{background:var(--admin-surface-muted);border-radius:8px;padding:.4rem}
.acl-module h4,.acl-group h5,.acl-policy h4{margin:0}
.acl-module header button{background:transparent;border:0;color:var(--admin-primary);cursor:pointer;font:inherit;font-size:.64rem}
.acl-group{display:grid;gap:.3rem}
.acl-group h5{font-size:.7rem}
.acl-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.32rem}
.acl-item{align-items:flex-start;border:1px solid var(--admin-border);border-radius:8px;display:flex;gap:.38rem;padding:.42rem}
.acl-item[hidden]{display:none!important}
.acl-item span{display:grid;gap:.05rem;min-width:0}
.acl-item strong{font-size:.69rem}
.acl-item small{color:var(--admin-text-muted);font-size:.57rem;overflow:hidden;text-overflow:ellipsis}
.acl-item em{color:#9b3434;font-size:.56rem;font-style:normal}
.acl-save{align-items:end;background:var(--admin-surface-muted);border:1px solid var(--admin-border);border-radius:9px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.5rem;padding:.5rem}
.acl-save label,.acl-assignment label{display:grid;gap:.22rem}
.acl-save label span,.acl-assignment label span{font-size:.68rem;font-weight:800}
.acl-user-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.4rem}
.acl-users{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.35rem}
.acl-users a{border:1px solid var(--admin-border);border-radius:9px;color:var(--admin-text);display:grid;gap:.08rem;padding:.48rem;text-decoration:none;min-width:0}
.acl-users a.is-active{background:var(--admin-primary-soft);border-color:var(--admin-primary)}
.acl-users span,.acl-users small{color:var(--admin-text-muted);font-size:.61rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.acl-assignment{max-width:520px}
.acl-user-policy{display:grid;gap:.55rem}
.acl-policy{background:var(--admin-surface-muted);border:1px solid var(--admin-border);border-radius:10px;display:grid;gap:.5rem;padding:.55rem}
.acl-policy>div{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.35rem}
.acl-policy label,.acl-capabilities label{background:var(--admin-surface);border:1px solid var(--admin-border);border-radius:8px;display:flex;gap:.35rem;padding:.45rem}
.acl-policy label span,.acl-capabilities label span{display:grid;gap:.05rem}
.acl-policy strong,.acl-capabilities strong{font-size:.68rem}
.acl-policy small,.acl-capabilities small{color:var(--admin-text-muted);font-size:.58rem}
.acl-capabilities{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.35rem}
@media(max-width:1050px){.acl-users{grid-template-columns:repeat(2,minmax(0,1fr))}.acl-policy>div{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:700px){.acl-hero{align-items:stretch;flex-direction:column}.acl-grid,.acl-users,.acl-policy>div,.acl-capabilities,.acl-counts{grid-template-columns:1fr}.acl-panel__header{align-items:stretch;flex-direction:column}.acl-save{grid-template-columns:1fr}}
</style>

<script>
(() => {
    const search = document.querySelector('[data-acl-search]');
    const modules = Array.from(
        document.querySelectorAll('[data-acl-module]')
    );

    search?.addEventListener('input', () => {
        const needle = (search.value || '')
            .trim()
            .toLocaleLowerCase('fa');

        modules.forEach((module) => {
            let visible = 0;

            module.querySelectorAll('[data-acl-item]')
                .forEach((item) => {
                    item.hidden = !(
                        needle === ''
                        || item.dataset.search.includes(needle)
                    );

                    if (!item.hidden) {
                        visible++;
                    }
                });

            module.hidden = needle !== '' && visible === 0;
        });
    });

    document.querySelectorAll('[data-acl-role-form]')
        .forEach((form) => {
            form.querySelectorAll('[data-acl-module]')
                .forEach((module) => {
                    module.querySelector('[data-acl-select]')
                        ?.addEventListener('click', () => {
                            module.querySelectorAll(
                                'input[type="checkbox"]:not(:disabled)'
                            ).forEach((checkbox) => {
                                checkbox.checked = true;
                            });
                        });

                    module.querySelector('[data-acl-clear]')
                        ?.addEventListener('click', () => {
                            module.querySelectorAll(
                                'input[type="checkbox"]:not(:disabled)'
                            ).forEach((checkbox) => {
                                checkbox.checked = false;
                            });
                        });
                });
        });
})();
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
PHP

echo "ADDED: $manager_view"

echo
echo "=== Add Foundation Test ==="

cat > "$test_file" <<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: cannot read {$path}\n");
        exit(1);
    }

    return $content;
};

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'CreateAccessControlFoundation.php'
);
$repository = $read(
    'public_html/app/Repositories/AccessControlRepository.php'
);
$permissionRepository = $read(
    'public_html/app/Repositories/PermissionRepository.php'
);
$authorization = $read(
    'public_html/app/Services/AuthorizationService.php'
);
$service = $read(
    'public_html/app/Services/AccessControlService.php'
);
$policy = $read(
    'public_html/app/Services/'
    . 'NotificationSendAccessPolicyService.php'
);
$send = $read(
    'public_html/app/Services/NotificationSendCenterService.php'
);
$routes = $read('public_html/routes/web.php');
$view = $read(
    'public_html/resources/views/admin/access-control.php'
);

$expect(
    str_contains($migration, 'user_permission_overrides')
    && str_contains($migration, 'access_control_change_logs')
    && str_contains($migration, 'notifications.send.direct')
    && str_contains($migration, 'notifications.send.request'),
    'Access schema or permissions are incomplete.'
);

$expect(
    str_contains($permissionRepository, 'userPermissionOverride')
    && str_contains($authorization, "\$override === 'allow'"),
    'User override precedence is incomplete.'
);

$expect(
    str_contains($repository, 'saveRolePermissions')
    && str_contains($repository, 'saveUserPolicy')
    && str_contains($service, 'notification_policy'),
    'Access management operations are incomplete.'
);

$expect(
    str_contains($policy, 'approval_required')
    && str_contains($send, 'notification_send_approval_required')
    && str_contains(
        $send,
        'notification_send_manual_target_forbidden'
    ),
    'Notification access enforcement is incomplete.'
);

$expect(
    str_contains($routes, '/admin/access-control/users')
    && str_contains($view, 'سیاست ارسال اعلان')
    && str_contains($view, 'data-acl-role-form'),
    'Access management UI or routes are incomplete.'
);

echo "Access control foundation checks passed.\n";
PHP

mkdir -p tools
cp -- "$0" "$tool_file"

stage=(
  "$migration"
  "$repo"
  "$service"
  "$policy"
  "$permission_repo"
  "$authorization"
  "$send_service"
  "$settings_service"
  "$web_routes"
  "$access_view"
  "$manager_view"
  "$test_file"
  "$tool_file"
  "${registries[@]}"
)

git add -- "${stage[@]}"

echo
echo "=== Cached Validation ==="
git diff --cached --check

if command -v php >/dev/null 2>&1; then
  echo
  echo "=== PHP Validation ==="

  php_files=(
    "$migration"
    "$repo"
    "$service"
    "$policy"
    "$permission_repo"
    "$authorization"
    "$send_service"
    "$settings_service"
    "$web_routes"
    "$access_view"
    "$manager_view"
    "$test_file"
  )

  for file in "${php_files[@]}"; do
    php -l "$file"
  done

  php "$test_file"
else
  echo
  echo "PHP_NOT_AVAILABLE_ON_WINDOWS=SKIPPED"
fi

echo
echo "=== Access Control Markers ==="

git grep -n -E \
  "CreateAccessControlFoundation|user_permission_overrides|access_control_change_logs|userPermissionOverride|AccessControlService|NotificationSendAccessPolicyService|notifications.send.direct|notifications.send.request|/admin/access-control|Access control foundation checks passed" \
  -- \
  public_html \
  tests/AccessControlFoundationTest.php

echo
echo "=== Migration Registries ==="
printf '%s\n' "${registries[@]}"

echo
echo "=== Required Scope Checks ==="

if git diff --cached --name-only \
  | grep -Fxq "$migration"
then
  echo "ACCESS_MIGRATION_ADDED=1"
else
  echo "ACCESS_MIGRATION_ADDED=0"
  exit 1
fi

if git diff --cached --name-only \
  | grep -Fxq "$manager_view"
then
  echo "ACCESS_MANAGEMENT_UI_ADDED=1"
else
  echo "ACCESS_MANAGEMENT_UI_ADDED=0"
  exit 1
fi

echo "MIGRATION_REQUIRED=YES"

echo
echo "=== Unstaged Changes Check ==="

if git diff --quiet; then
  echo "UNSTAGED_CHANGES=0"
else
  echo "UNSTAGED_CHANGES=1"
  git status --short
  exit 1
fi

echo
echo "=== Cached Summary ==="
git diff --cached --stat

echo
echo "=== Final Status ==="
git status --short --branch

echo
echo "ACCESS CONTROL FOUNDATION ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
