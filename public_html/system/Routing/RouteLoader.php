<?php

namespace IPKF\Routing;

use IPKF\Core\Application;

class RouteLoader
{
    public function load(Application $app): void
    {
        $router = $app->router();

        require BASE_PATH . '/routes/web.php';

        $workItemDetailRoutes = BASE_PATH . '/routes/work-item-detail.php';
        if (is_readable($workItemDetailRoutes)) {
            require $workItemDetailRoutes;
        }

        $workSettingsRoutes = BASE_PATH . '/routes/work-settings.php';
        if (is_readable($workSettingsRoutes)) {
            require $workSettingsRoutes;
        }
    }
}
