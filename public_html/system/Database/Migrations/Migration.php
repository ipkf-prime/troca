<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;
use PDO;

abstract class Migration
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    abstract public function up(): void;

    abstract public function down(): void;
}
