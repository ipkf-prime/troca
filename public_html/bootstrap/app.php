<?php

require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/system/Support/helpers.php';

use IPKF\Core\Application;
use IPKF\Routing\RouteLoader;
use IPKF\Support\Config;
use IPKF\Support\Env;
use IPKF\Support\ErrorHandler;

Env::load(BASE_PATH . '/.env');
date_default_timezone_set(\IPKF\Support\Clock::STORAGE_TIMEZONE);
ErrorHandler::register();
Config::load();

$app = new Application();

(new RouteLoader())->load($app);

return $app;
