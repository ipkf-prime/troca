<?php

namespace IPKF\Core;

class Session
{
    public function __construct()
    {
        if(session_status()!==PHP_SESSION_ACTIVE){

            session_start();

        }
    }

    public function set(string $key,mixed $value): void
    {
        $_SESSION[$key]=$value;
    }

    public function get(string $key,mixed $default=null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        session_destroy();
    }
}