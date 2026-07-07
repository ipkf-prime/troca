<?php

namespace IPKF\Database;

use IPKF\Database\Migrations\MigrationRunner;

class DatabaseManager
{
    protected MigrationRunner $runner;

    public function __construct()
    {
        $this->runner = new MigrationRunner();
    }

    public function migrate(): void
    {
        $this->runner->run([
            new \IPKF\Database\Migrations\CreateUsersTable()
        ]);
    }

    public function seed(): void
    {
        (new \IPKF\Database\Seeds\UserSeeder())->run();
    }
}