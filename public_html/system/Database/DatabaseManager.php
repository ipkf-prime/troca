<?php

namespace IPKF\Database;

use IPKF\Database\Migrations\MigrationRunner;
use IPKF\Database\Seeds\SeederRunner;

class DatabaseManager
{
    protected MigrationRunner $migrationRunner;

    protected SeederRunner $seederRunner;

    protected array $migrations = [];

    protected array $seeders = [];

    public function __construct()
    {
        $this->migrationRunner = new MigrationRunner();
        $this->seederRunner = new SeederRunner();
    }

    public function migrations(array $migrations): self
    {
        $this->migrations = $migrations;
        return $this;
    }

    public function seeders(array $seeders): self
    {
        $this->seeders = $seeders;
        return $this;
    }

    public function migrate(): void
    {
        $this->migrationRunner->run($this->migrations);
    }

    public function seed(): void
    {
        $this->seederRunner->run($this->seeders);
    }
}
