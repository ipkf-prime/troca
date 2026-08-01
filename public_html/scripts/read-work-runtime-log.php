<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not Found\n");
}

define('BASE_PATH', dirname(__DIR__));

$file = BASE_PATH
    . '/storage/logs/work-runtime.log';

$limit = 10;

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = max(
            1,
            min(
                100,
                (int) substr($argument, 8)
            )
        );
    }
}

if (!is_readable($file)) {
    echo "No Work runtime failure has been logged.\n";
    exit(0);
}

$lines = file(
    $file,
    FILE_IGNORE_NEW_LINES
    | FILE_SKIP_EMPTY_LINES
) ?: [];

$lines = array_slice($lines, -$limit);

foreach ($lines as $line) {
    $payload = json_decode($line, true);

    if (!is_array($payload)) {
        echo $line . PHP_EOL;
        continue;
    }

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
    ) . PHP_EOL;
}
