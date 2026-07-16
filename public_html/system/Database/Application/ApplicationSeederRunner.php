<?php

namespace IPKF\Database\Application;

class ApplicationSeederRunner
{
    public function run(array $seeders): int
    {
        $executed = 0;

        foreach ($seeders as $seeder) {
            $seeder->run();
            $executed++;
        }

        return $executed;
    }
}
