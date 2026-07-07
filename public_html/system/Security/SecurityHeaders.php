<?php

namespace IPKF\Security;

class SecurityHeaders
{
    public static function apply(): void
    {
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: no-referrer");
    }
}