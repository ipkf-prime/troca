<?php

use IPKF\Support\Env;

return [

    'name' => Env::get('APP_NAME', 'IPKF'),

    'env' => Env::get('APP_ENV', 'production'),

    'debug' => Env::isDebug(),

    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Tehran'),


];
