<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


require_once
    $root
    . '/public_html/app/Services/Ticketing/'
    . 'TicketAttachmentUploadService.php';


$read =
    static function (
        string $relative
    ) use ($root): string {

        $text =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($text)) {
            throw new RuntimeException(
                'Unreadable: '
                . $relative
            );
        }

        return $text;
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


$uploader =
    new \App\Services\Ticketing\TicketAttachmentUploadService();


$scopeA = [
    'portal_reference' =>
        'PORTAL-A',

    'project_reference' =>
        'PROJECT-A',

    'ticket_reference' =>
        'TKT-A',
];


$scopeB = [
    'portal_reference' =>
        'PORTAL-B',

    'project_reference' =>
        'PROJECT-B',

    'ticket_reference' =>
        'TKT-B',
];


$scopeC = [
    'portal_reference' =>
        'PORTAL-A',

    'project_reference' =>
        'PROJECT-B',

    'ticket_reference' =>
        'TKT-B',
];


$a =
    $uploader->namespacePrefix(
        $scopeA
    );

$b =
    $uploader->namespacePrefix(
        $scopeB
    );

$c =
    $uploader->namespacePrefix(
        $scopeC
    );


$expect(
    $a ===
        'ticketing/portals/PORTAL-A'
        . '/projects/PROJECT-A'
        . '/tickets/TKT-A'
        . '/attachments',
    'Scope A namespace mismatch.'
);


$expect(
    $b ===
        'ticketing/portals/PORTAL-B'
        . '/projects/PROJECT-B'
        . '/tickets/TKT-B'
        . '/attachments',
    'Scope B namespace mismatch.'
);


$expect(
    $a !== $b,
    'Cross-portal namespace collision.'
);


$expect(
    $a !== $c,
    'Cross-project namespace collision.'
);


$opaque =
    '0123456789abcdef.pdf';


$expect(
    $a . '/' . $opaque
    !==
    $b . '/' . $opaque,
    'Same filename collision isolation failed.'
);


$unsafeRejected = false;

try {

    $uploader->namespacePrefix([
        'portal_reference' =>
            '../portal',

        'project_reference' =>
            'PROJECT-A',

        'ticket_reference' =>
            'TKT-A',
    ]);

} catch (InvalidArgumentException) {

    $unsafeRejected = true;
}


$expect(
    $unsafeRejected,
    'Unsafe scope segment accepted.'
);


$upload =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketAttachmentUploadService.php'
    );

$scopeResolver =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketAttachmentStorageScopeService.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$lifecycle =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketLifecycleService.php'
    );

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
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

$shared =
    $read(
        'public_html/app/Services/Infrastructure/'
        . 'SharedPrivateStorageService.php'
    );


foreach ([
    'TICKETING_ATTACHMENT_STORAGE_SCOPE_V1',
    'public function forProjectRealm(',
    'public function forTicket(',
    'r.default_portal_id',
    'ticketing_support_portals po',
] as $marker) {

    $expect(
        str_contains(
            $scopeResolver,
            $marker
        ),
        'Scope marker missing: '
        . $marker
    );
}


foreach ([
    'TICKETING_ATTACHMENT_PORTAL_PROJECT_RESOURCE_NAMESPACE_V1',
    'SharedPrivateStorageService',
    'prepareDirectoryForNew',
    "'ticketing/portals/'",
    "'/projects/'",
    "'/tickets/'",
    "'/attachments'",
] as $marker) {

    $expect(
        str_contains(
            $upload,
            $marker
        ),
        'Uploader marker missing: '
        . $marker
    );
}


$expect(
    !str_contains(
        $upload,
        "'ticketing/attachments/'"
    ),
    'Legacy flat namespace is still a write target.'
);


foreach ([
    '$ticketReference',
    'TicketAttachmentStorageScopeService',
    '->forProjectRealm(',
    '$attachmentScope',
] as $marker) {

    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Create marker missing: '
        . $marker
    );
}


$expect(
    substr_count(
        $lifecycle,
        'new TicketAttachmentStorageScopeService()'
    ) === 2,
    'Reply scope resolver count invalid.'
);


$expect(
    substr_count(
        $lifecycle,
        '->forTicket('
    ) === 2,
    'Both reply paths must resolve Ticket scope.'
);


$listStart =
    strpos(
        $repository,
        'public function attachments('
    );

