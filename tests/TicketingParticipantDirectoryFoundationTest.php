<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateTicketingParticipantDirectoryFoundation.php'
    );

$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

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


foreach ([
    'ticketing_participants',
    'ticketing_participant_import_batches',
    'ticketing_participant_import_rows',
    'source_row_number',
    'participant_id',
    'requester_participant_id',
    'core_user_reference',
    'origin_code',
    'account_state',
    'email_normalized',
    'mobile_normalized',
    'external_reference',
    "'linked'",
    "'contact'",
] as $needle) {
    $expect(
        str_contains(
            $migration,
            $needle
        ),
        'Participant migration contract missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $migration,
        'ticketing_support_project_members_project_participant_unique'
    ),
    'Participant membership uniqueness missing.'
);


$expect(
    str_contains(
        $migration,
        'ticketing_support_project_members_participant_fk'
    ),
    'Participant membership FK missing.'
);


$expect(
    str_contains(
        $migration,
        'ticketing_tickets_requester_participant_fk'
    ),
    'Ticket requester participant FK missing.'
);


$expect(
    str_contains(
        $migration,
        'MODIFY COLUMN user_reference'
    )
    && str_contains(
        $migration,
        'NULL'
    ),
    'Legacy membership user reference must become nullable.'
);


$expect(
    str_contains(
        $registry,
        'CreateTicketingParticipantDirectoryFoundation::class'
    ),
    'Participant migration registry entry missing.'
);


foreach ([
    'TSP-NEP',
    'TSVC-NEP',
    "'np'",
    "'nep'",
    'نهاده پخش',
] as $forbidden) {

    $expect(
        !str_contains(
            $migration,
            $forbidden
        ),
        'Business-specific hardcode leaked: '
        . $forbidden
    );
}


$expect(
    !preg_match(
        '/JOIN\s+(?:`?[^`\s]+`?\.)?users\b/i',
        $migration
    ),
    'Participant migration must not cross-DB JOIN Core users.'
);


echo "TICKETING_PARTICIPANT_DIRECTORY_FOUNDATION_PASS\n";
