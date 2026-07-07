<?php

namespace IPKF\Support;

class Debug
{
    public static function dd(...$vars): void
    {
        echo "<pre>";
        var_dump($vars);
        echo "</pre>";
        exit;
    }
}