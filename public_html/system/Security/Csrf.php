<?php

namespace IPKF\Security;

use IPKF\Support\Session;

class Csrf
{
    public function token(): string
    {
        if (!Session::has('_token')) {
            Session::put('_token', bin2hex(random_bytes(32)));
        }

        return (string) Session::get('_token');
    }

    public function check(string $token): bool
    {
        return hash_equals($this->token(), $token);
    }
}
