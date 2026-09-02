<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$source = file_get_contents(
    $root
    . '/public_html/system/Support/ApplicationUrlRegistry.php'
);

if ($source === false) {
    throw new RuntimeException(
        'ApplicationUrlRegistry source unavailable.'
    );
}

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expect(
    str_contains(
        $source,
        '$this->isApplicationModuleHost($host)'
    ),
    'Host guard must resolve active module hosts dynamically.'
);

$expect(
    str_contains(
        $source,
        '(new ModuleRuntimeConfig())->allActive()'
    ),
    'Dynamic module host resolution must use active registry entries.'
);

$expect(
    !str_contains(
        $source,
        '[$this->coreHost(), $this->automationHost(), $this->workHost(), $this->ticketingHost()]'
    ),
    'Host guard must not contain a fixed module-host inventory.'
);

$expect(
    str_contains(
        $source,
        "Env::get('ALLOWED_APP_HOSTS', '')"
    ),
    'Additional non-module hosts must remain configurable.'
);

echo "DYNAMIC_HOST_GUARD_REGISTRY_PASS\n";
