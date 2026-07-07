<?php

namespace IPKF\Database\Seeds;

use IPKF\Core\Database;

abstract class Seeder
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    abstract public function run(): void;
}