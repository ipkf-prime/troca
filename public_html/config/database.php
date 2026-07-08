<?php

use IPKF\Support\Env;

return [

    'default' => Env::get('DB_CONNECTION', 'mysql'),

    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => Env::get('DB_HOST', 'localhost'),
            'database' => Env::get('DB_NAME', ''),
            'username' => Env::get('DB_USER', ''),
            'password' => Env::get('DB_PASS', ''),
            'port' => (int) Env::get('DB_PORT', 3306),
            'charset' => 'utf8mb4',
        ]

    ]

];
