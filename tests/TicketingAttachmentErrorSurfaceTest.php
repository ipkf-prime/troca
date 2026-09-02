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
                'Unreadable source: '
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


$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketLifecycleService.php'
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


$expect(
    str_contains(
        $service,
        "'attachment_error_code' =>"
    )
    &&
    str_contains(
        $service,
        "'attachment_error' =>"
    )
    &&
    str_contains(
        $service,
        '$attachmentUpload->errorMessage('
    ),
    'Lifecycle attachment error contract missing.'
);


foreach ([
    'TICKETING_REQUESTER_ATTACHMENT_ERROR_SURFACE_V2',

    "'ticket_attachment_too_many' =>",
    "'ticket_attachment_upload_failed' =>",
    "'ticket_attachment_upload_invalid' =>",
    "'ticket_attachment_empty' =>",
    "'ticket_attachment_too_large' =>",
    "'ticket_attachment_total_too_large' =>",
    "'ticket_attachment_type_invalid' =>",
    "'ticket_attachment_infected' =>",
    "'ticket_attachment_scan_failed' =>",

    "'requester_attachment_too_many'",
    "'requester_attachment_upload_failed'",
    "'requester_attachment_upload_invalid'",
    "'requester_attachment_empty'",
    "'requester_attachment_too_large'",
    "'requester_attachment_total_too_large'",
    "'requester_attachment_type_invalid'",
    "'requester_attachment_infected'",
    "'requester_attachment_scan_failed'",
    "'requester_attachment_invalid'",
] as $marker) {

    $expect(
        str_contains(
            $route,
            $marker
        ),
        'Route attachment status contract missing: '
        . $marker
    );
}


foreach ([
    "'requester_attachment_too_many' =>",
    "'requester_attachment_upload_failed' =>",
    "'requester_attachment_upload_invalid' =>",
    "'requester_attachment_empty' =>",
    "'requester_attachment_too_large' =>",
    "'requester_attachment_total_too_large' =>",
    "'requester_attachment_type_invalid' =>",
    "'requester_attachment_infected' =>",
    "'requester_attachment_scan_failed' =>",
    "'requester_attachment_invalid' =>",

    'نوع یا پسوند فایل انتخاب‌شده مجاز نیست.',
    'فایل انتخاب‌شده آلوده تشخیص داده شد',
    'بررسی امنیتی فایل در حال حاضر انجام نشد.',
] as $marker) {

    $expect(
        str_contains(
            $view,
            $marker
        ),
        'View attachment presentation missing: '
        . $marker
    );
}


/*
 * Do not render arbitrary query-string error text.
 */
$expect(
    !str_contains(
        $view,
        "\$_GET['attachment_error']"
    )
    &&
    !str_contains(
        $route,
        '?attachment_error='
    ),
    'Arbitrary attachment error rendering detected.'
);


$expect(
    str_contains(
        $route,
        "\$_FILES['attachments'] ?? []"
    ),
    'Requester upload forwarding was lost.'
);


$expect(
    substr_count(
        $service,
        "'attachment_error_code'"
    ) === 1,
    'attachment_error_code cardinality changed.'
);


echo "TICKETING_ATTACHMENT_ERROR_SURFACE_V2_PASS\n";
echo "FINITE_SERVER_CLASSIFICATION=PASS\n";
echo "REQUESTER_FILE_FORWARDING=PRESERVED\n";
echo "ARBITRARY_QUERY_ERROR_TEXT=BLOCKED\n";
