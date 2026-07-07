<?php

/** @var \IPKF\Routing\Router $router */

$router->get('/', function ($request, $response) {
    return $response->send('IPKF Framework Genesis OK');
});

$router->get('/test', function ($req, $res) {
    return $res->send("Test Route OK");
});