$lookupStart =
    strpos(
        $repository,
        'public function attachmentForTicket('
    );

$eventsStart =
    strpos(
        $repository,
        'public function events('
    );


$expect(
    is_int($listStart)
    &&
    is_int($lookupStart)
    &&
    is_int($eventsStart)
    &&
    $listStart < $lookupStart
    &&
    $lookupStart < $eventsStart,
    'Repository attachment boundaries invalid.'
);


$listBlock =
    substr(
        $repository,
        $listStart,
        $lookupStart
            - $listStart
    );


$lookupBlock =
    substr(
        $repository,
        $lookupStart,
        $eventsStart
            - $lookupStart
    );


$expect(
    str_contains(
        $listBlock,
        'public_reference'
    ),
    'Attachment public reference missing from presentation query.'
);


foreach ([
    'string $attachmentReference',
    'WHERE public_reference = ?',
    'AND ticket_id = ?',
] as $marker) {

    $expect(
        str_contains(
            $lookupBlock,
            $marker
        ),
        'Secure lookup marker missing: '
        . $marker
    );
}


$routeStart =
    strpos(
        $route,
        "'/admin/ticketing/tickets/"
        . "{public_reference}/attachments/"
        . "{attachment_reference}'"
    );

$routeEnd =
    strpos(
        $route,
        " * Detail\n",
        is_int($routeStart)
            ? $routeStart
            : 0
    );


$expect(
    is_int($routeStart)
    &&
    is_int($routeEnd)
    &&
    $routeStart < $routeEnd,
    'Secure attachment route missing.'
);


$attachmentRoute =
    substr(
        $route,
        $routeStart,
        $routeEnd
            - $routeStart
    );


foreach ([
    'attachmentForUser(',
    ')->canViewTicket(',
    'attachmentForAuthorizedContext(',
    'SharedPrivateStorageService',
    '->resolveExisting(',
    "'attachment_reference'",
    "'clean',",
    "'approved',",
    'hash_file(',
    'hash_equals(',
] as $marker) {

    $expect(
        str_contains(
            $attachmentRoute,
            $marker
        ),
        'Attachment route marker missing: '
        . $marker
    );
}


foreach ([
    '$attachmentId',
    "'attachment_id'",
    '{attachment_id}',
] as $forbidden) {

    $expect(
        !str_contains(
            $attachmentRoute,
            $forbidden
        ),
        'Legacy numeric identity remains: '
        . $forbidden
    );
}


$first =
    strpos(
        $attachmentRoute,
        'attachmentForUser('
    );

$second =
    strpos(
        $attachmentRoute,
        ')->canViewTicket('
    );

$third =
    strpos(
        $attachmentRoute,
        'attachmentForAuthorizedContext('
    );


$expect(
    is_int($first)
    &&
    is_int($second)
    &&
    is_int($third)
    &&
    $first < $second
    &&
    $second < $third,
    'Parent authorization ordering changed.'
);


$expect(
    str_contains(
        $view,
        "'public_reference'"
    )
    &&
    str_contains(
        $view,
        ". '/attachments/'"
    ),
    'View does not use attachment public identity.'
);


foreach ([
    'public function resolveExisting(',
    'public function prepareDirectoryForNew(',
    "case 'ticketing':",
    ". '/storage/uploads'",
] as $marker) {

    $expect(
        str_contains(
            $shared,
            $marker
        ),
        'Shared storage compatibility missing: '
        . $marker
    );
}


echo "TICKETING_ATTACHMENT_STORAGE_NAMESPACE_CONTRACT_PASS\n";
echo "PORTAL_PROJECT_TICKET_NAMESPACE=PASS\n";
echo "CROSS_PORTAL_ISOLATION=PASS\n";
echo "CROSS_PROJECT_ISOLATION=PASS\n";
echo "SAME_FILENAME_COLLISION_ISOLATION=PASS\n";
echo "PUBLIC_ATTACHMENT_IDENTITY=PASS\n";
echo "INCIDENT_CONTEXT_PUBLIC_IDENTITY=PASS\n";
echo "PARENT_AUTHORIZATION_REUSE=PASS\n";
echo "CONFIGURABLE_PRIVATE_BACKEND=PASS\n";
echo "LEGACY_STORAGE_READ_COMPATIBILITY=PASS\n";
