<?php

namespace IPKF\Database\Seeds;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, username, password, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            'Admin',
            'admin',
            password_hash('123456', PASSWORD_BCRYPT),
            'super_admin'
        ]);
    }
}