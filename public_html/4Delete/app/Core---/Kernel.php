<?php

namespace IPKF\Core;

class Kernel
{
    protected array $middleware = [];

    public function add(Middleware $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle(Request $request): bool
    {
        foreach ($this->middleware as $middleware) {

            if (!$middleware->handle($request)) {
                return false;
            }

        }

        return true;
    }
}