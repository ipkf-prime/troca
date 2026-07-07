<?php

namespace IPKF\Routing;

use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Http\Pipeline;

class Router
{
    protected array $routes = [];

    protected array $routeMiddleware = [];

    protected ControllerResolver $resolver;

    public function __construct($container = null)
    {
        $this->resolver = new ControllerResolver($container);
    }

    public function get(string $uri, callable|array $action): void
    {
        $this->routes['GET'][$this->normalize($uri)] = $action;
    }

    public function post(string $uri, callable|array $action): void
    {
        $this->routes['POST'][$this->normalize($uri)] = $action;
    }

    public function middleware(array $middlewares): self
    {
        $this->routeMiddleware = $middlewares;
        return $this;
    }

    protected function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');

        return $uri === '//' ? '/' : $uri;
    }

    public function dispatch(Request $request, Response $response): void
    {
        $method = strtoupper($request->method());
        $uri    = $this->normalize($request->uri());

        $action = $this->routes[$method][$uri] ?? null;

        if ($action === null) {
            http_response_code(404);
            echo "404 - Route not found : {$uri}";
            return;
        }

        $controller = $this->resolver->resolve($action);

        $pipeline = new Pipeline();

        $pipeline
            ->through($this->routeMiddleware)
            ->then(function () use ($controller, $request, $response) {

                $controller($request, $response);

            });
    }
}