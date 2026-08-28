<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$read =
    static function (
        string $relative
    ) use ($root): string {

        $value =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($value)) {
            throw new RuntimeException(
                'Cannot read '
                . $relative
            );
        }

        return $value;
    };


$expect =
    static function (
        bool $condition,
        string $message
    ): void {

        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketStaffOperationsRepository.php'
    );


$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketStaffOperationsService.php'
    );


foreach ([
    'public function displayNameForUserReference(',
    'ticketing_support_project_members',
    'display_name_snapshot',
    'user_reference = ?',
] as $marker) {

    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Repository actor-name contract missing: '
        . $marker
    );
}


foreach ([
    'displayNameForUserReference(',
    '$membershipDisplayName',
    "'user:' . \$userId",
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Service actor-name contract missing: '
        . $marker
    );
}


$membershipPosition =
    strpos(
        $service,
        '$membershipDisplayName'
    );

$contextPosition =
    strpos(
        $service,
        "'display_name'"
    );


$expect(
    $membershipPosition !== false
    &&
    $contextPosition !== false
    &&
    $membershipPosition
        < $contextPosition,
    'Ticketing membership display name must '
    . 'take precedence over Admin context.'
);


echo
    "TICKETING_STAFF_ACTOR_DISPLAY_NAME_CONTRACT_PASS\n";
