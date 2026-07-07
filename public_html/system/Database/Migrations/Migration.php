<?php

namespace IPKF\Database\Migrations;

use IPKF\Core\Database;
use PDO;

abstract class Migration
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    abstract public function up(): void;

    abstract public function down(): void;
}