<?php

require __DIR__ . '/../vendor/autoload.php';

use IPKF\Database\DatabaseManager;

$manager = new DatabaseManager();

$manager->migrate();
$manager->seed();

echo "MIGRATION DONE";