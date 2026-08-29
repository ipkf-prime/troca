<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Cannot read '
                . $relative
            );
        }

        return $content;
    };


$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$route =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );


$assert =
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


$assert(
    str_contains(
        $repository,
        'public function attachmentForTicket('
    )
    &&
    str_contains(
        $repository,
        'checksum_sha256'
    ),
    'Secure repository lookup missing.'
);


$assert(
    str_contains(
        $service,
        'public function attachmentForUser('
    )
    &&
    str_contains(
        $service,
        '$this->detailForUser('
    ),
    'Attachment must reuse Detail authorization.'
);


$assert(
    str_contains(
        $route,
        '/attachments/{attachment_id}'
    ),
    'Secure attachment route missing.'
);


$assert(
    str_contains(
        $route,
        "BASE_PATH\n"
        . "                    . '/storage/uploads'"
    ),
    'Correct private upload root missing.'
);


foreach ([
    'ticketing_private',
    "'clean',",
    "'approved',",
    '->status(423)',
    'hash_file(',
    'hash_equals(',
    'Content-Disposition',
    'Content-Length',
    'X-Content-Type-Options',
    'nosniff',
    'Cache-Control',
    'private, no-store, max-age=0',
] as $contract) {

    $assert(
        str_contains(
            $route,
            $contract
        ),
        'Secure route contract missing: '
        . $contract
    );
}


$assert(
    str_contains(
        $view,
        '/attachments/'
    )
    &&
    str_contains(
        $view,
        'target="_blank"'
    )
    &&
    str_contains(
        $view,
        'rel="noopener noreferrer"'
    ),
    'Approved attachment link missing.'
);


$assert(
    !str_contains(
        $view,
        'storage_key'
    ),
    'Private storage key leaked into View.'
);


echo
    "TICKETING_SECURE_ATTACHMENT_ACCESS_PASS\n";


/*
 * A8D6-R3:
 * MIME regex delimiter must not collide with '#'
 * which is a legal MIME token character in this validator.
 */
$routeMimeContract =
    file_get_contents(
        dirname(__DIR__)
        . '/public_html/routes/ticketing-runtime.php'
    );

if (!is_string($routeMimeContract)) {
    throw new RuntimeException(
        'Cannot read Ticketing route for MIME contract.'
    );
}

$badMimePattern =
    <<<'BAD'
'#^[a-z0-9!#$&^_.+\-]+/[a-z0-9!#$&^_.+\-]+$#'
BAD;

$goodMimePattern =
    <<<'GOOD'
'~^[a-z0-9!#$&^_.+\-]+/[a-z0-9!#$&^_.+\-]+$~'
GOOD;

if (
    str_contains(
        $routeMimeContract,
        $badMimePattern
    )
    ||
    !str_contains(
        $routeMimeContract,
        $goodMimePattern
    )
) {
    throw new RuntimeException(
        'Secure attachment MIME regex contract failed.'
    );
}

echo "TICKETING_SECURE_ATTACHMENT_MIME_REGEX_PASS\n";
