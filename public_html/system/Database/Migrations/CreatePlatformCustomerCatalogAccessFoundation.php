<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

/** Separate commercial customer identity from deployment installation and organization. */
final class CreatePlatformCustomerCatalogAccessFoundation extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_customers (
                public_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
                code VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                title VARCHAR(255) NOT NULL,
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY platform_customers_code_unique (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_customer_catalog_owners (
                catalog_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
                customer_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                created_by_user_reference VARCHAR(100) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX customer_catalog_owners_customer_index (customer_reference),
                CONSTRAINT customer_catalog_owners_customer_fk
                    FOREIGN KEY (customer_reference) REFERENCES platform_customers(public_reference)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT customer_catalog_owners_catalog_fk
                    FOREIGN KEY (catalog_reference) REFERENCES organization_catalogs(public_reference)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_customer_catalog_grants (
                catalog_reference CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                customer_reference VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'active',
                granted_by_user_reference VARCHAR(100) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (catalog_reference, customer_reference),
                INDEX customer_catalog_grants_customer_status_index (customer_reference, status),
                CONSTRAINT customer_catalog_grants_customer_fk
                    FOREIGN KEY (customer_reference) REFERENCES platform_customers(public_reference)
                    ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT customer_catalog_grants_catalog_fk
                    FOREIGN KEY (catalog_reference) REFERENCES organization_catalogs(public_reference)
                    ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        // Data and access grants are never removed implicitly.
    }
}
