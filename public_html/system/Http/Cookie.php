<?php

namespace IPKF\Http;

class Cookie
{
    public function set(string $name, string $value, int $minutes = 60): void
    {
        setcookie($name, $value, time() + ($minutes * 60), "/");
    }

    public function get(string $name)
    {
        return $_COOKIE[$name] ?? null;
    }

    public function forget(string $name): void
    {
        setcookie($name, '', time() - 3600, "/");
    }
}