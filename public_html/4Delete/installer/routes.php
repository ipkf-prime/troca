<?php

use IPKF\Installer\InstallerController;

$router->get('/install', [InstallerController::class, 'step1']);
$router->post('/install/db', [InstallerController::class, 'saveDatabase']);
$router->post('/install/admin', [InstallerController::class, 'createAdmin']);
$router->get('/install/finish', [InstallerController::class, 'finish']);