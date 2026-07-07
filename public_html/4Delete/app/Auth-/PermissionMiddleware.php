<?php

namespace IPKF\Auth;

use IPKF\Http\Request;

class PermissionMiddleware
{
    protected string $permission;

    public function __construct(string $permission)
    {
        $this->permission = $permission;
    }

    public function handle(Request $request, callable $next)
    {
        if (!can($this->permission)) {

            http_response_code(403);

            echo "Forbidden - No Permission";

            return;
        }

        return $next($request);
    }
}