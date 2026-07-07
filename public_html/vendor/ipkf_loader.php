<?php

spl_autoload_register(function ($class) {

    $prefixes = [

        'IPKF\\' => dirname(__DIR__) . '/system/',
        'App\\'  => dirname(__DIR__) . '/app/',

    ];

    foreach ($prefixes as $prefix => $baseDir) {

        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));

        $relative = str_replace('\\', '/', $relative);

        $file = $baseDir . $relative . '.php';

        if (file_exists($file)) {
            require_once $file;
        }

        return;
    }
});