<?php

namespace App\Repositories;

use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

class AdminUserManagementRepository extends BaseRepository
{
    private ?array $dynamicGeographyCache = null;

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

        $personId = (int) ($user['person_id'] ?? 0);
        $addressRecords = $this->addressRecordsForPerson(
            $personId
        );

        $user['role_ids'] = $this->globalRoleIdsForUser(
            $userId
        );
        $user = array_merge(
            $user,
            $this->contactFormData($personId),
            $this->addressFormDataFromRecords(
                $addressRecords
            )
        );
        $user['address_records'] = $addressRecords;

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
                roles.priority ASC,
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
        $geography = $this->dynamicGeographyOptions();

        if ($geography['provinces'] === []) {
            $geography = [
                'source' => 'legacy',
                'provinces' => $this->idOptions('provinces'),
                'counties' => $this->countyOptions(),
                'cities' => $this->idOptions(
                    'cities',
                    ['province_id', 'county_id']
                ),
            ];
        }

        return [
            'person_types' => $this->codeOptions(
                'person_types',
                [
                    [
                        'code' => 'individual',
                        'title' => 'شخص حقیقی',
                    ],
                    [
                        'code' => 'legal',
                        'title' => 'شخص حقوقی',
                    ],
                ]
            ),
            'geography_source' => $geography['source'],
            'provinces' => $geography['provinces'],
            'counties' => $geography['counties'],
            'cities' => $geography['cities'],
            'address_types' =>
                $this->idOptions('address_types'),
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

    public function validGeographicLocationId(
        int $id,
        string $levelCode
    ): bool {
        if ($id < 1) {
            return false;
        }

        if (
            Database::tableExists('geographic_locations')
            && Database::tableExists(
                'geographic_level_types'
            )
        ) {
            $statement = $this->connection()->prepare("
                SELECT COUNT(*)
                FROM geographic_locations AS locations
                INNER JOIN geographic_level_types AS levels
                  ON levels.id = locations.level_type_id
                WHERE locations.id = ?
                  AND locations.status = 'active'
                  AND levels.status = 'active'
                  AND levels.code = ?
            ");
            $statement->execute([$id, $levelCode]);

            return (int) $statement->fetchColumn() > 0;
        }

        return match ($levelCode) {
            'province' => $this->validProvinceId($id),
            'county' => $this->validCountyId($id),
            'city' => $this->validCityId($id),
            default => false,
        };
    }

    public function validGeographicSelection(
        int $provinceId,
        int $countyId,
        int $cityId
    ): bool {
        if (
            !Database::tableExists('geographic_locations')
            || !Database::tableExists(
                'geographic_level_types'
            )
        ) {
            return ($provinceId < 1
                    || $this->validProvinceId($provinceId))
                && ($countyId < 1
                    || $this->validCountyId($countyId))
                && ($cityId < 1
                    || $this->validCityId($cityId));
        }

        if (
            $provinceId > 0
            && !$this->validGeographicLocationId(
                $provinceId,
                'province'
            )
        ) {
            return false;
        }

        if (
            $countyId > 0
            && !$this->validGeographicLocationId(
                $countyId,
                'county'
            )
        ) {
            return false;
        }

        if (
            $cityId > 0
            && !$this->validGeographicLocationId(
                $cityId,
                'city'
            )
        ) {
            return false;
        }

        $targetId = $cityId > 0
            ? $cityId
            : ($countyId > 0 ? $countyId : $provinceId);

        if ($targetId < 1) {
            return true;
        }

        $selection = $this->geographicSelection(
            $targetId
        );

        if ($selection === null) {
            return false;
        }

        return ($provinceId < 1
                || $selection['province_location_id']
                    === $provinceId)
            && ($countyId < 1
                || $selection['county_location_id']
                    === $countyId)
            && ($cityId < 1
                || $selection['city_location_id']
                    === $cityId);
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

    public function updateRoles(
        int $userId,
        array $roleIds,
        bool $preserveSuperAdmin
    ): bool {
        $db = $this->connection();
        $db->beginTransaction();

        try {
            if ($this->findForForm($userId) === null) {
                throw new RuntimeException('user_not_found');
            }

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
        $addressPresent =
            trim((string) (
                $form['address_line'] ?? ''
            )) !== ''
            || (int) (
                $form['province_location_id'] ?? 0
            ) > 0
            || (int) (
                $form['county_location_id'] ?? 0
            ) > 0
            || (int) (
                $form['city_location_id'] ?? 0
            ) > 0
            || trim((string) (
                $form['district'] ?? ''
            )) !== ''
            || trim((string) (
                $form['postal_code'] ?? ''
            )) !== '';

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
                    (int) (
                        $form['province_location_id'] ?? 0
                    )
                ),
                'city' => $this->optionTitle(
                    $options['cities'],
                    (int) (
                        $form['city_location_id'] ?? 0
                    )
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

    private function emptyAddressFormData(): array
    {
        return [
            'address_type_id' => 0,
            'province_location_id' => 0,
            'county_location_id' => 0,
            'city_location_id' => 0,
            'geographic_location_id' => 0,
            'district' => '',
            'address_line' => '',
            'postal_code' => '',
        ];
    }

    private function addressFormDataFromRecords(
        array $records
    ): array {
        $first = $records[0] ?? null;

        return is_array($first)
            ? array_merge(
                $this->emptyAddressFormData(),
                $first
            )
            : $this->emptyAddressFormData();
    }

    private function addressRecordsForPerson(
        int $personId
    ): array {
        if (
            $personId < 1
            || !Database::tableExists(
                'person_addresses'
            )
        ) {
            return [];
        }

        $select = [
            'id',
            'address_type_id',
            'is_primary',
            'district',
            'address_line',
            'postal_code',
        ];

        foreach ([
            'province_id',
            'city_id',
            'geographic_location_id',
        ] as $column) {
            $select[] = Database::columnExists(
                'person_addresses',
                $column
            )
                ? $column
                : "NULL AS {$column}";
        }

        $statement = $this->connection()->prepare(
            'SELECT '
            . implode(', ', $select)
            . "
              FROM person_addresses
              WHERE person_id = ?
                AND status = 'active'
              ORDER BY is_primary DESC, id ASC"
        );
        $statement->execute([$personId]);

        $records = [];

        foreach (
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
            as $address
        ) {
            $record = array_merge(
                $this->emptyAddressFormData(),
                [
                    'id' => (int) (
                        $address['id'] ?? 0
                    ),
                    'address_type_id' => (int) (
                        $address['address_type_id'] ?? 0
                    ),
                    'is_primary' => !empty(
                        $address['is_primary']
                    ),
                    'geographic_location_id' => (int) (
                        $address[
                            'geographic_location_id'
                        ] ?? 0
                    ),
                    'district' => (string) (
                        $address['district'] ?? ''
                    ),
                    'address_line' => (string) (
                        $address['address_line'] ?? ''
                    ),
                    'postal_code' => (string) (
                        $address['postal_code'] ?? ''
                    ),
                ]
            );

            $geographicLocationId = (int) (
                $address[
                    'geographic_location_id'
                ] ?? 0
            );

            if ($geographicLocationId > 0) {
                $selection =
                    $this->geographicSelection(
                        $geographicLocationId
                    );

                if ($selection !== null) {
                    $record = array_merge(
                        $record,
                        $selection
                    );
                }
            } else {
                $record['province_location_id'] =
                    (int) (
                        $address['province_id'] ?? 0
                    );
                $record['city_location_id'] =
                    (int) (
                        $address['city_id'] ?? 0
                    );
            }

            $records[] = $record;
        }

        return $records;
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

    private function syncPrimaryAddress(
        int $personId,
        array $data
    ): void {
        if (!Database::tableExists('person_addresses')) {
            return;
        }

        $provinceLocationId = (int) (
            $data['province_location_id'] ?? 0
        );
        $countyLocationId = (int) (
            $data['county_location_id'] ?? 0
        );
        $cityLocationId = (int) (
            $data['city_location_id'] ?? 0
        );
        $geographicLocationId = $cityLocationId > 0
            ? $cityLocationId
            : (
                $countyLocationId > 0
                    ? $countyLocationId
                    : $provinceLocationId
            );
        $addressTypeId = (int) (
            $data['address_type_id'] ?? 0
        );
        $district = trim((string) (
            $data['district'] ?? ''
        ));
        $addressLine = trim((string) (
            $data['address_line'] ?? ''
        ));
        $postalCode = trim((string) (
            $data['postal_code'] ?? ''
        ));

        $hasAddress = $geographicLocationId > 0
            || $district !== ''
            || $addressLine !== ''
            || $postalCode !== '';

        if ($addressTypeId < 1 && $hasAddress) {
            $addressTypes = $this->idOptions(
                'address_types'
            );
            $addressTypeId = (int) (
                $addressTypes[0]['id'] ?? 0
            );
        }

        if ($addressTypeId < 1) {
            return;
        }

        $existing = $this->connection()->prepare("
            SELECT id
            FROM person_addresses
            WHERE person_id = ?
              AND address_type_id = ?
            ORDER BY is_primary DESC, id ASC
            LIMIT 1
        ");
        $existing->execute([
            $personId,
            $addressTypeId,
        ]);
        $addressId = $existing->fetchColumn();

        if (!$hasAddress) {
            $this->connection()->prepare("
            UPDATE person_addresses
            SET is_primary = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE person_id = ?
              AND is_primary = 1
        ")->execute([$personId]);

        if ($addressId !== false) {
                $this->connection()->prepare("
                    UPDATE person_addresses
                    SET is_primary = 0,
                        status = 'inactive',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ")->execute([(int) $addressId]);
            }

            return;
        }

        $fields = [
            'address_type_id' =>
                $addressTypeId > 0
                    ? $addressTypeId
                    : null,
            'district' =>
                $district !== '' ? $district : null,
            'address_line' => $addressLine,
            'postal_code' =>
                $postalCode !== '' ? $postalCode : null,
            'is_primary' => 1,
            'status' => 'active',
        ];

        if (Database::columnExists(
            'person_addresses',
            'geographic_location_id'
        )) {
            $fields['geographic_location_id'] =
                $geographicLocationId > 0
                    ? $geographicLocationId
                    : null;
        }

        if ($addressId !== false) {
            $set = [];
            $values = [];

            foreach ($fields as $column => $value) {
                $set[] = "{$column} = ?";
                $values[] = $value;
            }

            $set[] = 'updated_at = CURRENT_TIMESTAMP';
            $values[] = (int) $addressId;

            $statement = $this->connection()->prepare(
                'UPDATE person_addresses SET '
                . implode(', ', $set)
                . ' WHERE id = ?'
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

        $columns[] = 'created_at';
        $columns[] = 'updated_at';
        $placeholders[] = 'CURRENT_TIMESTAMP';
        $placeholders[] = 'CURRENT_TIMESTAMP';

        $statement = $this->connection()->prepare(
            'INSERT INTO person_addresses ('
            . implode(', ', $columns)
            . ') VALUES ('
            . implode(', ', $placeholders)
            . ')'
        );
        $statement->execute($values);
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

    private function dynamicGeographyOptions(): array
    {
        $graph = $this->dynamicGeographyGraph();

        if ($graph['nodes'] === []) {
            return [
                'source' => 'dynamic',
                'provinces' => [],
                'counties' => [],
                'cities' => [],
            ];
        }

        $provinces = [];
        $counties = [];
        $cities = [];

        foreach ($graph['nodes'] as $node) {
            $id = (int) $node['id'];
            $title = (string) $node['title'];
            $level = (string) $node['level_code'];

            if ($level === 'province') {
                $provinces[] = [
                    'id' => $id,
                    'title' => $title,
                ];
                continue;
            }

            $selection = $this->geographicSelection(
                $id
            );

            if ($selection === null) {
                continue;
            }

            if ($level === 'county') {
                $counties[] = [
                    'id' => $id,
                    'title' => $title,
                    'province_location_id' =>
                        $selection[
                            'province_location_id'
                        ],
                ];
                continue;
            }

            if ($level === 'city') {
                $cities[] = [
                    'id' => $id,
                    'title' => $title,
                    'province_location_id' =>
                        $selection[
                            'province_location_id'
                        ],
                    'county_location_id' =>
                        $selection[
                            'county_location_id'
                        ],
                ];
            }
        }

        $sort = static function (
            array &$items
        ): void {
            usort(
                $items,
                static fn (
                    array $left,
                    array $right
                ): int => strnatcasecmp(
                    (string) $left['title'],
                    (string) $right['title']
                )
            );
        };

        $sort($provinces);
        $sort($counties);
        $sort($cities);

        return [
            'source' => 'dynamic',
            'provinces' => $provinces,
            'counties' => $counties,
            'cities' => $cities,
        ];
    }

    private function geographicSelection(
        int $locationId
    ): ?array {
        if ($locationId < 1) {
            return null;
        }

        $graph = $this->dynamicGeographyGraph();
        $nodes = $graph['nodes'];
        $parents = $graph['parents'];

        if (!isset($nodes[$locationId])) {
            return null;
        }

        $selection = [
            'province_location_id' => 0,
            'county_location_id' => 0,
            'city_location_id' => 0,
            'geographic_location_id' => $locationId,
        ];
        $currentId = $locationId;
        $visited = [];

        for ($depth = 0; $depth < 12; $depth++) {
            if (
                isset($visited[$currentId])
                || !isset($nodes[$currentId])
            ) {
                break;
            }

            $visited[$currentId] = true;
            $level = (string) (
                $nodes[$currentId]['level_code'] ?? ''
            );

            if ($level === 'province') {
                $selection['province_location_id'] =
                    $currentId;
            } elseif ($level === 'county') {
                $selection['county_location_id'] =
                    $currentId;
            } elseif ($level === 'city') {
                $selection['city_location_id'] =
                    $currentId;
            }

            if (!isset($parents[$currentId])) {
                break;
            }

            $currentId = (int) $parents[$currentId];
        }

        return $selection;
    }

    private function dynamicGeographyGraph(): array
    {
        if ($this->dynamicGeographyCache !== null) {
            return $this->dynamicGeographyCache;
        }

        $empty = [
            'nodes' => [],
            'parents' => [],
        ];

        if (
            !Database::tableExists(
                'geographic_locations'
            )
            || !Database::tableExists(
                'geographic_level_types'
            )
            || !Database::tableExists(
                'geographic_location_relations'
            )
            || !Database::tableExists(
                'geographic_relation_types'
            )
        ) {
            return $this->dynamicGeographyCache =
                $empty;
        }

        $statement = $this->connection()->query("
            SELECT
                locations.id,
                locations.title,
                levels.code AS level_code
            FROM geographic_locations AS locations
            INNER JOIN geographic_level_types AS levels
              ON levels.id = locations.level_type_id
            WHERE locations.status = 'active'
              AND levels.status = 'active'
              AND levels.code IN (
                  'province',
                  'county',
                  'district',
                  'city'
              )
            ORDER BY
                levels.hierarchy_order ASC,
                locations.title ASC,
                locations.id ASC
        ");

        $nodes = [];

        foreach (
            $statement->fetchAll(PDO::FETCH_ASSOC) ?: []
            as $row
        ) {
            $nodes[(int) $row['id']] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'level_code' =>
                    (string) $row['level_code'],
            ];
        }

        if ($nodes === []) {
            return $this->dynamicGeographyCache =
                $empty;
        }

        $relations = $this->connection()->query("
            SELECT
                relations.parent_location_id,
                relations.child_location_id,
                relations.is_primary
            FROM geographic_location_relations AS relations
            INNER JOIN geographic_relation_types AS relation_types
              ON relation_types.id =
                 relations.relation_type_id
            WHERE relations.status = 'active'
              AND relations.is_primary = 1
              AND relation_types.status = 'active'
              AND relation_types.code =
                  'administrative_parent'
            ORDER BY relations.id ASC
        ");
        $parents = [];

        foreach (
            $relations->fetchAll(PDO::FETCH_ASSOC)
                ?: []
            as $relation
        ) {
            $childId = (int) (
                $relation['child_location_id'] ?? 0
            );
            $parentId = (int) (
                $relation['parent_location_id'] ?? 0
            );

            if (
                $childId < 1
                || $parentId < 1
                || !isset($nodes[$childId])
                || !isset($nodes[$parentId])
                || isset($parents[$childId])
            ) {
                continue;
            }

            $parents[$childId] = $parentId;
        }

        return $this->dynamicGeographyCache = [
            'nodes' => $nodes,
            'parents' => $parents,
        ];
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
