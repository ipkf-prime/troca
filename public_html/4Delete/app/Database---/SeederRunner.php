<?php

namespace IPKF\Database;

class SeederRunner
{
    public function run(): void
    {
        $files = glob(BASE_PATH . '/database/seeders/*.php');

        foreach ($files as $file) {

            require $file;

            $class = basename($file, '.php');

            (new $class)->run();
        }
    }
}