<?php

require __DIR__ . '/../vendor/autoload.php';

use IPKF\Installer\InstallerController;

$controller = new InstallerController();

$step = $_GET['step'] ?? 1;

switch ($step) {

    case 1:
        $controller->step1();
        break;

    case 2:
        $controller->saveDatabase();
        break;

    case 3:
        $controller->createAdmin();
        break;

    case 4:
        $controller->finish();
        break;
}