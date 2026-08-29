<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'SupportProjectMembershipConfigurationService.php';

$text =
    file_get_contents($path);

if (!is_string($text)) {
    throw new RuntimeException(
        'Membership service unreadable.'
    );
}

$saveStart =
    strpos(
        $text,
        '    private function saveSettings('
    );

$replaceStart =
    strpos(
        $text,
        '    private function replaceFields('
    );

if (
    $saveStart === false
    ||
    $replaceStart === false
    ||
    $replaceStart <= $saveStart
) {
    throw new RuntimeException(
        'Membership persistence methods unavailable.'
    );
}

$blocks = [
    'saveSettings' =>
        substr(
            $text,
            $saveStart,
            $replaceStart - $saveStart
        ),

    'replaceFields' =>
        substr(
            $text,
            $replaceStart
        ),
];

foreach ($blocks as $name => $block) {

    if (
        preg_match(
            '/:actor,\s*:actor,/s',
            $block
        ) === 1
    ) {
        throw new RuntimeException(
            $name
            . ' contains duplicate PDO named placeholder.'
        );
    }

    if (
        preg_match(
            "/'actor'\s*=>\s*\\\$actor,/s",
            $block
        ) === 1
    ) {
        throw new RuntimeException(
            $name
            . ' retains legacy actor binding.'
        );
    }

    foreach ([
        ':created_by_user_reference',
        ':updated_by_user_reference',
        "'created_by_user_reference' =>",
        "'updated_by_user_reference' =>",
    ] as $required) {

        if (
            !str_contains(
                $block,
                $required
            )
        ) {
            throw new RuntimeException(
                $name
                . ' missing distinct binding: '
                . $required
            );
        }
    }
}

echo "TICKETING_MEMBERSHIP_PDO_BINDING_REGRESSION_PASS\n";
