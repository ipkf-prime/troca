<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;

final class RepairAppSettingsUtf8mb4 extends Migration
{
    public function up(): void
    {
        if (!Database::tableExists('app_settings')) {
            return;
        }

        $this->db->exec(
            'ALTER TABLE app_settings '
            . 'CONVERT TO CHARACTER SET utf8mb4 '
            . 'COLLATE utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
    }
}
