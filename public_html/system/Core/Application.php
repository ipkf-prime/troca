<?php

namespace IPKF\Core;

use IPKF\Routing\Router;
use IPKF\Http\Request;
use IPKF\Http\Response;

class Application
{
    protected Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function run(): void
    {
        $request = new Request();
        $response = new Response();

        $this->router->dispatch($request, $response);
    }
}