<?php

namespace IPKF\Core;

use IPKF\Routing\Router;
use IPKF\Routing\RouteLoader;
use IPKF\Http\Request;
use IPKF\Http\Response;
use IPKF\Support\Config;
use IPKF\Support\Env;

class Application
{
    protected Router $router;
    protected RouteLoader $routeLoader;

    public function __construct()
    {
        echo "APP BOOT START\n";
    
        Env::load(BASE_PATH . '/.env');
    
        Config::load();
    
        $this->container = new Container();
    
        $this->routeLoader = new RouteLoader();
    
        $this->router = new Router();
    
        echo "CORE READY\n";
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function run(): void
    {
        echo "BOOT COMPLETE\n";
    
        $this->routeLoader->load($this);
    
        $request  = new Request();
        $response = new Response();
    
        // 🔥 inject container into router (very important)
        $this->router = new \IPKF\Routing\Router($this->container);
    
        $this->router->dispatch($request, $response);
    }
}