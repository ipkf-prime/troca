<?php

namespace IPKF\Core;

class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    public static function check(string $token): bool
    {
        return isset($_SESSION['_token']) && $_SESSION['_token'] === $token;
    }
}