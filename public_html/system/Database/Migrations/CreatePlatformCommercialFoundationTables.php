<?php

namespace IPKF\Database\Migrations;

use PDO;

class CreatePlatformCommercialFoundationTables extends Migration
{
    public function up(): void
    {
        $this->createInstallationsTable();
        $this->createEnvironmentsTable();
        $this->createApplicationsTable();
        $this->createModulesTable();
        $this->createModuleDependenciesTable();
        $this->createInstallationApplicationsTable();
        $this->createInstallationModulesTable();
        $this->createDomainsTable();
        $this->createDatabaseEndpointsTable();
        $this->createStorageEndpointsTable();
        $this->createServiceEndpointsTable();
        $this->createLicensesTable();
        $this->createLicenseEntitlementsTable();
        $this->createLicenseLimitsTable();
        $this->createProvisioningRunsTable();
        $this->createProvisioningStepsTable();
        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function createInstallationsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_installations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                installation_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'single_tenant',
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'planned',
                owner_organization_id BIGINT UNSIGNED NULL,
                metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_installations_reference_unique (public_reference),
                UNIQUE KEY platform_installations_code_unique (code),
                INDEX platform_installations_status_index (status),
                INDEX platform_installations_owner_org_index (owner_organization_id),
                CONSTRAINT platform_installations_code_check CHECK (
                    code = LOWER(code) AND code REGEXP '^[a-z0-9][a-z0-9_-]*[a-z0-9]$'
                ),
                CONSTRAINT platform_installations_type_check CHECK (
                    installation_type IN ('single_tenant', 'multi_tenant', 'development', 'staging', 'production')
                ),
                CONSTRAINT platform_installations_status_check CHECK (
                    status IN ('planned', 'active', 'suspended', 'retired')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createEnvironmentsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_environments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                installation_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                environment_kind VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'development',
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_env_install_code_unique (installation_id, code),
                INDEX platform_env_installation_index (installation_id),
                INDEX platform_env_kind_index (environment_kind),
                INDEX platform_env_status_index (status),
                CONSTRAINT platform_env_code_check CHECK (
                    code = LOWER(code) AND code REGEXP '^[a-z0-9][a-z0-9_-]*[a-z0-9]$'
                ),
                CONSTRAINT platform_env_kind_check CHECK (
                    environment_kind IN ('development', 'staging', 'production', 'testing', 'demo')
                ),
                CONSTRAINT platform_env_status_check CHECK (
                    status IN ('active', 'disabled', 'retired')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createApplicationsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                owner_scope VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'specialized',
                description TEXT NULL,
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_applications_code_unique (code),
                INDEX platform_applications_owner_index (owner_scope),
                INDEX platform_applications_status_index (status),
                INDEX platform_applications_sort_index (sort_order),
                CONSTRAINT platform_applications_code_check CHECK (
                    code = LOWER(code) AND code REGEXP '^[a-z0-9][a-z0-9_-]*[a-z0-9]$'
                ),
                CONSTRAINT platform_applications_owner_check CHECK (
                    owner_scope IN ('platform_core', 'specialized')
                ),
                CONSTRAINT platform_applications_status_check CHECK (
                    status IN ('active', 'disabled', 'deprecated')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createModulesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_modules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                application_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(150) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                module_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'feature',
                description TEXT NULL,
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_modules_code_unique (code),
                UNIQUE KEY platform_modules_app_code_unique (application_id, code),
                INDEX platform_modules_application_index (application_id),
                INDEX platform_modules_type_index (module_type),
                INDEX platform_modules_status_index (status),
                CONSTRAINT platform_modules_code_check CHECK (
                    code = LOWER(code) AND code REGEXP '^[a-z0-9][a-z0-9_.-]*[a-z0-9]$'
                ),
                CONSTRAINT platform_modules_type_check CHECK (
                    module_type IN ('core', 'feature', 'integration', 'reporting', 'extension')
                ),
                CONSTRAINT platform_modules_status_check CHECK (
                    status IN ('active', 'disabled', 'deprecated')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createModuleDependenciesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_module_dependencies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_id BIGINT UNSIGNED NOT NULL,
                depends_on_module_id BIGINT UNSIGNED NOT NULL,
                dependency_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'required',
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_module_deps_unique (module_id, depends_on_module_id),
                INDEX platform_module_deps_module_index (module_id),
                INDEX platform_module_deps_depends_index (depends_on_module_id),
                INDEX platform_module_deps_status_index (status),
                CONSTRAINT platform_module_deps_no_self_check CHECK (module_id <> depends_on_module_id),
                CONSTRAINT platform_module_deps_type_check CHECK (
                    dependency_type IN ('required', 'optional', 'conflict')
                ),
                CONSTRAINT platform_module_deps_status_check CHECK (
                    status IN ('active', 'disabled')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createInstallationApplicationsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_installation_applications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                installation_id BIGINT UNSIGNED NOT NULL,
                environment_id BIGINT UNSIGNED NOT NULL,
                application_id BIGINT UNSIGNED NOT NULL,
                installed_state VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'planned',
                enabled_state VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'disabled',
                installed_at TIMESTAMP NULL,
                enabled_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_install_apps_unique (installation_id, environment_id, application_id),
                INDEX platform_install_apps_installation_index (installation_id),
                INDEX platform_install_apps_environment_index (environment_id),
                INDEX platform_install_apps_application_index (application_id),
                INDEX platform_install_apps_installed_state_index (installed_state),
                INDEX platform_install_apps_enabled_state_index (enabled_state),
                CONSTRAINT platform_install_apps_installed_check CHECK (
                    installed_state IN ('planned', 'installing', 'installed', 'failed', 'removed')
                ),
                CONSTRAINT platform_install_apps_enabled_check CHECK (
                    enabled_state IN ('enabled', 'disabled')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createInstallationModulesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_installation_modules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                installation_id BIGINT UNSIGNED NOT NULL,
                environment_id BIGINT UNSIGNED NOT NULL,
                application_id BIGINT UNSIGNED NOT NULL,
                module_id BIGINT UNSIGNED NOT NULL,
                installed_state VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'planned',
                enabled_state VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'disabled',
                license_state VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'not_evaluated',
                installed_at TIMESTAMP NULL,
                enabled_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_install_modules_unique (installation_id, environment_id, module_id),
                INDEX platform_install_modules_installation_index (installation_id),
                INDEX platform_install_modules_environment_index (environment_id),
                INDEX platform_install_modules_application_index (application_id),
                INDEX platform_install_modules_module_index (module_id),
                INDEX platform_install_modules_installed_index (installed_state),
                INDEX platform_install_modules_enabled_index (enabled_state),
                INDEX platform_install_modules_license_index (license_state),
                CONSTRAINT platform_install_modules_installed_check CHECK (
                    installed_state IN ('planned', 'installing', 'installed', 'failed', 'removed')
                ),
                CONSTRAINT platform_install_modules_enabled_check CHECK (
                    enabled_state IN ('enabled', 'disabled')
                ),
                CONSTRAINT platform_install_modules_license_check CHECK (
                    license_state IN ('not_evaluated', 'licensed', 'unlicensed', 'expired', 'grace')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createDomainsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_domains (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                installation_id BIGINT UNSIGNED NOT NULL,
                environment_id BIGINT UNSIGNED NOT NULL,
                application_id BIGINT UNSIGNED NOT NULL,
                hostname VARCHAR(253) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
                domain_type VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'alias',
                requires_https TINYINT(1) NOT NULL DEFAULT 1,
                verification_status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                primary_domain_slot TINYINT GENERATED ALWAYS AS (
                    CASE WHEN domain_type = 'primary' AND enabled = 1 THEN 1 ELSE NULL END
                ) PERSISTENT,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_domains_reference_unique (public_reference),
                UNIQUE KEY platform_domains_env_host_unique (installation_id, environment_id, hostname),
                UNIQUE KEY platform_domains_primary_unique (installation_id, environment_id, application_id, primary_domain_slot),
                INDEX platform_domains_installation_index (installation_id),
                INDEX platform_domains_environment_index (environment_id),
                INDEX platform_domains_application_index (application_id),
                INDEX platform_domains_type_index (domain_type),
                INDEX platform_domains_verification_index (verification_status),
                CONSTRAINT platform_domains_type_check CHECK (
                    domain_type IN ('primary', 'alias')
                ),
                CONSTRAINT platform_domains_verification_check CHECK (
                    verification_status IN ('pending', 'verified', 'failed', 'disabled')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createDatabaseEndpointsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_database_endpoints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                installation_id BIGINT UNSIGNED NOT NULL,
                environment_id BIGINT UNSIGNED NOT NULL,
                application_id BIGINT UNSIGNED NOT NULL,
                purpose VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                driver VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'mysql',
                host_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,
                port INT UNSIGNED NULL,
                database_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,
                credential_secret_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'planned',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_db_endpoints_reference_unique (public_reference),
                UNIQUE KEY platform_db_endpoints_scope_unique (installation_id, environment_id, application_id, purpose),
                INDEX platform_db_endpoints_installation_index (installation_id),
                INDEX platform_db_endpoints_environment_index (environment_id),
                INDEX platform_db_endpoints_application_index (application_id),
                INDEX platform_db_endpoints_purpose_index (purpose),
                INDEX platform_db_endpoints_status_index (status),
                CONSTRAINT platform_db_endpoints_purpose_check CHECK (
                    purpose IN ('primary', 'read_replica', 'reporting', 'archive')
                ),
                CONSTRAINT platform_db_endpoints_status_check CHECK (
                    status IN ('planned', 'active', 'disabled', 'retired')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createStorageEndpointsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_storage_endpoints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                installation_id BIGINT UNSIGNED NOT NULL,
                environment_id BIGINT UNSIGNED NOT NULL,
                application_id BIGINT UNSIGNED NOT NULL,
                purpose VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private_files',
                provider_code VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                bucket_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,
                base_path_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                credential_secret_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'planned',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_storage_reference_unique (public_reference),
                UNIQUE KEY platform_storage_scope_unique (installation_id, environment_id, application_id, purpose),
                INDEX platform_storage_installation_index (installation_id),
                INDEX platform_storage_environment_index (environment_id),
                INDEX platform_storage_application_index (application_id),
                INDEX platform_storage_provider_index (provider_code),
                INDEX platform_storage_status_index (status),
                CONSTRAINT platform_storage_purpose_check CHECK (
                    purpose IN ('private_files', 'public_assets', 'archive', 'exports', 'imports')
                ),
                CONSTRAINT platform_storage_status_check CHECK (
                    status IN ('planned', 'active', 'disabled', 'retired')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createServiceEndpointsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_service_endpoints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                installation_id BIGINT UNSIGNED NOT NULL,
                environment_id BIGINT UNSIGNED NOT NULL,
                application_id BIGINT UNSIGNED NOT NULL,
                service_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                purpose VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                base_url_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                credential_secret_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'planned',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_service_reference_unique (public_reference),
                UNIQUE KEY platform_service_scope_unique (installation_id, environment_id, application_id, service_code, purpose),
                INDEX platform_service_installation_index (installation_id),
                INDEX platform_service_environment_index (environment_id),
                INDEX platform_service_application_index (application_id),
                INDEX platform_service_code_index (service_code),
                INDEX platform_service_status_index (status),
                CONSTRAINT platform_service_status_check CHECK (
                    status IN ('planned', 'active', 'disabled', 'retired')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createLicensesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_licenses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                installation_id BIGINT UNSIGNED NOT NULL,
                customer_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,
                edition VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'standard',
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'draft',
                activation_mode VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'offline',
                signed_manifest_reference VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
                issued_at TIMESTAMP NULL,
                valid_from TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                grace_until TIMESTAMP NULL,
                revoked_at TIMESTAMP NULL,
                revocation_reference VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_licenses_reference_unique (public_reference),
                INDEX platform_licenses_installation_index (installation_id),
                INDEX platform_licenses_customer_index (customer_reference),
                INDEX platform_licenses_status_index (status),
                INDEX platform_licenses_expiry_index (expires_at),
                CONSTRAINT platform_licenses_status_check CHECK (
                    status IN ('draft', 'active', 'expired', 'suspended', 'revoked')
                ),
                CONSTRAINT platform_licenses_activation_check CHECK (
                    activation_mode IN ('online', 'offline')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createLicenseEntitlementsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_license_entitlements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                license_id BIGINT UNSIGNED NOT NULL,
                module_id BIGINT UNSIGNED NOT NULL,
                entitlement_status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                valid_from TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_license_entitlements_unique (license_id, module_id),
                INDEX platform_license_entitlements_license_index (license_id),
                INDEX platform_license_entitlements_module_index (module_id),
                INDEX platform_license_entitlements_status_index (entitlement_status),
                CONSTRAINT platform_license_entitlements_status_check CHECK (
                    entitlement_status IN ('active', 'disabled', 'expired', 'revoked')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createLicenseLimitsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_license_limits (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entitlement_id BIGINT UNSIGNED NOT NULL,
                metric_code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                limit_value BIGINT UNSIGNED NOT NULL,
                period_code VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'lifetime',
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_license_limits_metric_unique (entitlement_id, metric_code),
                INDEX platform_license_limits_entitlement_index (entitlement_id),
                INDEX platform_license_limits_metric_index (metric_code),
                CONSTRAINT platform_license_limits_metric_check CHECK (
                    metric_code = LOWER(metric_code) AND metric_code REGEXP '^[a-z0-9][a-z0-9_.-]*[a-z0-9]$'
                ),
                CONSTRAINT platform_license_limits_period_check CHECK (
                    period_code IN ('lifetime', 'monthly', 'yearly', 'daily')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createProvisioningRunsTable(): void
    {
        $userType = $this->referenceColumnType('users', 'id', 'BIGINT UNSIGNED');

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_provisioning_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                installation_id BIGINT UNSIGNED NOT NULL,
                environment_id BIGINT UNSIGNED NOT NULL,
                application_id BIGINT UNSIGNED NULL,
                run_type VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'provision',
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                requested_by_user_id {$userType} NULL,
                requested_at TIMESTAMP NULL,
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                failure_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_provisioning_runs_reference_unique (public_reference),
                INDEX platform_provisioning_runs_installation_index (installation_id),
                INDEX platform_provisioning_runs_environment_index (environment_id),
                INDEX platform_provisioning_runs_application_index (application_id),
                INDEX platform_provisioning_runs_status_index (status),
                CONSTRAINT platform_provisioning_runs_status_check CHECK (
                    status IN ('pending', 'running', 'succeeded', 'failed', 'skipped', 'rolled_back')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createProvisioningStepsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_provisioning_steps (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provisioning_run_id BIGINT UNSIGNED NOT NULL,
                step_order INT UNSIGNED NOT NULL,
                step_code VARCHAR(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
                started_at TIMESTAMP NULL,
                completed_at TIMESTAMP NULL,
                failure_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
                safe_metadata_json LONGTEXT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                UNIQUE KEY platform_provisioning_steps_order_unique (provisioning_run_id, step_order),
                UNIQUE KEY platform_provisioning_steps_code_unique (provisioning_run_id, step_code),
                INDEX platform_provisioning_steps_run_index (provisioning_run_id),
                INDEX platform_provisioning_steps_status_index (status),
                CONSTRAINT platform_provisioning_steps_order_check CHECK (step_order > 0),
                CONSTRAINT platform_provisioning_steps_status_check CHECK (
                    status IN ('pending', 'running', 'succeeded', 'failed', 'skipped', 'rolled_back')
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function addForeignKeys(): void
    {
        $this->addForeignKeyIfPossible('platform_installations', 'platform_installations_owner_org_fk', 'owner_organization_id', 'organizations', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('platform_environments', 'platform_env_installation_fk', 'installation_id', 'platform_installations', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('platform_modules', 'platform_modules_application_fk', 'application_id', 'platform_applications', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_module_dependencies', 'platform_module_deps_module_fk', 'module_id', 'platform_modules', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_module_dependencies', 'platform_module_deps_depends_fk', 'depends_on_module_id', 'platform_modules', 'id', 'RESTRICT');

        foreach (['platform_installation_applications', 'platform_installation_modules', 'platform_domains', 'platform_database_endpoints', 'platform_storage_endpoints', 'platform_service_endpoints'] as $table) {
            $prefix = match ($table) {
                'platform_installation_applications' => 'platform_install_apps',
                'platform_installation_modules' => 'platform_install_modules',
                'platform_database_endpoints' => 'platform_db_endpoints',
                'platform_storage_endpoints' => 'platform_storage',
                'platform_service_endpoints' => 'platform_service',
                default => 'platform_domains',
            };

            $this->addForeignKeyIfPossible($table, "{$prefix}_installation_fk", 'installation_id', 'platform_installations', 'id', 'RESTRICT');
            $this->addForeignKeyIfPossible($table, "{$prefix}_environment_fk", 'environment_id', 'platform_environments', 'id', 'RESTRICT');
            $this->addForeignKeyIfPossible($table, "{$prefix}_application_fk", 'application_id', 'platform_applications', 'id', 'RESTRICT');
        }

        $this->addForeignKeyIfPossible('platform_installation_modules', 'platform_install_modules_module_fk', 'module_id', 'platform_modules', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('platform_licenses', 'platform_licenses_installation_fk', 'installation_id', 'platform_installations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_license_entitlements', 'platform_license_entitlements_license_fk', 'license_id', 'platform_licenses', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_license_entitlements', 'platform_license_entitlements_module_fk', 'module_id', 'platform_modules', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_license_limits', 'platform_license_limits_entitlement_fk', 'entitlement_id', 'platform_license_entitlements', 'id', 'RESTRICT');

        $this->addForeignKeyIfPossible('platform_provisioning_runs', 'platform_provisioning_runs_installation_fk', 'installation_id', 'platform_installations', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_provisioning_runs', 'platform_provisioning_runs_environment_fk', 'environment_id', 'platform_environments', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_provisioning_runs', 'platform_provisioning_runs_application_fk', 'application_id', 'platform_applications', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_provisioning_runs', 'platform_provisioning_runs_user_fk', 'requested_by_user_id', 'users', 'id', 'RESTRICT');
        $this->addForeignKeyIfPossible('platform_provisioning_steps', 'platform_provisioning_steps_run_fk', 'provisioning_run_id', 'platform_provisioning_runs', 'id', 'RESTRICT');
    }

    private function referenceColumnType(string $table, string $column, string $default): string
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return $default;
        }

        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);
        $type = strtoupper((string) $statement->fetchColumn());

        return preg_match('/^(TINYINT|SMALLINT|MEDIUMINT|INT|BIGINT)(\(\d+\))?( UNSIGNED)?$/', $type) === 1
            ? $type
            : $default;
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (!$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists($referenceTable, $referenceColumn)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
            || $this->columnType($table, $column) !== $this->columnType($referenceTable, $referenceColumn)
            || $this->foreignKeyExists($table, $constraint)
        ) {
            return;
        }

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column}) REFERENCES {$referenceTable} ({$referenceColumn})
            ON UPDATE RESTRICT ON DELETE {$onDelete}
        ");
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

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnType(string $table, string $column): string
    {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower((string) $statement->fetchColumn());
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower((string) $statement->fetchColumn()) === 'innodb';
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.referential_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
        ");
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn() > 0;
    }
}
