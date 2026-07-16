<?php

namespace IPKF\Database\Application;

class ApplicationSeederRunner
{
    public function run(array $seeders): void
    {
        foreach ($seeders as $seeder) {
            $seeder->run();
        }
    }
}
