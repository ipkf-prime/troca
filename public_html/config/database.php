<?php

return [

    'connections' => [

        'mysql' => [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_NAME'] ?? '',
            'username' => $_ENV['DB_USER'] ?? 'root',
            'password' => $_ENV['DB_PASS'] ?? '',
            'port' => $_ENV['DB_PORT'] ?? 3306,
        ]

    ]

];
