<?php

namespace App\Services;

use App\Repositories\AdminNavigationRegistryRepository;
use Throwable;

class DynamicRouteAccessService extends BaseService
{
    public function __construct(
        private ?AdminNavigationRegistryRepository $repository = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??= new AdminNavigationRegistryRepository();
        $this->authorization ??= new AuthorizationService();
    }

    public function can(
        int $userId,
        string $method,
        string $path
    ): bool {
        try {
            $rules = $this->repository->routeRules($method);
        } catch (Throwable) {
            return false;
        }

        foreach ($rules as $rule) {
            if (!$this->matches((string) $rule['route_pattern'], $path)) {
                continue;
            }

            $permissions = json_decode(
                (string) $rule['permission_codes_json'],
                true
            );

            if (!is_array($permissions)) {
                return false;
            }

            $results = array_map(
                fn ($permission): bool =>
                    $this->authorization->hasPermission(
                        $userId,
                        (string) $permission
                    ),
                $permissions
            );

            return ($rule['permission_mode'] ?? 'any') === 'all'
                ? !in_array(false, $results, true)
                : in_array(true, $results, true);
        }

        return false;
    }

    private function matches(string $pattern, string $path): bool
    {
        $quoted = preg_quote(rtrim($pattern, '/') ?: '/', '#');
        $regex = preg_replace(
            '/\\\\\{[^}]+\\\\\}/',
            '[^/]+',
            $quoted
        );

        return preg_match(
            '#^' . $regex . '/?$#',
            rtrim($path, '/') ?: '/'
        ) === 1;
    }
}
