<?php

namespace IPKF\Database\Seeds;

class MultiSourceMetadataSeeder extends Seeder
{
    public function run(): void
    {
        if (!$this->tableExists('data_sources')
            || !$this->tableExists('data_source_authority_scopes')
            || !$this->tableExists('external_coding_systems')
            || !$this->tableExists('external_code_sets')
            || !$this->tableExists('external_code_segments')
            || !$this->tableExists('geographic_hierarchy_types')
            || !$this->tableExists('geographic_level_types')
        ) {
            return;
        }

        $this->seedDataSources();
        $this->seedAuthorityScopes();
        $this->seedCodingSystems();
        $this->seedCodeSets();
        $this->seedCodeSegments();
        $this->seedHierarchyTypes();
        $this->seedOperationalRegionLevel();

        if ($this->tableExists('geographic_source_level_mappings')
            && $this->tableExists('data_source_import_settings')
        ) {
            $this->seedMinistryGeographyLevelMappings();
            $this->seedMinistryImportSettings();
        }
    }

    private function seedDataSources(): void
    {
        $sources = [
            [
                'iran_ministry_of_interior',
                'Iran Ministry of Interior',
                'Ministry of Interior',
                'official_administrative',
                'IRN',
                'Authoritative source for official Iranian administrative divisions and parent relationships.',
                10,
                1,
            ],
            [
                'iran_statistical_center',
                'Statistical Center of Iran',
                'Statistical Center of Iran',
                'statistical',
                'IRN',
                'Supplementary source for statistical geography, settlements, census identifiers and statistical points.',
                20,
                0,
            ],
            [
                'rural_cooperation_statistical_system',
                'Rural Cooperation Statistical System',
                'Rural Cooperation Organization',
                'operational_registry',
                'IRN',
                'Authoritative source for its operational geography, network classifications and unchanged integration codes.',
                10,
                1,
            ],
        ];

        $statement = $this->db->prepare("
            INSERT INTO data_sources (
                code, title, authority_name, source_kind, country_iso_code,
                description, default_priority, is_authoritative, is_system,
                status, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                authority_name = VALUES(authority_name),
                source_kind = VALUES(source_kind),
                country_iso_code = VALUES(country_iso_code),
                description = VALUES(description),
                default_priority = VALUES(default_priority),
                is_authoritative = VALUES(is_authoritative),
                is_system = 1,
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($sources as $source) {
            $statement->execute($source);
        }
    }

    private function seedAuthorityScopes(): void
    {
        $scopes = [
            ['iran_ministry_of_interior', 'official_administrative_hierarchy', 'Official administrative hierarchy', 10, 1, 'source_wins'],
            ['iran_ministry_of_interior', 'official_location_status', 'Official location status', 10, 1, 'source_wins'],
            ['iran_ministry_of_interior', 'official_parent_relationship', 'Official parent relationship', 10, 1, 'source_wins'],
            ['iran_statistical_center', 'statistical_geography', 'Statistical geography', 10, 1, 'supplement'],
            ['iran_statistical_center', 'village_and_settlement_data', 'Village and settlement data', 10, 1, 'supplement'],
            ['iran_statistical_center', 'census_identifiers', 'Census identifiers', 10, 1, 'preserve_all'],
            ['rural_cooperation_statistical_system', 'rural_cooperation_operational_geography', 'Rural Cooperation operational geography', 10, 1, 'separate_hierarchy'],
            ['rural_cooperation_statistical_system', 'rural_cooperation_organization_codes', 'Rural Cooperation organization codes', 10, 1, 'preserve_exact'],
            ['rural_cooperation_statistical_system', 'rural_cooperation_classification_codes', 'Rural Cooperation classification codes', 10, 1, 'preserve_exact'],
        ];

        $statement = $this->db->prepare("
            INSERT INTO data_source_authority_scopes (
                source_id, domain_code, title, priority, is_authoritative,
                conflict_policy, description, status, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                priority = VALUES(priority),
                is_authoritative = VALUES(is_authoritative),
                conflict_policy = VALUES(conflict_policy),
                description = VALUES(description),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($scopes as [$sourceCode, $domainCode, $title, $priority, $authoritative, $policy]) {
            $sourceId = $this->sourceId($sourceCode);

            if ($sourceId === null) {
                continue;
            }

            $statement->execute([
                $sourceId,
                $domainCode,
                $title,
                $priority,
                $authoritative,
                $policy,
                'Authority is evaluated for this domain only; disagreements create review work instead of silent overwrite.',
            ]);
        }
    }

    private function seedCodingSystems(): void
    {
        $systems = [
            ['iran_ministry_of_interior', 'iran_moi_administrative', 'Ministry administrative coding', 'Official administrative location codes and identifiers.'],
            ['iran_statistical_center', 'iran_sci_statistical_geography', 'SCI statistical geography coding', 'Statistical and census geography codes.'],
            ['rural_cooperation_statistical_system', 'rural_cooperation_operational', 'Rural Cooperation statistical-system coding', 'Operational geography, organization and classification codes used by the active integration.'],
        ];

        $statement = $this->db->prepare("
            INSERT INTO external_coding_systems (
                source_id, code, title, description, is_versioned, is_system,
                status, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, 1, 1, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                source_id = VALUES(source_id),
                title = VALUES(title),
                description = VALUES(description),
                is_versioned = 1,
                is_system = 1,
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($systems as [$sourceCode, $code, $title, $description]) {
            $sourceId = $this->sourceId($sourceCode);

            if ($sourceId !== null) {
                $statement->execute([$sourceId, $code, $title, $description]);
            }
        }
    }

    private function seedCodeSets(): void
    {
        $sets = [
            ['iran_moi_administrative', 'administrative_location_code', 'Administrative location code', 'geography', null, null, 10],
            ['iran_moi_administrative', 'national_location_identifier', 'National location identifier', 'geography', null, null, 20],
            ['iran_sci_statistical_geography', 'statistical_geography_code', 'Statistical geography code', 'geography', null, null, 10],
            ['iran_sci_statistical_geography', 'census_identifier', 'Census identifier', 'geography', null, null, 20],
            ['rural_cooperation_operational', 'province_code', 'Province code', 'geographic_scope', 3, null, 10],
            ['rural_cooperation_operational', 'county_code', 'County code', 'geographic_scope', 5, 'province_code', 20],
            ['rural_cooperation_operational', 'organization_code', 'Organization code', 'organization', 8, 'county_code', 30],
            ['rural_cooperation_operational', 'geographic_level', 'Geographic level', 'geography', null, null, 40],
            ['rural_cooperation_operational', 'organization_level', 'Organization level', 'organization_classification', null, null, 50],
            ['rural_cooperation_operational', 'organization_kind', 'Organization kind', 'organization_classification', null, null, 60],
            ['rural_cooperation_operational', 'organization_type', 'Organization type', 'organization_classification', null, null, 70],
            ['rural_cooperation_operational', 'organization_subtype', 'Organization subtype', 'organization_classification', null, null, 80],
        ];

        $statement = $this->db->prepare("
            INSERT INTO external_code_sets (
                coding_system_id, code, title, entity_domain, expected_length,
                parent_code_set_id, description, sort_order, status, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                entity_domain = VALUES(entity_domain),
                expected_length = VALUES(expected_length),
                parent_code_set_id = VALUES(parent_code_set_id),
                description = VALUES(description),
                sort_order = VALUES(sort_order),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($sets as [$systemCode, $code, $title, $domain, $length, $parentCode, $sortOrder]) {
            $systemId = $this->codingSystemId($systemCode);

            if ($systemId === null) {
                continue;
            }

            $parentId = $parentCode === null ? null : $this->codeSetId($systemCode, $parentCode);
            $statement->execute([
                $systemId,
                $code,
                $title,
                $domain,
                $length,
                $parentId,
                'External codes remain strings and preserve leading zeroes; they are not canonical primary keys.',
                $sortOrder,
            ]);
        }
    }

    private function seedCodeSegments(): void
    {
        $segments = [
            ['province_code', 'province_code', 'Full province code', 1, 3, null, 10],
            ['county_code', 'province_code', 'Province-code segment', 1, 3, 'province_code', 10],
            ['county_code', 'county_sequence', 'County sequence segment', 4, 2, null, 20],
            ['organization_code', 'county_code', 'County-code segment', 1, 5, 'county_code', 10],
            ['organization_code', 'organization_sequence', 'Organization sequence segment', 6, 3, null, 20],
        ];

        $statement = $this->db->prepare("
            INSERT INTO external_code_segments (
                code_set_id, segment_code, title, start_position, segment_length,
                referenced_code_set_id, description, sort_order, status, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                start_position = VALUES(start_position),
                segment_length = VALUES(segment_length),
                referenced_code_set_id = VALUES(referenced_code_set_id),
                description = VALUES(description),
                sort_order = VALUES(sort_order),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($segments as [$setCode, $segmentCode, $title, $start, $length, $referenceCode, $sortOrder]) {
            $setId = $this->codeSetId('rural_cooperation_operational', $setCode);
            $referenceId = $referenceCode === null
                ? null
                : $this->codeSetId('rural_cooperation_operational', $referenceCode);

            if ($setId === null) {
                continue;
            }

            $statement->execute([
                $setId,
                $segmentCode,
                $title,
                $start,
                $length,
                $referenceId,
                'Positions are one-based and inclusive. Parsing must never cast the source code to an integer.',
                $sortOrder,
            ]);
        }
    }

    private function seedHierarchyTypes(): void
    {
        $types = [
            ['official_administrative', 'Official administrative hierarchy', 'Canonical official hierarchy governed by the relevant administrative authority.', 1, 10],
            ['statistical', 'Statistical hierarchy', 'Supplementary census and statistical hierarchy that does not override official parents.', 0, 20],
            ['rural_cooperation_operational', 'Rural Cooperation operational hierarchy', 'Operational network geography kept separate from official administrative geography.', 0, 30],
            ['custom', 'Custom hierarchy', 'Deployment-defined hierarchy with explicit provenance and review rules.', 0, 100],
        ];

        $statement = $this->db->prepare("
            INSERT INTO geographic_hierarchy_types (
                code, title, description, is_authoritative, supports_history,
                sort_order, status, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, 1, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                is_authoritative = VALUES(is_authoritative),
                supports_history = 1,
                sort_order = VALUES(sort_order),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($types as $type) {
            $statement->execute($type);
        }
    }

    private function seedOperationalRegionLevel(): void
    {
        $this->db->prepare("
            INSERT INTO geographic_level_types (
                code, title, description, hierarchy_order, is_administrative,
                is_addressable, is_selectable, is_system, sort_order, status,
                created_at, updated_at
            )
            VALUES (
                'operational_region', 'منطقه عملیاتی',
                'Internal network region; it is not an official administrative province.',
                NULL, 0, 0, 1, 1, 900, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
            ON DUPLICATE KEY UPDATE code = code
        ")->execute();
    }

    private function seedMinistryGeographyLevelMappings(): void
    {
        $sourceId = $this->sourceId('iran_ministry_of_interior');

        if ($sourceId === null) {
            return;
        }

        $mappings = [
            ['استان', 'province', null, 2, null, 10],
            ['شهرستان', 'county', 'province', 4, 2, 20],
            ['بخش', 'district', 'county', 6, 4, 30],
            ['دهستان', 'rural_district', 'district', 8, 6, 40],
            ['شهر', 'city', 'district', 9, 6, 50],
        ];
        $statement = $this->db->prepare("
            INSERT INTO geographic_source_level_mappings (
                source_id, source_type_value, geographic_level_code,
                parent_geographic_level_code, expected_code_length,
                parent_prefix_length, sort_order, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                geographic_level_code = VALUES(geographic_level_code),
                parent_geographic_level_code = VALUES(parent_geographic_level_code),
                expected_code_length = VALUES(expected_code_length),
                parent_prefix_length = VALUES(parent_prefix_length),
                sort_order = VALUES(sort_order),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($mappings as $mapping) {
            $statement->execute([$sourceId, ...$mapping]);
        }
    }

    private function seedMinistryImportSettings(): void
    {
        $sourceId = $this->sourceId('iran_ministry_of_interior');

        if ($sourceId === null) {
            return;
        }

        $settings = [
            ['geography.placeholder_values', '["11"]', 'json'],
            ['geography.country_root_code', 'IR', 'string'],
            ['geography.max_file_size_bytes', '26214400', 'integer'],
            ['geography.allowed_extensions', '["csv"]', 'json'],
        ];
        $statement = $this->db->prepare("
            INSERT INTO data_source_import_settings (
                source_id, setting_key, setting_value, value_type,
                status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                value_type = VALUES(value_type),
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach ($settings as $setting) {
            $statement->execute([$sourceId, ...$setting]);
        }
    }

    private function sourceId(string $code): ?int
    {
        return $this->idForCode('data_sources', $code);
    }

    private function codingSystemId(string $code): ?int
    {
        return $this->idForCode('external_coding_systems', $code);
    }

    private function codeSetId(string $systemCode, string $setCode): ?int
    {
        $statement = $this->db->prepare("
            SELECT sets.id
            FROM external_code_sets sets
            INNER JOIN external_coding_systems systems ON systems.id = sets.coding_system_id
            WHERE systems.code = ? AND sets.code = ?
            LIMIT 1
        ");
        $statement->execute([$systemCode, $setCode]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function idForCode(string $table, string $code): ?int
    {
        $statement = $this->db->prepare("SELECT id FROM {$table} WHERE code = ? LIMIT 1");
        $statement->execute([$code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }
}
