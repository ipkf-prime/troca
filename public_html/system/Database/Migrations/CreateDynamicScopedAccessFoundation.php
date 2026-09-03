<?php
declare(strict_types=1);

namespace IPKF\Database\Migrations;

/**
 * DYNAMIC_SCOPED_ACCESS_FOUNDATION_V1
 *
 * Extends existing roles/permissions/role assignments; does not replace them.
 */
class CreateDynamicScopedAccessFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS access_scope_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                entity_type_code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NULL,
                hierarchy_rank INT NOT NULL DEFAULT 0,
                supports_descendants TINYINT(1) NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY access_scope_types_code_unique (code),
                INDEX access_scope_types_active_sort_index (is_active, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS access_constraint_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(190) NOT NULL,
                value_kind_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'text',
                operators_json LONGTEXT NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY access_constraint_types_code_unique (code),
                INDEX access_constraint_types_active_sort_index (is_active, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_scope_policies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_id BIGINT UNSIGNED NOT NULL,
                scope_type_code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY role_scope_policies_unique (role_id, scope_type_code),
                INDEX role_scope_policies_role_index (role_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_identity_requirements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_id BIGINT UNSIGNED NOT NULL,
                field_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                verification_mode_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'present',
                is_required TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY role_identity_requirements_unique (role_id, field_code),
                INDEX role_identity_requirements_role_index (role_id, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_assignment_scopes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_assignment_id BIGINT UNSIGNED NOT NULL,
                scope_type_code VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                scope_reference VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
                effect_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'allow',
                include_descendants TINYINT(1) NOT NULL DEFAULT 0,
                metadata_json LONGTEXT NULL,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY role_assignment_scopes_unique
                    (role_assignment_id, scope_type_code, scope_reference, effect_code),
                INDEX role_assignment_scopes_assignment_index (role_assignment_id, id),
                INDEX role_assignment_scopes_lookup_index
                    (scope_type_code, scope_reference, effect_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_assignment_constraints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_assignment_id BIGINT UNSIGNED NOT NULL,
                constraint_type_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                operator_code VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                value_json LONGTEXT NOT NULL,
                effect_code VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'allow',
                sort_order INT NOT NULL DEFAULT 0,
                created_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX role_assignment_constraints_assignment_index
                    (role_assignment_id, sort_order, id),
                INDEX role_assignment_constraints_type_index
                    (constraint_type_code, operator_code, effect_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $scope = $this->db->prepare("
            INSERT INTO access_scope_types
                (code,title,entity_type_code,hierarchy_rank,supports_descendants,
                 is_system,is_active,sort_order,created_at,updated_at)
            VALUES (?,?,?,?,?,1,1,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title=VALUES(title),
                entity_type_code=VALUES(entity_type_code),
                hierarchy_rank=VALUES(hierarchy_rank),
                supports_descendants=VALUES(supports_descendants),
                is_active=1,sort_order=VALUES(sort_order),updated_at=CURRENT_TIMESTAMP
        ");

        $scopes = [
            ['global','سراسری',null,0,1,10],
            ['national','ملی / اتحادیه مرکزی','organization',10,1,20],
            ['province','استان','province',20,1,30],
            ['county','شهرستان','county',30,1,40],
            ['district','بخش','district',40,1,50],
            ['village','دهستان','village',50,1,60],
            ['city','شهر','city',50,1,70],
            ['organization','سازمان','organization',60,1,80],
            ['company','شرکت','organization',70,1,90],
            ['warehouse','انبار','warehouse',80,1,100],
            ['center','مرکز','center',80,1,110],
            ['org_unit','واحد سازمانی','org_unit',90,1,120],
            ['project','پروژه','project',100,0,130],
            ['own','فقط داده‌های خود کاربر','user',110,0,140],
            ['assigned','فقط موارد تخصیص‌یافته','assignment',120,0,150],
        ];
        foreach ($scopes as $row) {
            $scope->execute($row);
        }

        $operators = json_encode(
            ['eq','neq','in','not_in','contains','exists'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $constraint = $this->db->prepare("
            INSERT INTO access_constraint_types
                (code,title,value_kind_code,operators_json,is_system,is_active,
                 sort_order,created_at,updated_at)
            VALUES (?,?,?, ?,1,1,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title=VALUES(title),value_kind_code=VALUES(value_kind_code),
                operators_json=VALUES(operators_json),is_active=1,
                sort_order=VALUES(sort_order),updated_at=CURRENT_TIMESTAMP
        ");
        foreach ([
            ['project','پروژه','reference',10],
            ['service','خدمت / سرویس','reference',20],
            ['category','دسته‌بندی','reference',30],
            ['status','وضعیت','code',40],
            ['priority','اولویت','code',50],
            ['organization_type','نوع سازمان','code',60],
            ['record_owner','مالک رکورد','reference',70],
            ['assignment','تخصیص','reference',80],
            ['custom_tag','برچسب سفارشی','text',90],
        ] as $row) {
            $constraint->execute([$row[0],$row[1],$row[2],$operators,$row[3]]);
        }

        $this->seedPermission();
        $this->grantPermission();
        $this->alignRoutes();
    }

    public function down(): void
    {
        if ($this->tableExists('admin_route_permissions')) {
            $this->db->exec("
                DELETE FROM admin_route_permissions
                WHERE route_pattern IN (
                    '/admin/access-control/roles/create',
                    '/admin/access-control/scopes'
                )
            ");
        }

        if ($this->tableExists('permissions')) {
            $id = $this->db->query("
                SELECT id FROM permissions
                WHERE code='access.scopes.manage' LIMIT 1
            ")->fetchColumn();

            if ($id && $this->tableExists('role_permissions')) {
                $s = $this->db->prepare("DELETE FROM role_permissions WHERE permission_id=?");
                $s->execute([(int) $id]);
            }
            $this->db->exec("DELETE FROM permissions WHERE code='access.scopes.manage'");
        }

        foreach ([
            'role_assignment_constraints','role_assignment_scopes',
            'role_identity_requirements','role_scope_policies',
            'access_constraint_types','access_scope_types'
        ] as $table) {
            $this->db->exec("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    private function seedPermission(): void
    {
        if (!$this->tableExists('permissions')) {
            return;
        }

        $columns = $this->columns('permissions');
        $names = ['code','module','resource','action','title'];
        $values = [
            'access.scopes.manage','access','scopes','manage',
            'مدیریت حوزه‌ها و محدودیت‌های دسترسی'
        ];

        $optional = [
            'description' => 'تعریف حوزه جغرافیایی، سازمانی، پروژه‌ای و محدودیت جزئی انتساب نقش.',
            'display_group' => 'سطوح و نقش‌های دسترسی',
            'display_type' => 'operation',
            'sort_order' => 45,
            'is_sensitive' => 1,
        ];

        foreach ($optional as $column => $value) {
            if (isset($columns[$column])) {
                $names[] = $column;
                $values[] = $value;
            }
        }

        $names[] = 'is_active';
        $values[] = 1;
        $names[] = 'created_at';
        $names[] = 'updated_at';

        $quoted = implode(', ', array_map(fn($n) => "`{$n}`", $names));
        $placeholders = [];
        foreach ($names as $name) {
            $placeholders[] = in_array($name, ['created_at','updated_at'], true)
                ? 'CURRENT_TIMESTAMP' : '?';
        }

        $updates = [
            'module=VALUES(module)','resource=VALUES(resource)',
            'action=VALUES(action)','title=VALUES(title)',
            'is_active=1','updated_at=CURRENT_TIMESTAMP'
        ];
        foreach (array_keys($optional) as $column) {
            if (isset($columns[$column])) {
                $updates[] = "`{$column}`=VALUES(`{$column}`)";
            }
        }

        $sql = "INSERT INTO permissions ({$quoted}) VALUES ("
            . implode(', ', $placeholders)
            . ") ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        $params = [];
        foreach ($names as $i => $name) {
            if (!in_array($name, ['created_at','updated_at'], true)) {
                $params[] = $values[$i];
            }
        }
        $this->db->prepare($sql)->execute($params);
    }

    private function grantPermission(): void
    {
        if (!$this->tableExists('role_permissions')) {
            return;
        }

        $this->db->exec("
            INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
            SELECT DISTINCT src.role_id,target.id,CURRENT_TIMESTAMP
            FROM role_permissions src
            INNER JOIN permissions existing ON existing.id=src.permission_id
            CROSS JOIN permissions target
            WHERE existing.code IN ('access.manage','access.roles.manage','access.users.manage')
              AND target.code='access.scopes.manage'
        ");

        $this->db->exec("
            INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
            SELECT r.id,p.id,CURRENT_TIMESTAMP
            FROM roles r CROSS JOIN permissions p
            WHERE r.code='super_admin' AND p.code='access.scopes.manage'
        ");
    }

    private function alignRoutes(): void
    {
        if (!$this->tableExists('admin_route_permissions')) {
            return;
        }

        $permissions = json_encode(
            ['access.manage','access.roles.manage','access.scopes.manage'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $s = $this->db->prepare("
            INSERT INTO admin_route_permissions
                (route_pattern,http_method,permission_mode,permission_codes_json,
                 priority,is_active,created_at,updated_at)
            VALUES (?,?,'any',?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                permission_mode='any',permission_codes_json=VALUES(permission_codes_json),
                priority=VALUES(priority),is_active=1,updated_at=CURRENT_TIMESTAMP
        ");

        foreach ([
            ['/admin/access-control/roles/create','GET',130],
            ['/admin/access-control/roles/create','POST',131],
            ['/admin/access-control/scopes','GET',132],
            ['/admin/access-control/scopes','POST',133],
        ] as $route) {
            $s->execute([$route[0],$route[1],$permissions,$route[2]]);
        }
    }

    private function columns(string $table): array
    {
        $s = $this->db->prepare("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema=DATABASE() AND table_name=?
        ");
        $s->execute([$table]);
        return array_fill_keys($s->fetchAll(\PDO::FETCH_COLUMN) ?: [], true);
    }

    private function tableExists(string $table): bool
    {
        $s = $this->db->prepare("
            SELECT COUNT(*) FROM information_schema.tables
            WHERE table_schema=DATABASE() AND table_name=?
        ");
        $s->execute([$table]);
        return (int) $s->fetchColumn() > 0;
    }
}
