<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $path
    ) use ($root): string {
        $content =
            file_get_contents(
                $root . '/' . $path
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Could not read '
                . $path
            );
        }

        return $content;
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


$upload =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketAttachmentUploadService.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketCreateRoutingRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$form =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-form.php'
    );

$routes =
    $read(
        'public_html/routes/'
        . 'ticketing-runtime.php'
    );


foreach ([
    'MAX_FILES',
    'MAX_FILE_BYTES',
    'MAX_TOTAL_BYTES',
    'is_uploaded_file',
    'move_uploaded_file',
    'hash_file',
    'sha256',
    'FILEINFO_MIME_TYPE',
    'ticketing_private',
    '/storage/uploads',
    'absolute_path',
    'scan_status_code',
    'clean',
] as $needle) {
    $expect(
        str_contains(
            $upload,
            $needle
        ),
        'Upload contract missing: '
        . $needle
    );
}


foreach ([
    'array $attachments = []',
    '$messageId',
    'persistInitialAttachments',
    'cleanupInitialAttachmentFiles',
    'INSERT INTO ticketing_attachments',
    'checksum_sha256',
    'scan_status_code',
    'uploaded_by_user_reference',
] as $needle) {
    $expect(
        str_contains(
            $repository,
            $needle
        ),
        'Repository contract missing: '
        . $needle
    );
}


$persist =
    strpos(
        $repository,
        '$this->persistInitialAttachments('
    );

$commit =
    strpos(
        $repository,
        '$this->db->commit();'
    );

$expect(
    $persist !== false
    && $commit !== false
    && $persist < $commit,
    'Attachment metadata must be persisted before commit.'
);


foreach ([
    'array $files = []',
    'TicketAttachmentUploadService',
    '$preparedAttachments',
    "'attachments' =>",
] as $needle) {
    $expect(
        str_contains(
            $service,
            $needle
        ),
        'TicketService contract missing: '
        . $needle
    );
}


foreach ([
    'enctype="multipart/form-data"',
    'type="file"',
    'name="attachments[]"',
    'multiple',
] as $needle) {
    $expect(
        str_contains(
            $form,
            $needle
        ),
        'Create form contract missing: '
        . $needle
    );
}


$expect(
    str_contains(
        $routes,
        "\$_FILES['attachments']"
    ),
    'POST route does not forward attachments.'
);


foreach ([
    '/public/',
    'public/uploads',
    'public/assets',
] as $forbidden) {
    $expect(
        !str_contains(
            $upload,
            $forbidden
        ),
        'Attachment storage leaked into public root.'
    );
}


echo "TICKETING_INITIAL_ATTACHMENT_UPLOAD_PASS\n";
