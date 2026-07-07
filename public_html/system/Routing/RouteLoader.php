<?php

namespace IPKF\Routing;

use IPKF\Core\Application;

class RouteLoader
{
    public function load(Application $app): void
    {
        $router = $app->router();

        require BASE_PATH . '/routes/web.php';
    }
}