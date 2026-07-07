<?php

use App\Http\Controllers\HomeController;
use App\Http\Middleware\AuthMiddleware;

/** @var \IPKF\Routing\Router $router */

$router->middleware([AuthMiddleware::class])
       ->get('/dashboard', [HomeController::class, 'index']);

$router->get('/test', function ($req, $res) {
    $res->send("Test Route OK");
});



