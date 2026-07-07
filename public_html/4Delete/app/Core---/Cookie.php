<?php

namespace IPKF\Core;

class Cookie
{
    public function set(
        string $key,
        string $value,
        int $minutes=60
    ): void
    {
        setcookie(

            $key,

            $value,

            time()+($minutes*60),

            "/"

        );
    }

    public function get(string $key,mixed $default=null): mixed
    {
        return $_COOKIE[$key] ?? $default;
    }

    public function delete(string $key): void
    {
        setcookie(

            $key,

            "",

            time()-3600,

            "/"

        );
    }
}