<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255),
                username VARCHAR(255),
                password VARCHAR(255),
                role VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE users");
    }
}