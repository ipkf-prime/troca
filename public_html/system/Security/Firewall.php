<?php

namespace IPKF\Security;

class Firewall
{
    public function handle($request): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $blocked = [
            '127.0.0.2'
        ];

        if (in_array($ip, $blocked)) {

            http_response_code(403);

            echo "Access Denied";

            return false;
        }

        return true;
    }
}