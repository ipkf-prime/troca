<?php

namespace IPKF\Database\Seeds;

use IPKF\Database\Database;
use PDO;

abstract class Seeder
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    abstract public function run(): void;
}
