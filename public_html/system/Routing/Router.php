<?php

namespace IPKF\Routing;

use IPKF\Core\Container;
use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Http\Pipeline;

class Router
{
    protected array $routes = [];

    protected ControllerResolver $resolver;

    protected array $pendingMiddleware = [];

    public function __construct(Container $container)
    {
        $this->resolver = new ControllerResolver($container);
    }

    public function get(string $uri, callable|array $action): self
    {
        return $this->add('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): self
    {
        return $this->add('POST', $uri, $action);
    }

    protected function add(string $method, string $uri, callable|array $action): self
    {
        $this->routes[$method][$this->normalize($uri)] = [
            'action' => $action,
            'middleware' => $this->pendingMiddleware,
        ];

        $this->pendingMiddleware = [];

        return $this;
    }

    public function middleware(array $middlewares): self
    {
        $this->pendingMiddleware = $middlewares;
        return $this;
    }

    protected function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');

        return $uri === '//' ? '/' : $uri;
    }

    public function dispatch(Request $request, Response $response): Response
    {
        $method = strtoupper($request->method());
        $uri    = $this->normalize($request->uri());

        $route = $this->routes[$method][$uri] ?? null;

        if ($route === null) {
            return $response->status(404)->send("404 - Route not found: {$uri}");
        }

        $action = $route['action'];
        $controller = $this->resolver->resolve($action);

        return (new Pipeline())
            ->through($route['middleware'])
            ->send($request, $response)
            ->then(function (Request $request, Response $response) use ($controller): Response {
                $result = $controller($request, $response);

                return $result instanceof Response ? $result : $response;
            });
    }
}
