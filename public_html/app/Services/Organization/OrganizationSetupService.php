<?php

namespace App\Services\Organization;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

class OrganizationSetupService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function workspace(): array
    {
        try {
            return [
                'ok' => true,
                'organizations' => $this->organizations(),
                'units' => $this->units(),
                'position_templates' => $this->positionTemplates(),
                'organization_positions' => $this->organizationPositions(),
                'users' => $this->users(),
                'persons' => $this->persons(),
                'records' => [
                    'organizations' => $this->organizationRecords(),
                    'units' => $this->unitRecords(),
                    'positions' => $this->positionRecords(),
                    'identities' => $this->identityRecords(),
                ],
            ];
        } catch (Throwable) {
            return [
                'ok' => false,
                'organizations' => [],
                'units' => [],
                'position_templates' => [],
                'organization_positions' => [],
                'users' => [],
                'persons' => [],
                'records' => [
                    'organizations' => [],
                    'units' => [],
                    'positions' => [],
                    'identities' => [],
                ],
            ];
        }
    }

    public function createOrganization(array $data): string
    {
        $titleFa = $this->required($data['title_fa'] ?? '', 'عنوان فارسی سازمان الزامی است.', 255);
        $titleEn = $this->optional($data['title_en'] ?? '', 255);
        $shortTitle = $this->optional($data['short_title'] ?? '', 150);
        $parentRef = trim((string) ($data['parent_reference'] ?? ''));
        $parentId = null;
        $depth = 0;
        $path = null;

        if ($parentRef !== '') {
            $parent = $this->row(
                'SELECT id, depth, path FROM organizations WHERE public_reference = ? AND deleted_at IS NULL LIMIT 1',
                [$parentRef]
            );

            if (!$parent) {
                throw new RuntimeException('سازمان بالادست معتبر نیست.');
            }

            $parentId = (int) $parent['id'];
            $depth = min(255, (int) $parent['depth'] + 1);
            $path = trim((string) ($parent['path'] ?? ''), '/');
        }

        $reference = $this->uuid();
        $sortOrder = $this->int($data['sort_order'] ?? 0, 0, 100000);
        $statement = $this->db->prepare(
            'INSERT INTO organizations (
                public_reference, parent_id, title, title_fa, title_en, short_title,
                depth, path, sort_order, is_active, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            $reference,
            $parentId,
            $titleFa,
            $titleFa,
            $titleEn,
            $shortTitle,
            $depth,
            $path,
            $sortOrder,
        ]);

        $id = (int) $this->db->lastInsertId();
        $finalPath = trim(($path ? $path . '/' : '') . $id, '/');
        $this->db->prepare('UPDATE organizations SET path = ? WHERE id = ?')->execute([$finalPath, $id]);

        return $reference;
    }

    public function createUnit(array $data): string
    {
        $organizationReference = trim((string) ($data['organization_reference'] ?? ''));
        $organizationId = $this->scalar(
            'SELECT id FROM organizations WHERE public_reference = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [$organizationReference]
        );

        if (!$organizationId) {
            throw new RuntimeException('انتخاب سازمان الزامی است.');
        }

        $titleFa = $this->required($data['title_fa'] ?? '', 'عنوان فارسی واحد الزامی است.', 255);
        $titleEn = $this->optional($data['title_en'] ?? '', 255);
        $code = $this->optional($data['code'] ?? '', 100);
        $parentReference = trim((string) ($data['parent_reference'] ?? ''));
        $parentId = null;
        $depth = 0;
        $path = null;

        if ($parentReference !== '') {
            $parent = $this->row(
                "SELECT id, organization_id, depth, path
                 FROM org_units
                 WHERE public_reference = ? AND status = 'active' AND deleted_at IS NULL
                 LIMIT 1",
                [$parentReference]
            );

            if (!$parent || (int) $parent['organization_id'] !== (int) $organizationId) {
                throw new RuntimeException('واحد بالادست باید متعلق به همان سازمان باشد.');
            }

            $parentId = (int) $parent['id'];
            $depth = min(255, (int) $parent['depth'] + 1);
            $path = trim((string) ($parent['path'] ?? ''), '/');
        }

        if ($code !== null) {
            $duplicate = $this->scalar(
                'SELECT COUNT(*) FROM org_units WHERE organization_id = ? AND code = ? AND deleted_at IS NULL',
                [(int) $organizationId, $code]
            );

            if ((int) $duplicate > 0) {
                throw new RuntimeException('کد واحد در این سازمان تکراری است.');
            }
        }

        $reference = $this->uuid();
        $sortOrder = $this->int($data['sort_order'] ?? 0, 0, 100000);
        $statement = $this->db->prepare(
            "INSERT INTO org_units (
                public_reference, organization_id, parent_id, code, title, title_fa, title_en,
                depth, path, sort_order, status, description, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $statement->execute([
            $reference,
            (int) $organizationId,
            $parentId,
            $code,
            $titleFa,
            $titleFa,
            $titleEn,
            $depth,
            $path,
            $sortOrder,
            $this->optional($data['description'] ?? '', 2000),
        ]);

        $id = (int) $this->db->lastInsertId();
        $finalPath = trim(($path ? $path . '/' : '') . $id, '/');
        $this->db->prepare('UPDATE org_units SET path = ? WHERE id = ?')->execute([$finalPath, $id]);

        return $reference;
    }

    public function createOrganizationPosition(array $data): string
    {
        $organizationReference = trim((string) ($data['organization_reference'] ?? ''));
        $organizationId = $this->scalar(
            'SELECT id FROM organizations WHERE public_reference = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [$organizationReference]
        );

        if (!$organizationId) {
            throw new RuntimeException('انتخاب سازمان الزامی است.');
        }

        $unitReference = trim((string) ($data['unit_reference'] ?? ''));
        $unitId = null;

        if ($unitReference !== '') {
            $unit = $this->row(
                "SELECT id, organization_id
                 FROM org_units
                 WHERE public_reference = ? AND status = 'active' AND deleted_at IS NULL
                 LIMIT 1",
                [$unitReference]
            );

            if (!$unit || (int) $unit['organization_id'] !== (int) $organizationId) {
                throw new RuntimeException('واحد انتخاب‌شده متعلق به سازمان نیست.');
            }

            $unitId = (int) $unit['id'];
        }

        $titleFa = $this->required($data['title_fa'] ?? '', 'عنوان فارسی پست الزامی است.', 255);
        $titleEn = $this->optional($data['title_en'] ?? '', 255);
        $code = $this->optional($data['code'] ?? '', 100);
        $sortOrder = $this->int($data['sort_order'] ?? 0, 0, 100000);
        $templateId = $this->scalar(
            "SELECT id FROM positions WHERE title = ? AND status = 'active' LIMIT 1",
            [$titleFa]
        );

        if (!$templateId) {
            $templateReference = $this->uuid();
            $statement = $this->db->prepare(
                "INSERT INTO positions (
                    public_reference, code, title, title_fa, title_en, status,
                    sort_order, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, 'active', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $statement->execute([$templateReference, $code, $titleFa, $titleFa, $titleEn, $sortOrder]);
            $templateId = (int) $this->db->lastInsertId();
        }

        $reference = $this->uuid();
        $statement = $this->db->prepare(
            "INSERT INTO organization_positions (
                public_reference, organization_id, org_unit_id, position_id, code,
                title_override, title_fa, title_en, headcount_limit, is_head,
                is_acting_allowed, status, sort_order, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'active', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $statement->execute([
            $reference,
            (int) $organizationId,
            $unitId,
            (int) $templateId,
            $code,
            $titleFa,
            $titleFa,
            $titleEn,
            $this->int($data['headcount_limit'] ?? 1, 1, 10000),
            isset($data['is_head']) ? 1 : 0,
            $sortOrder,
        ]);

        return $reference;
    }

    public function linkUserToPerson(array $data): void
    {
        $userId = $this->int($data['user_id'] ?? 0, 1, PHP_INT_MAX);
        $personReference = trim((string) ($data['person_reference'] ?? ''));
        $personId = $this->scalar(
            "SELECT id FROM persons WHERE public_reference = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1",
            [$personReference]
        );

        if (!$personId) {
            throw new RuntimeException('شخص انتخاب‌شده معتبر نیست.');
        }

        $user = $this->row(
            "SELECT id, person_id FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1",
            [$userId]
        );

        if (!$user) {
            throw new RuntimeException('کاربر انتخاب‌شده معتبر نیست.');
        }

        $used = $this->scalar(
            'SELECT id FROM users WHERE person_id = ? AND id <> ? AND deleted_at IS NULL LIMIT 1',
            [(int) $personId, $userId]
        );

        if ($used) {
            throw new RuntimeException('این شخص قبلاً به حساب کاربری دیگری متصل شده است.');
        }

        $this->db->prepare('UPDATE users SET person_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([(int) $personId, $userId]);
    }

    private function organizations(): array
    {
        $rows = $this->db->query(
            "SELECT id, public_reference,
                    COALESCE(NULLIF(title_fa, ''), title) AS title,
                    title_en, short_title, parent_id, depth, is_active, sort_order
             FROM organizations
             WHERE deleted_at IS NULL
             ORDER BY depth ASC, sort_order ASC, title ASC
             LIMIT 500"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = $row;
        }
        foreach ($rows as &$row) {
            $row['display_path'] = $this->pathLabel((int) $row['id'], $map);
        }

        return $rows;
    }

    private function units(): array
    {
        $rows = $this->db->query(
            "SELECT ou.id, ou.public_reference, ou.organization_id, ou.parent_id,
                    COALESCE(NULLIF(ou.title_fa, ''), ou.title) AS title,
                    ou.title_en, ou.code, ou.description, ou.depth, ou.status, ou.sort_order,
                    COALESCE(NULLIF(o.title_fa, ''), o.title) AS organization_title
             FROM org_units ou
             INNER JOIN organizations o ON o.id = ou.organization_id
             WHERE ou.deleted_at IS NULL AND ou.status = 'active'
             ORDER BY organization_title ASC, ou.depth ASC, ou.sort_order ASC, ou.title ASC
             LIMIT 1000"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = $row;
        }
        foreach ($rows as &$row) {
            $row['unit_path'] = $this->pathLabel((int) $row['id'], $map);
            $row['display_path'] = $row['organization_title'] . ' ← ' . $row['unit_path'];
        }

        return $rows;
    }

    private function positionTemplates(): array
    {
        return $this->db->query(
            "SELECT public_reference, COALESCE(NULLIF(title_fa, ''), title) AS title, title_en, code
             FROM positions
             WHERE status = 'active'
             ORDER BY sort_order ASC, title ASC
             LIMIT 500"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function organizationPositions(): array
    {
        return $this->db->query(
            "SELECT op.public_reference,
                    COALESCE(NULLIF(op.title_fa, ''), NULLIF(op.title_override, ''), p.title) AS title,
                    op.title_en, op.code, op.is_head, op.headcount_limit, op.status, op.sort_order,
                    COALESCE(NULLIF(o.title_fa, ''), o.title) AS organization_title,
                    COALESCE(NULLIF(ou.title_fa, ''), ou.title) AS unit_title
             FROM organization_positions op
             INNER JOIN organizations o ON o.id = op.organization_id
             INNER JOIN positions p ON p.id = op.position_id
             LEFT JOIN org_units ou ON ou.id = op.org_unit_id
             WHERE op.status = 'active'
             ORDER BY organization_title ASC, unit_title ASC, op.sort_order ASC, title ASC
             LIMIT 1000"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function users(): array
    {
        $rows = $this->db->query(
            "SELECT u.id, u.username, u.email, u.mobile, u.person_id,
                    COALESCE(NULLIF(p.display_name_fa, ''), p.full_name) AS person_name
             FROM users u
             LEFT JOIN persons p ON p.id = u.person_id
             WHERE u.status = 'active' AND u.deleted_at IS NULL
             ORDER BY u.id DESC
             LIMIT 500"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['label'] = $this->userLabel($row);
        }

        return $rows;
    }

    private function persons(): array
    {
        return $this->db->query(
            "SELECT public_reference,
                    COALESCE(NULLIF(display_name_fa, ''), full_name) AS title,
                    national_code
             FROM persons
             WHERE status = 'active' AND deleted_at IS NULL
             ORDER BY title ASC
             LIMIT 1000"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function organizationRecords(): array
    {
        return $this->db->query(
            "SELECT o.public_reference,
                    COALESCE(NULLIF(o.title_fa, ''), o.title) AS title,
                    o.title_en, o.short_title, o.sort_order, o.is_active,
                    COALESCE(NULLIF(parent.title_fa, ''), parent.title) AS parent_title
             FROM organizations o
             LEFT JOIN organizations parent ON parent.id = o.parent_id
             WHERE o.deleted_at IS NULL
             ORDER BY o.updated_at DESC, o.id DESC
             LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function unitRecords(): array
    {
        return $this->db->query(
            "SELECT ou.public_reference,
                    COALESCE(NULLIF(ou.title_fa, ''), ou.title) AS title,
                    ou.title_en, ou.code, ou.status, ou.sort_order,
                    COALESCE(NULLIF(o.title_fa, ''), o.title) AS organization_title,
                    COALESCE(NULLIF(parent.title_fa, ''), parent.title) AS parent_title
             FROM org_units ou
             INNER JOIN organizations o ON o.id = ou.organization_id
             LEFT JOIN org_units parent ON parent.id = ou.parent_id
             WHERE ou.deleted_at IS NULL
             ORDER BY ou.updated_at DESC, ou.id DESC
             LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function positionRecords(): array
    {
        return $this->db->query(
            "SELECT op.public_reference,
                    COALESCE(NULLIF(op.title_fa, ''), NULLIF(op.title_override, ''), p.title) AS title,
                    op.title_en, op.code, op.headcount_limit, op.is_head, op.status, op.sort_order,
                    COALESCE(NULLIF(o.title_fa, ''), o.title) AS organization_title,
                    COALESCE(NULLIF(ou.title_fa, ''), ou.title) AS unit_title
             FROM organization_positions op
             INNER JOIN organizations o ON o.id = op.organization_id
             INNER JOIN positions p ON p.id = op.position_id
             LEFT JOIN org_units ou ON ou.id = op.org_unit_id
             ORDER BY op.updated_at DESC, op.id DESC
             LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function identityRecords(): array
    {
        $rows = $this->db->query(
            "SELECT u.username, u.email, u.mobile, u.status,
                    COALESCE(NULLIF(p.display_name_fa, ''), p.full_name) AS person_name,
                    u.updated_at
             FROM users u
             INNER JOIN persons p ON p.id = u.person_id
             WHERE u.deleted_at IS NULL AND p.deleted_at IS NULL
             ORDER BY u.updated_at DESC, u.id DESC
             LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['user_label'] = $this->userLabel($row);
        }

        return $rows;
    }

    private function userLabel(array $row): string
    {
        foreach (['username', 'email', 'mobile'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'حساب کاربری';
    }

    private function required(mixed $value, string $message, int $max): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new RuntimeException($message);
        }

        return $this->cut($value, $max);
    }

    private function optional(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $this->cut($value, $max);
    }

    private function cut(string $value, int $max): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $max, 'UTF-8')
            : substr($value, 0, $max);
    }

    private function int(mixed $value, int $min, int $max): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);

        return $number === false || $number < $min || $number > $max ? $min : (int) $number;
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 4095) | 0x4000,
            random_int(0, 16383) | 0x8000,
            random_int(0, 65535),
            random_int(0, 65535),
            random_int(0, 65535)
        );
    }

    private function pathLabel(int $id, array $map): string
    {
        $parts = [];
        $guard = 0;

        while ($id > 0 && isset($map[$id]) && $guard++ < 30) {
            array_unshift($parts, (string) $map[$id]['title']);
            $id = (int) ($map[$id]['parent_id'] ?? 0);
        }

        return implode(' ← ', $parts);
    }

    private function scalar(string $sql, array $params): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn();
    }

    private function row(string $sql, array $params): ?array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
