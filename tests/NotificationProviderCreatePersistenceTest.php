<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: cannot read {$path}\n");
        exit(1);
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$service = $read(
    'public_html/app/Services/'
    . 'NotificationProviderManagementService.php'
);
$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationProviderManagementRepository.php'
);

$expect(
    str_contains(
        $service,
        "'is_create' => \$created"
    ),
    'Provider service does not declare the create operation.'
);

$saveStart = strpos(
    $repository,
    'public function save('
);
$saveEnd = strpos(
    $repository,
    'public function setEnabled('
);

$expect(
    $saveStart !== false
    && $saveEnd !== false
    && $saveEnd > $saveStart,
    'Provider repository save method could not be isolated.'
);

$saveBody = substr(
    $repository,
    $saveStart,
    $saveEnd - $saveStart
);

$expect(
    str_contains(
        $saveBody,
        "\$isCreate = !empty(\n"
        . "                \$instance['is_create']"
    ),
    'Provider repository does not read the create operation.'
);

$expect(
    str_contains(
        $saveBody,
        "if (!\$isCreate) {\n"
        . "                \$statement = \$db->prepare("
    ),
    'Provider repository still looks up a generated create reference.'
);

$expect(
    str_contains(
        $saveBody,
        "if (\$isCreate) {\n"
        . "                \$insert = \$db->prepare("
    ),
    'Provider repository does not explicitly insert create operations.'
);

$expect(
    !preg_match(
        '/if\s*\(\s*\$reference\s*!==\s*\'\'\s*\)'
        . '\s*\{\s*\$statement\s*=\s*\$db->prepare/s',
        $saveBody
    ),
    'Legacy reference-based create/update detection is still present.'
);

echo "Notification provider create persistence checks passed.\n";
