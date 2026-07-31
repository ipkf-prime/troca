<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

class AdminUserManagementRepository extends BaseRepository
{
    public function findForForm(int $userId): ?array
    {
        $profileJoin = Database::tableExists('person_profiles')
            ? 'LEFT JOIN person_profiles ON person_profiles.person_id = persons.id'
            : '';
        $profileSelect = Database::tableExists('person_profiles')
            ? 'person_profiles.birth_place, person_profiles.identity_number, person_profiles.identity_serial'
            : 'NULL AS birth_place, NULL AS identity_number, NULL AS identity_serial';

        $personColumns = [
            'person_type' => "'individual'",
            'national_code' => 'NULL',
            'father_name' => 'NULL',
            'birth_date' => 'NULL',
            'province_id' => 'NULL',
            'county_id' => 'NULL',
            'city_id' => 'NULL',
        ];

        $personSelect = [];
        foreach ($personColumns as $column => $fallback) {
            $personSelect[] = Database::columnExists('persons', $column)
                ? "persons.{$column}"
                : "{$fallback} AS {$column}";
        }

        $statement = $this->connection()->prepare("
            SELECT
                users.id,
                users.person_id,
                users.username,
                users.email,
                users.mobile,
                users.status,
                users.email_verified_at,
                users.mobile_verified_at,
                persons.first_name,
                persons.last_name,
                persons.full_name,
                " . implode(",\n                ", $personSelect) . ",
                {$profileSelect}
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            {$profileJoin}
            WHERE users.id = ?
              AND users.deleted_at IS NULL
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $user['role_ids'] = $this->globalRoleIdsForUser($userId);
        $user = array_merge(
            $user,
            $this->contactFormData((int) ($user['person_id'] ?? 0)),
            $this->addressFormData((int) ($user['person_id'] ?? 0))
        );

        return $user;
    }

    public function roles(bool $includeProtected): array
    {
        $where = $includeProtected
            ? 'roles.is_active = 1'
            : "roles.is_active = 1 AND roles.code <> 'super_admin'";

        $statement = $this->connection()->query("
            SELECT
                roles.id,
                roles.code,
                roles.title,
                roles.priority,
                roles.is_system,
                roles.role_kind_id,
                roles.role_area_id,
                COALESCE(role_kinds.code, 'uncategorized') AS role_kind_code,
                COALESCE(role_kinds.title, 'سایر') AS role_kind_title,
                COALESCE(role_areas.code, 'global') AS role_area_code,
                COALESCE(role_areas.title, 'سراسری') AS role_area_title
            FROM roles
            LEFT JOIN role_kinds ON role_kinds.id = roles.role_kind_id
            LEFT JOIN role_areas ON role_areas.id = roles.role_area_id
            WHERE {$where}
            ORDER BY
                CASE WHEN roles.code = 'user' THEN 0 ELSE 1 END,
                roles.code ASC,
                roles.id ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roleKinds(bool $includeProtected): array
    {
        $protected = $includeProtected ? '' : " AND roles.code <> 'super_admin'";
        $statement = $this->connection()->query("
            SELECT DISTINCT role_kinds.id, role_kinds.code, role_kinds.title
            FROM role_kinds
            INNER JOIN roles ON roles.role_kind_id = role_kinds.id
            WHERE roles.is_active = 1 {$protected}
            ORDER BY role_kinds.title ASC, role_kinds.id ASC
        ");
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function roleAreas(bool $includeProtected): array
    {
        $protected = $includeProtected ? '' : " AND roles.code <> 'super_admin'";
        $statement = $this->connection()->query("
            SELECT DISTINCT role_areas.id, role_areas.code, role_areas.title
            FROM role_areas
            INNER JOIN roles ON roles.role_area_id = role_areas.id
            WHERE roles.is_active = 1 {$protected}
            ORDER BY role_areas.title ASC, role_areas.id ASC
        ");
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function formOptions(): array
    {
        return [
            'person_types' => $this->codeOptions(
                'person_types',
                [
                    ['code' => 'individual', 'title' => 'شخص حقیقی'],
                    ['code' => 'legal', 'title' => 'شخص حقوقی'],
                ]
            ),
            'provinces' => $this->idOptions('provinces'),
            'counties' => $this->countyOptions(),
            'cities' => $this->idOptions(
                'cities',
                ['province_id', 'county_id']
            ),
            'address_types' => $this->idOptions('address_types'),
        ];
    }

    public function validPersonType(string $code): bool
    {
        if (!Database::tableExists('person_types')) {
            return in_array($code, ['individual', 'legal'], true);
        }

        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM person_types
            WHERE code = ?
        ");
        $statement->execute([$code]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function validProvinceId(int $id): bool
    {
        return $this->validLookupId('provinces', $id);
    }

    public function validCountyId(int $id): bool
    {
        $table = $this->countyTable();
        return $table !== null && $this->validLookupId($table, $id);
    }

    public function validCityId(int $id): bool
    {
        return $this->validLookupId('cities', $id);
    }

    public function validAddressTypeId(int $id): bool
    {
        return $this->validLookupId('address_types', $id);
    }

    public function roleIdsByIds(array $roleIds, bool $includeProtected): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $protectedFilter = $includeProtected
            ? ''
            : " AND code <> 'super_admin'";

        $statement = $this->connection()->prepare("
            SELECT id
            FROM roles
            WHERE id IN ({$placeholders})
              AND is_active = 1
              {$protectedFilter}
        ");
        $statement->execute($ids);

        return array_map(
            'intval',
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    public function roleIdByCode(string $code): ?int
    {
        $statement = $this->connection()->prepare("
            SELECT id
            FROM roles
            WHERE code = ?
              AND is_active = 1
            LIMIT 1
        ");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function userHasGlobalRole(int $userId, string $roleCode): bool
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM user_role_assignments assignments
            INNER JOIN roles ON roles.id = assignments.role_id
            WHERE assignments.user_id = ?
              AND assignments.scope_type = 'global'
              AND assignments.scope_id IS NULL
              AND assignments.is_active = 1
              AND roles.code = ?
              AND roles.is_active = 1
        ");
        $statement->execute([$userId, $roleCode]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function nationalCodeExists(
        string $nationalCode,
        ?int $exceptUserId = null
    ): bool {
        $nationalCode = preg_replace(
            '/\D+/',
            '',
            $nationalCode
        ) ?: '';

        if (
            $nationalCode === ''
            || !Database::columnExists(
                'persons',
                'national_code'
            )
        ) {
            return false;
        }

        $sql = "
            SELECT COUNT(*)
            FROM persons
            LEFT JOIN users
              ON users.person_id = persons.id
             AND users.deleted_at IS NULL
            WHERE persons.national_code = ?
        ";
        $parameters = [$nationalCode];

        if ($exceptUserId !== null) {
            $sql .= "
              AND persons.id <> COALESCE(
                  (
                      SELECT target.person_id
                      FROM users AS target
                      WHERE target.id = ?
                      LIMIT 1
                  ),
                  0
              )
            ";
            $parameters[] = $exceptUserId;
        }

        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function identityExists(
        string $field,
        string $normalizedValue,
        ?int $exceptUserId = null
    ): bool {
        $column = match ($field) {
            'username' => 'users.username_norm',
            'email' => 'users.email_norm',
            'mobile' => 'users.mobile_norm',
            default => null,
        };

        if ($column === null || $normalizedValue === '') {
            return false;
        }

        $sql = "
            SELECT COUNT(*)
            FROM users
            WHERE {$column} = ?
              AND users.deleted_at IS NULL
        ";
        $parameters = [$normalizedValue];

        if ($exceptUserId !== null) {
            $sql .= ' AND users.id <> ?';
            $parameters[] = $exceptUserId;
        }

        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(array $data, array $roleIds): int
    {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $personId = $this->insertPerson($data);
            $this->syncPersonProfile($personId, $data);

            $user = $db->prepare("
                INSERT INTO users (
                    person_id,
                    username,
                    username_norm,
                    email,
                    email_norm,
                    mobile,
                    mobile_norm,
                    password_hash,
                    status,
                    email_verified_at,
                    mobile_verified_at,
                    failed_login_attempts,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, 0,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $user->execute([
                $personId,
                $data['username'],
                $data['username_norm'],
                $data['email'],
                $data['email_norm'],
                $data['mobile'],
                $data['mobile_norm'],
                $data['password_hash'],
                $data['status'],
                $data['email_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
                $data['mobile_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
            ]);
            $userId = (int) $db->lastInsertId();

            $this->syncPrimaryContacts($personId, $data);
            $this->syncPrimaryAddress($personId, $data);
            $this->syncGlobalRoles($userId, $roleIds);

            $db->commit();

            return $userId;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function update(
        int $userId,
        array $data,
        array $roleIds,
        bool $preserveSuperAdmin
    ): bool {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $current = $this->findForForm($userId);
            if ($current === null) {
                throw new RuntimeException('user_not_found');
            }

            $personId = (int) ($current['person_id'] ?? 0);
            if ($personId < 1) {
                $personId = $this->insertPerson($data);
            } else {
                $this->updatePerson($personId, $data);
            }
            $this->syncPersonProfile($personId, $data);

            $passwordSql = '';
            $parameters = [
                $personId,
                $data['username'],
                $data['username_norm'],
                $data['email'],
                $data['email_norm'],
                $data['mobile'],
                $data['mobile_norm'],
                $data['status'],
                $data['email_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
                $data['mobile_verified']
                    ? gmdate('Y-m-d H:i:s')
                    : null,
            ];

            if ($data['password_hash'] !== null) {
                $passwordSql = ', password_hash = ?';
                $parameters[] = $data['password_hash'];
            }

            $parameters[] = $userId;

            $updateUser = $db->prepare("
                UPDATE users
                SET person_id = ?,
                    username = ?,
                    username_norm = ?,
                    email = ?,
                    email_norm = ?,
                    mobile = ?,
                    mobile_norm = ?,
                    status = ?,
                    email_verified_at = ?,
                    mobile_verified_at = ?
                    {$passwordSql},
                    failed_login_attempts = 0,
                    locked_until = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND deleted_at IS NULL
            ");
            $updateUser->execute($parameters);

            $this->syncPrimaryContacts($personId, $data);
            $this->syncPrimaryAddress($personId, $data);

            if ($preserveSuperAdmin) {
                $superAdminId = $this->roleIdByCode('super_admin');
                if ($superAdminId !== null) {
                    $roleIds[] = $superAdminId;
                }
            }

            $this->syncGlobalRoles($userId, $roleIds);

            $db->commit();

            return true;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function updateOwnProfile(
        int $userId,
        array $data
    ): bool {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            $current = $this->findForForm($userId);

            if ($current === null) {
                throw new RuntimeException(
                    'user_not_found'
                );
            }

            $personId = (int) (
                $current['person_id'] ?? 0
            );

            if ($personId < 1) {
                throw new RuntimeException(
                    'person_not_found'
                );
            }

            $payload = array_merge($current, $data, [
                'person_type' => (string) (
                    $current['person_type']
                    ?? 'individual'
                ),
                'email' => $current['email'] ?? null,
                'email_norm' => $current['email'] ?? null,
                'mobile' => $current['mobile'] ?? null,
                'mobile_norm' => $current['mobile'] ?? null,
                'full_name' => trim(
                    (string) $data['first_name']
                    . ' '
                    . (string) $data['last_name']
                ),
            ]);

            $this->updatePerson($personId, $payload);
            $this->syncPersonProfile(
                $personId,
                $payload
            );
            $this->syncPrimaryAddress(
                $personId,
                $payload
            );

            $db->commit();

            return true;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function detailFallback(int $userId): array
    {
        $form = $this->findForForm($userId);
        if ($form === null) {
            return ['contacts' => [], 'addresses' => []];
        }

        $contacts = [];
        if (trim((string) ($form['email'] ?? '')) !== '') {
            $contacts[] = [
                'type' => 'ایمیل',
                'label' => (string) ($form['contact_email_label'] ?? 'ایمیل اصلی'),
                'value' => (string) $form['email'],
                'is_primary' => 'بله',
                'is_verified' => !empty($form['email_verified_at']) ? 'بله' : 'خیر',
                'status' => ['code' => 'active', 'label' => 'فعال'],
            ];
        }
        if (trim((string) ($form['mobile'] ?? '')) !== '') {
            $contacts[] = [
                'type' => 'موبایل',
                'label' => (string) ($form['contact_mobile_label'] ?? 'موبایل اصلی'),
                'value' => (string) $form['mobile'],
                'is_primary' => 'بله',
                'is_verified' => !empty($form['mobile_verified_at']) ? 'بله' : 'خیر',
                'status' => ['code' => 'active', 'label' => 'فعال'],
            ];
        }

        $addresses = [];
        $addressPresent = trim((string) ($form['address_line'] ?? '')) !== ''
            || (int) ($form['province_id'] ?? 0) > 0
            || (int) ($form['city_id'] ?? 0) > 0
            || trim((string) ($form['district'] ?? '')) !== ''
            || trim((string) ($form['postal_code'] ?? '')) !== '';

        if ($addressPresent) {
            $options = $this->formOptions();
            $addresses[] = [
                'type' => $this->optionTitle(
                    $options['address_types'],
                    (int) ($form['address_type_id'] ?? 0),
                    'نشانی اصلی'
                ),
                'province' => $this->optionTitle(
                    $options['provinces'],
                    (int) ($form['province_id'] ?? 0)
                ),
                'city' => $this->optionTitle(
                    $options['cities'],
                    (int) ($form['city_id'] ?? 0)
                ),
                'district' => (string) ($form['district'] ?? ''),
                'postal_code' => (string) ($form['postal_code'] ?? ''),
                'is_primary' => 'بله',
                'address_line' => (string) ($form['address_line'] ?? ''),
                'status' => ['code' => 'active', 'label' => 'فعال'],
            ];
        }

        return [
            'contacts' => $contacts,
            'addresses' => $addresses,
        ];
    }

    private function insertPerson(array $data): int
    {
        $columns = [
            'person_type',
            'first_name',
            'last_name',
            'full_name',
            'email',
            'email_norm',
            'mobile',
            'mobile_norm',
            'status',
        ];
        $values = [
            $data['person_type'],
            $data['first_name'],
            $data['last_name'],
            $data['full_name'],
            $data['email'],
            $data['email_norm'],
            $data['mobile'],
            $data['mobile_norm'],
            'active',
        ];

        foreach ($this->optionalPersonFields() as $field) {
            if (Database::columnExists('persons', $field)) {
                $columns[] = $field;
                $values[] = $data[$field] ?? null;
            }
        }

        $columns[] = 'created_at';
        $columns[] = 'updated_at';
        $placeholders = array_fill(0, count($values), '?');
        $placeholders[] = 'CURRENT_TIMESTAMP';
        $placeholders[] = 'CURRENT_TIMESTAMP';

        $statement = $this->connection()->prepare(
            'INSERT INTO persons (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($values);

        return (int) $this->connection()->lastInsertId();
    }

    private function updatePerson(int $personId, array $data): void
    {
        $set = [
            'person_type = ?',
            'first_name = ?',
            'last_name = ?',
            'full_name = ?',
            'email = ?',
            'email_norm = ?',
            'mobile = ?',
            'mobile_norm = ?',
        ];
        $values = [
            $data['person_type'],
            $data['first_name'],
            $data['last_name'],
            $data['full_name'],
            $data['email'],
            $data['email_norm'],
            $data['mobile'],
            $data['mobile_norm'],
        ];

        foreach ($this->optionalPersonFields() as $field) {
            if (Database::columnExists('persons', $field)) {
                $set[] = "{$field} = ?";
                $values[] = $data[$field] ?? null;
            }
        }

        $set[] = 'updated_at = CURRENT_TIMESTAMP';
        $values[] = $personId;

        $statement = $this->connection()->prepare(
            'UPDATE persons SET ' . implode(', ', $set) . ' WHERE id = ?'
        );
        $statement->execute($values);
    }

    private function optionalPersonFields(): array
    {
        return [
            'national_code',
            'father_name',
            'birth_date',
            'province_id',
            'county_id',
            'city_id',
        ];
    }

    private function syncPersonProfile(int $personId, array $data): void
    {
        if (!Database::tableExists('person_profiles')) {
            return;
        }

        $fields = [];
        foreach (['birth_place', 'identity_number', 'identity_serial'] as $field) {
            if (Database::columnExists('person_profiles', $field)) {
                $fields[$field] = $data[$field] ?? null;
            }
        }

        if ($fields === []) {
            return;
        }

        $existing = $this->connection()->prepare("
            SELECT id
            FROM person_profiles
            WHERE person_id = ?
            LIMIT 1
        ");
        $existing->execute([$personId]);
        $profileId = $existing->fetchColumn();

        if ($profileId !== false) {
            $set = [];
            $values = [];
            foreach ($fields as $column => $value) {
                $set[] = "{$column} = ?";
                $values[] = $value;
            }
            if (Database::columnExists('person_profiles', 'updated_at')) {
                $set[] = 'updated_at = CURRENT_TIMESTAMP';
            }
            $values[] = (int) $profileId;
            $statement = $this->connection()->prepare(
                'UPDATE person_profiles SET ' . implode(', ', $set) . ' WHERE id = ?'
            );
            $statement->execute($values);
            return;
        }

        $columns = ['person_id'];
        $values = [$personId];
        $placeholders = ['?'];
        foreach ($fields as $column => $value) {
            $columns[] = $column;
            $values[] = $value;
            $placeholders[] = '?';
        }
        if (Database::columnExists('person_profiles', 'created_at')) {
            $columns[] = 'created_at';
            $placeholders[] = 'CURRENT_TIMESTAMP';
        }
        if (Database::columnExists('person_profiles', 'updated_at')) {
            $columns[] = 'updated_at';
            $placeholders[] = 'CURRENT_TIMESTAMP';
        }
        $statement = $this->connection()->prepare(
            'INSERT INTO person_profiles (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($values);
    }

    private function contactFormData(int $personId): array
    {
        $result = [
            'contact_email_label' => 'ایمیل اصلی',
            'contact_mobile_label' => 'موبایل اصلی',
        ];

        if ($personId < 1 || !Database::tableExists('person_contacts')) {
            return $result;
        }

        $codeSelect = Database::tableExists('contact_types')
            && Database::columnExists('contact_types', 'code')
            ? 'contact_types.code AS type_code'
            : 'NULL AS type_code';
        $titleColumn = $this->titleColumn('contact_types');
        $titleSelect = $titleColumn !== null
            ? "contact_types.{$titleColumn} AS type_title"
            : 'NULL AS type_title';
        $typeJoin = Database::tableExists('contact_types')
            ? 'LEFT JOIN contact_types ON contact_types.id = person_contacts.contact_type_id'
            : '';

        $statement = $this->connection()->prepare("
            SELECT
                person_contacts.value,
                person_contacts.label,
                person_contacts.is_primary,
                {$codeSelect},
                {$titleSelect}
            FROM person_contacts
            {$typeJoin}
            WHERE person_contacts.person_id = ?
              AND person_contacts.status = 'active'
            ORDER BY person_contacts.is_primary DESC, person_contacts.id ASC
        ");
        $statement->execute([$personId]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $value = trim((string) ($row['value'] ?? ''));
            $code = strtolower(trim((string) ($row['type_code'] ?? '')));
            $title = trim((string) ($row['type_title'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));

            if (
                str_contains($value, '@')
                || str_contains($code, 'email')
                || str_contains($title, 'ایمیل')
            ) {
                $result['contact_email_label'] = $label !== '' ? $label : 'ایمیل اصلی';
                continue;
            }

            if (
                preg_match('/^09\d{9}$/', preg_replace('/\D+/', '', $value) ?? '') === 1
                || str_contains($code, 'mobile')
                || str_contains($title, 'موبایل')
            ) {
                $result['contact_mobile_label'] = $label !== '' ? $label : 'موبایل اصلی';
            }
        }

        return $result;
    }

    private function addressFormData(int $personId): array
    {
        $result = [
            'address_type_id' => 0,
            'district' => '',
            'address_line' => '',
            'postal_code' => '',
        ];

        if ($personId < 1 || !Database::tableExists('person_addresses')) {
            return $result;
        }

        $statement = $this->connection()->prepare("
            SELECT
                address_type_id,
                province_id,
                city_id,
                district,
                address_line,
                postal_code
            FROM person_addresses
            WHERE person_id = ?
              AND status = 'active'
            ORDER BY is_primary DESC, id ASC
            LIMIT 1
        ");
        $statement->execute([$personId]);
        $address = $statement->fetch(PDO::FETCH_ASSOC);

        return $address ? array_merge($result, $address) : $result;
    }

    private function syncPrimaryContacts(int $personId, array $data): void
    {
        if (!Database::tableExists('person_contacts')) {
            return;
        }

        $this->syncPrimaryContact(
            $personId,
            'email',
            (string) ($data['email'] ?? ''),
            (string) ($data['contact_email_label'] ?? 'ایمیل اصلی'),
            (bool) ($data['email_verified'] ?? false)
        );
        $this->syncPrimaryContact(
            $personId,
            'mobile',
            (string) ($data['mobile'] ?? ''),
            (string) ($data['contact_mobile_label'] ?? 'موبایل اصلی'),
            (bool) ($data['mobile_verified'] ?? false)
        );
    }

    private function syncPrimaryContact(
        int $personId,
        string $type,
        string $value,
        string $label,
        bool $verified
    ): void {
        $typeId = $this->contactTypeId($type);
        if ($typeId === null) {
            return;
        }

        $existing = $this->connection()->prepare("
            SELECT id
            FROM person_contacts
            WHERE person_id = ?
              AND contact_type_id = ?
            ORDER BY is_primary DESC, id ASC
            LIMIT 1
        ");
        $existing->execute([$personId, $typeId]);
        $contactId = $existing->fetchColumn();

        if (trim($value) === '') {
            if ($contactId !== false) {
                $statement = $this->connection()->prepare("
                    UPDATE person_contacts
                    SET is_primary = 0,
                        status = 'inactive',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $statement->execute([(int) $contactId]);
            }
            return;
        }

        if ($contactId !== false) {
            $statement = $this->connection()->prepare("
                UPDATE person_contacts
                SET value = ?,
                    label = ?,
                    is_primary = 1,
                    is_verified = ?,
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute([
                $value,
                $label,
                $verified ? 1 : 0,
                (int) $contactId,
            ]);
            return;
        }

        $statement = $this->connection()->prepare("
            INSERT INTO person_contacts (
                person_id,
                contact_type_id,
                value,
                label,
                is_primary,
                is_verified,
                status,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, 1, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $statement->execute([
            $personId,
            $typeId,
            $value,
            $label,
            $verified ? 1 : 0,
        ]);
    }

    private function contactTypeId(string $type): ?int
    {
        if (!Database::tableExists('contact_types')) {
            return null;
        }

        $codes = $type === 'email'
            ? ['email', 'mail']
            : ['mobile', 'cellphone', 'phone'];

        if (Database::columnExists('contact_types', 'code')) {
            $placeholders = implode(',', array_fill(0, count($codes), '?'));
            $statement = $this->connection()->prepare("
                SELECT id
                FROM contact_types
                WHERE LOWER(code) IN ({$placeholders})
                ORDER BY id ASC
                LIMIT 1
            ");
            $statement->execute($codes);
            $id = $statement->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        $titleColumn = $this->titleColumn('contact_types');
        if ($titleColumn !== null) {
            $needle = $type === 'email' ? '%ایمیل%' : '%موبایل%';
            $statement = $this->connection()->prepare(
                "SELECT id FROM contact_types WHERE {$titleColumn} LIKE ? ORDER BY id ASC LIMIT 1"
            );
            $statement->execute([$needle]);
            $id = $statement->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
        }

        return null;
    }

    private function syncPrimaryAddress(int $personId, array $data): void
    {
        if (!Database::tableExists('person_addresses')) {
            return;
        }

        $addressTypeId = (int) ($data['address_type_id'] ?? 0);
        $hasAddress = (int) ($data['province_id'] ?? 0) > 0
            || (int) ($data['city_id'] ?? 0) > 0
            || trim((string) ($data['district'] ?? '')) !== ''
            || trim((string) ($data['address_line'] ?? '')) !== ''
            || trim((string) ($data['postal_code'] ?? '')) !== '';

        if (!$hasAddress) {
            return;
        }

        if ($addressTypeId < 1) {
            $options = $this->idOptions('address_types');
            $addressTypeId = (int) ($options[0]['id'] ?? 0);
        }

        $existing = $this->connection()->prepare("
            SELECT id
            FROM person_addresses
            WHERE person_id = ?
            ORDER BY is_primary DESC, id ASC
            LIMIT 1
        ");
        $existing->execute([$personId]);
        $addressId = $existing->fetchColumn();

        $values = [
            $addressTypeId > 0 ? $addressTypeId : null,
            (int) ($data['province_id'] ?? 0) > 0 ? (int) $data['province_id'] : null,
            (int) ($data['city_id'] ?? 0) > 0 ? (int) $data['city_id'] : null,
            $data['district'] !== '' ? $data['district'] : null,
            (string) ($data['address_line'] ?? ''),
            $data['postal_code'] !== '' ? $data['postal_code'] : null,
        ];

        if ($addressId !== false) {
            $statement = $this->connection()->prepare("
                UPDATE person_addresses
                SET address_type_id = ?,
                    province_id = ?,
                    city_id = ?,
                    district = ?,
                    address_line = ?,
                    postal_code = ?,
                    is_primary = 1,
                    status = 'active',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $statement->execute(array_merge($values, [(int) $addressId]));
            return;
        }

        $statement = $this->connection()->prepare("
            INSERT INTO person_addresses (
                person_id,
                address_type_id,
                province_id,
                city_id,
                district,
                address_line,
                postal_code,
                is_primary,
                status,
                created_at,
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $statement->execute(array_merge([$personId], $values));
    }

    private function globalRoleIdsForUser(int $userId): array
    {
        $statement = $this->connection()->prepare("
            SELECT assignments.role_id
            FROM user_role_assignments assignments
            INNER JOIN roles ON roles.id = assignments.role_id
            WHERE assignments.user_id = ?
              AND assignments.scope_type = 'global'
              AND assignments.scope_id IS NULL
              AND assignments.is_active = 1
              AND roles.is_active = 1
            ORDER BY roles.priority ASC, roles.id ASC
        ");
        $statement->execute([$userId]);

        return array_map(
            'intval',
            $statement->fetchAll(PDO::FETCH_COLUMN) ?: []
        );
    }

    private function syncGlobalRoles(int $userId, array $roleIds): void
    {
        $baseRoleId = $this->roleIdByCode('user');
        if ($baseRoleId === null) {
            throw new RuntimeException('base_user_role_missing');
        }

        $roleIds[] = $baseRoleId;
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', $roleIds),
            static fn (int $id): bool => $id > 0
        )));

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $deactivate = $this->connection()->prepare("
            UPDATE user_role_assignments
            SET is_active = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
              AND scope_type = 'global'
              AND scope_id IS NULL
              AND role_id NOT IN ({$placeholders})
        ");
        $deactivate->execute(array_merge([$userId], $roleIds));

        foreach ($roleIds as $roleId) {
            $existing = $this->connection()->prepare("
                SELECT id
                FROM user_role_assignments
                WHERE user_id = ?
                  AND role_id = ?
                  AND scope_type = 'global'
                  AND scope_id IS NULL
                LIMIT 1
            ");
            $existing->execute([$userId, $roleId]);
            $assignmentId = $existing->fetchColumn();

            if ($assignmentId !== false) {
                $update = $this->connection()->prepare("
                    UPDATE user_role_assignments
                    SET include_children = 0,
                        is_active = 1,
                        starts_at = NULL,
                        ends_at = NULL,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $update->execute([(int) $assignmentId]);
                continue;
            }

            $insert = $this->connection()->prepare("
                INSERT INTO user_role_assignments (
                    user_id,
                    role_id,
                    scope_type,
                    scope_id,
                    include_children,
                    starts_at,
                    ends_at,
                    is_active,
                    created_at,
                    updated_at
                )
                VALUES (
                    ?, ?,
                    'global',
                    NULL,
                    0,
                    NULL,
                    NULL,
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
            ");
            $insert->execute([$userId, $roleId]);
        }
    }

    private function codeOptions(string $table, array $fallback): array
    {
        if (!Database::tableExists($table) || !Database::columnExists($table, 'code')) {
            return $fallback;
        }

        $title = $this->titleColumn($table);
        if ($title === null) {
            return $fallback;
        }

        $where = $this->activeWhere($table);
        $order = Database::columnExists($table, 'sort_order')
            ? 'sort_order ASC, '
            : '';
        $statement = $this->connection()->query("
            SELECT code, {$title} AS title
            FROM {$table}
            {$where}
            ORDER BY {$order}{$title} ASC
        ");

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: $fallback;
    }

    private function idOptions(string $table, array $extras = []): array
    {
        if (!Database::tableExists($table) || !Database::columnExists($table, 'id')) {
            return [];
        }

        $title = $this->titleColumn($table);
        if ($title === null) {
            return [];
        }

        $select = ['id', "{$title} AS title"];
        foreach ($extras as $extra) {
            if (Database::columnExists($table, $extra)) {
                $select[] = $extra;
            }
        }

        $where = $this->activeWhere($table);
        $order = Database::columnExists($table, 'sort_order')
            ? 'sort_order ASC, '
            : '';
        $statement = $this->connection()->query(
            'SELECT ' . implode(', ', $select)
            . " FROM {$table} {$where} ORDER BY {$order}{$title} ASC"
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function countyOptions(): array
    {
        $table = $this->countyTable();
        return $table === null ? [] : $this->idOptions($table, ['province_id']);
    }

    private function countyTable(): ?string
    {
        foreach (['counties', 'shahrestans'] as $table) {
            if (Database::tableExists($table)) {
                return $table;
            }
        }

        return null;
    }

    private function validLookupId(string $table, int $id): bool
    {
        if ($id < 1 || !Database::tableExists($table)) {
            return false;
        }

        $statement = $this->connection()->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id = ?"
        );
        $statement->execute([$id]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function titleColumn(string $table): ?string
    {
        if (!Database::tableExists($table)) {
            return null;
        }

        foreach (['title', 'name', 'label'] as $column) {
            if (Database::columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function activeWhere(string $table): string
    {
        if (Database::columnExists($table, 'is_active')) {
            return 'WHERE is_active = 1';
        }
        if (Database::columnExists($table, 'status')) {
            return "WHERE status = 'active'";
        }

        return '';
    }

    private function optionTitle(array $options, int $id, string $fallback = '—'): string
    {
        foreach ($options as $option) {
            if ((int) ($option['id'] ?? 0) === $id) {
                return (string) ($option['title'] ?? $fallback);
            }
        }

        return $fallback;
    }
}
