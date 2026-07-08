<?php

use IPKF\Support\Config;
use IPKF\Support\Env;

if (!function_exists('config')) {

    function config(string $key, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {

    function env(string $key, $default = null)
    {
        return Env::get($key, $default);
    }
}
