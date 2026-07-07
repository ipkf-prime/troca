<?php

namespace IPKF\Core\Middleware;

class AuthMiddleware
{
    public function handle($request, $response, $next)
    {
        if (!isset($_SESSION['user'])) {
            header("Location: /login");
            exit;
        }

        return $next($request, $response);
    }
}