<?php

namespace IPKF\Database\Migrations;

class ExtendApplicationModuleRegistryNavigationMetadata extends Migration
{
    public function up(): void
    {
        $columns = [
            'icon_code' =>
                "VARCHAR(100) NULL",

            'color_code' =>
                "VARCHAR(50) NULL",

            'route_path' =>
                "VARCHAR(255) NULL",

            'permission_key' =>
                "VARCHAR(190) NULL",

            'sidebar_enabled' =>
                "TINYINT(1) NOT NULL DEFAULT 1",

            'dashboard_enabled' =>
                "TINYINT(1) NOT NULL DEFAULT 1",

            'dashboard_description' =>
                "TEXT NULL",
        ];

        foreach ($columns as $name=>$definition){

            $check=$this->db->query(
                "SHOW COLUMNS FROM application_modules LIKE '{$name}'"
            );

            if(!$check->fetch()){

                $this->db->exec(
                    "ALTER TABLE application_modules
                     ADD COLUMN {$name} {$definition}"
                );

            }
        }
    }


    public function down(): void
    {
    }
}
