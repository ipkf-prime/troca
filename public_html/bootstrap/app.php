<?php

require_once BASE_PATH . '/vendor/autoload.php';

//use IPKF\Support\ErrorHandler;

//ErrorHandler::register();

echo "BOOTSTRAP START\n";

use IPKF\Core\Application;

$app = new Application();

return $app;


