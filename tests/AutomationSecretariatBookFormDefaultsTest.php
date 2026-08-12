<?php

$root = dirname(__DIR__);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-secretariat-management.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(
    str_contains(
        $view,
        "} elseif (\$formInput === []) {\n"
        . "    \$bookDirectionInput = [];\n"
        . "}"
    ),
    'New book form must not preselect incoming direction.'
);

$expect(
    str_contains(
        $view,
        'انتخاب منبع شماره'
    ),
    'Number sequence selector must expose an explicit neutral option.'
);

$expect(
    str_contains(
        $view,
        "\$inputValue('number_sequence_id') === ''"
    ),
    'Neutral number sequence option must be selected for a fresh form.'
);

$expect(
    str_contains(
        $view,
        "'numbering_strategy_code',\n"
        . "                                        'dedicated'"
    ),
    'Dedicated numbering strategy should remain the default.'
);

echo "Automation secretariat book form default checks passed.\n";
