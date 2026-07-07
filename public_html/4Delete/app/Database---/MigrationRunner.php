<?php

namespace IPKF\Database;

class MigrationRunner
{
    public function run(): void
    {
        $files = glob(BASE_PATH . '/database/migrations/*.php');

        foreach ($files as $file) {

            $migration = require $file;

            $schema = new Schema();

            $migration->up($schema);
        }
    }
}