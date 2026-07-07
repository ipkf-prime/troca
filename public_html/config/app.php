<?php

return [

    'name' => $_ENV['APP_NAME'] ?? 'IPKF',

    'env' => $_ENV['APP_ENV'] ?? 'production',

    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),


];