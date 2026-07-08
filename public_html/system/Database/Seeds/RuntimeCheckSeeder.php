<?php

namespace IPKF\Database\Seeds;

class RuntimeCheckSeeder extends Seeder
{
    public function run(): void
    {
        $statement = $this->db->prepare("
            INSERT INTO ipkf_runtime_checks (check_key, check_value, created_at, updated_at)
            VALUES (:check_key, :check_value, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE
                check_value = VALUES(check_value),
                updated_at = CURRENT_TIMESTAMP
        ");

        $statement->execute([
            'check_key' => 'foundation_v0_2',
            'check_value' => 'migration_seeder_ok',
        ]);
    }
}
