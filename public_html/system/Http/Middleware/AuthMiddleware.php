<?php

namespace IPKF\Http\Middleware;

use IPKF\Http\Request;
use IPKF\Http\Response;
use App\Services\AuthService;

class AuthMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        if (!(new AuthService())->authenticated()) {
            return $response->status(401)->json([
                'status' => 'error',
                'authenticated' => false,
                'message' => 'Unauthenticated.',
            ]);
        }

        return $next($request, $response);
    }
}
