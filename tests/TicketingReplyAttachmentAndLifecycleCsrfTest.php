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
        . 'TicketLifecycleRepository.php'
    );

$createRepository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketCreateRoutingRepository.php'
    );

$upload =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketAttachmentUploadService.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketLifecycleService.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );


foreach ([
    'persistInitialAttachments',
    'cleanupInitialAttachmentFiles',
    'INSERT INTO ticketing_attachments',
    'message_id',
    'checksum_sha256',
] as $marker) {
    $expect(
        str_contains(
            $createRepository,
            $marker
        ),
        'Initial attachment contract missing: '
        . $marker
    );
}


foreach ([
    'ticketing_private',
    'checksum_sha256',
    'absolute_path',
    'uploaded_by_user_reference',
] as $marker) {
    $expect(
        str_contains(
            $upload,
            $marker
        ),
        'Secure upload contract missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_REPLY_MESSAGE_ATTACHMENTS',
    'persistReplyAttachments',
    'cleanupReplyAttachmentFiles',
    'INSERT INTO ticketing_attachments',
    'message_id',
    'array $attachments = []',
] as $marker) {
    $expect(
        str_contains(
            $repository,
            $marker
        ),
        'Lifecycle attachment contract missing: '
        . $marker
    );
}


$expect(
    substr_count(
        $repository,
        'array $attachments = []'
    ) === 2,
    'Exactly two lifecycle reply methods '
    . 'must accept prepared attachments.'
);


$staffMessage =
    strpos(
        $repository,
        "'ticket_reply_message_insert_failed'"
    );

$staffPersist =
    strpos(
        $repository,
        '$this->persistReplyAttachments(',
        $staffMessage !== false
            ? $staffMessage
            : 0
    );

$expect(
    $staffMessage !== false
    &&
    $staffPersist !== false
    &&
    $staffPersist > $staffMessage,
    'Staff attachments are not linked after '
    . 'the exact message ID is created.'
);


$requesterMessage =
    strpos(
        $repository,
        "'requester_reply_message_insert_failed'"
    );

$requesterPersist =
    strpos(
        $repository,
        '$this->persistReplyAttachments(',
        $requesterMessage !== false
            ? $requesterMessage
            : 0
    );

$expect(
    $requesterMessage !== false
    &&
    $requesterPersist !== false
    &&
    $requesterPersist > $requesterMessage,
    'Requester attachments are not linked after '
    . 'the exact message ID is created.'
);


$expect(
    substr_count(
        $repository,
        '$this->cleanupReplyAttachmentFiles('
    ) === 2,
    'Both reply transactions require '
    . 'rollback file cleanup.'
);


$expect(
    str_contains(
        $service,
        'TICKETING_REPLY_MESSAGE_ATTACHMENTS'
    )
    &&
    str_contains(
        $service,
        'TicketAttachmentUploadService'
    )
    &&
    str_contains(
        $service,
        '$preparedAttachments'
    )
    &&
    str_contains(
        $service,
        'array $files = []'
    ),
    'Lifecycle service does not reuse '
    . 'the secure upload pipeline.'
);


$expect(
    substr_count(
        $service,
        'array $files = []'
    ) === 2,
    'Exactly two reply service methods '
    . 'must accept uploaded files.'
);


$expect(
    substr_count(
        $routes,
        "\$_FILES['attachments']"
    ) >= 3,
    'Create + staff reply + requester reply '
    . 'must forward attachments.'
);


$expect(
    substr_count(
        $view,
        'enctype="multipart/form-data"'
    ) >= 2
    &&
    substr_count(
        $view,
        'name="attachments[]"'
    ) >= 2,
    'Both reply composers must support files.'
);


$expect(
    str_contains(
        $view,
        '$lifecycleActionCsrf'
    ),
    'Independent lifecycle action CSRF missing.'
);


$expect(
    substr_count(
        $view,
        '$lifecycleActionCsrf'
    ) === 4,
    'Independent lifecycle CSRF must have '
    . 'one definition + three form uses.'
);


$expect(
    str_contains(
        $view,
        '$lifecycleCsrf'
    )
    &&
    str_contains(
        $view,
        '$requesterReplyCsrf'
    ),
    'Reply-specific CSRF variables were lost.'
);


echo
    "TICKETING_REPLY_ATTACHMENT_AND_LIFECYCLE_CSRF_PASS\n";
