<?php

namespace IPKF\Security;

use IPKF\Http\Session;

class Csrf
{
    protected Session $session;

    public function __construct()
    {
        $this->session = new Session();
    }

    public function token(): string
    {
        if (!$this->session->has('_token')) {
            $this->session->set('_token', bin2hex(random_bytes(32)));
        }

        return $this->session->get('_token');
    }

    public function check(string $token): bool
    {
        return hash_equals($this->token(), $token);
    }
}