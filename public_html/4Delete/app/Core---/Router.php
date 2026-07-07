<?php

namespace IPKF\Core;

class Router
{
    protected Application $app;

    protected array $routes = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function get(string $uri, callable $action): void
    {
        $this->routes['GET'][$this->normalize($uri)] = $action;
    }

    public function post(string $uri, callable $action): void
    {
        $this->routes['POST'][$this->normalize($uri)] = $action;
    }

    protected function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');

        return $uri === '//' ? '/' : $uri;
    }

    public function dispatch(): void
    {
        $request = $this->app->request();
        $response = $this->app->response();

        $method = $request->method();
        $uri = parse_url($request->uri(), PHP_URL_PATH);

        $uri = '/' . trim($uri, '/');
        
        if ($uri === '//') {
            $uri = '/';
        }
        if (isset($this->routes[$method][$uri])) {

            call_user_func(
                $this->routes[$method][$uri],
                $request,
                $response
            );

            return;
        }

        $response->status(404);

        echo "<h1>404</h1>";
        echo "<p>Route not found : {$uri}</p>";
    }
}