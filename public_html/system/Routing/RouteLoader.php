<?php

namespace IPKF\Routing;

use IPKF\Core\Application;

class RouteLoader
{
    public function load(Application $app): void
    {
        $router = $app->router();

        require BASE_PATH . '/routes/web.php';

        foreach ([
            BASE_PATH . '/routes/admin-users-manage.php',
            BASE_PATH . '/routes/work-item-detail.php',
            BASE_PATH . '/routes/work-settings.php',
            BASE_PATH . '/routes/work-project-access.php',
        ] as $routeFile) {
            if (is_readable($routeFile)) {
                require $routeFile;
            }
        }
    }
}
