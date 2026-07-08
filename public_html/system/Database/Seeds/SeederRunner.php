<?php

namespace IPKF\Database\Seeds;

class SeederRunner
{
    public function run(array $seeders): void
    {
        foreach ($seeders as $seeder) {
            $seeder->run();
        }
    }
}
