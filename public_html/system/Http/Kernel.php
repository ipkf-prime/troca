<?php

namespace IPKF\Http;

class Kernel
{
    protected array $middleware = [
        \App\Http\Middleware\LogMiddleware::class,
        \App\Http\Middleware\CsrfMiddleware::class,
    ];

    public function middleware(): array
    {
        return $this->middleware;
    }
}