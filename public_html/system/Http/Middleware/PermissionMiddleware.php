<?php

namespace IPKF\Http\Middleware;

use App\Services\AuthService;
use App\Services\AuthorizationService;
use IPKF\Http\Request;
use IPKF\Http\Response;

class PermissionMiddleware
{
    protected string $permission = '';

    public function handle(Request $request, Response $response, callable $next): Response
    {
        $auth = new AuthService();
        $userId = $auth->currentUserId();

        if ($userId === null) {
            return $response->status(401)->json([
                'status' => 'error',
                'authenticated' => false,
                'message' => 'Unauthenticated.',
            ]);
        }

        if ($this->permission !== '' && !(new AuthorizationService())->hasPermission($userId, $this->permission)) {
            return $response->status(403)->json([
                'status' => 'error',
                'message' => 'Forbidden.',
            ]);
        }

        return $next($request, $response);
    }
}
