<?php

use IPKF\Core\Config;
use IPKF\Core\Env;

if(!function_exists('env')){

    function env($key,$default=null){

        return Env::get($key,$default);

    }

}

if(!function_exists('config')){

    function config($key,$default=null){

        return Config::get($key,$default);

    }

}