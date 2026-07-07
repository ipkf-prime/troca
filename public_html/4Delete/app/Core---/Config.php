<?php

namespace IPKF\Core;

class Config
{
    protected static array $items = [];

    public static function load(string $path): void
    {
        foreach (glob($path.'/*.php') as $file) {

            $name = basename($file,'.php');

            self::$items[$name] = require $file;

        }
    }

    public static function get(string $key,mixed $default=null): mixed
    {
        $parts = explode('.',$key);

        $config = self::$items;

        foreach($parts as $part){

            if(!isset($config[$part])){

                return $default;

            }

            $config = $config[$part];

        }

        return $config;
    }

}