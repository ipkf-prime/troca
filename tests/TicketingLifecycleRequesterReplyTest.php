<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $relative) use ($root): string {
    $text = file_get_contents($root . '/' . $relative);

    if (!is_string($text)) {
        throw new RuntimeException('Unreadable: ' . $relative);
    }

    return $text;
};

$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$repository = $read(
    'public_html/app/Repositories/TicketLifecycleRepository.php'
);
$service = $read(
    'public_html/app/Services/Ticketing/TicketLifecycleService.php'
);
$routes = $read(
    'public_html/routes/ticketing-runtime.php'
);
$view = $read(
    'public_html/resources/views/admin/ticketing-ticket-detail.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'EnableTicketingRequesterReplyOperations.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);

foreach ([
    'public function requesterReply(',
    'public function requesterResolve(',
    "'new'",
    "'in_progress'",
    "'waiting_requester'",
    "'waiting_internal'",
    "'resolved'",
    'requester_update_forbidden_state',
    'requester_resolve_forbidden_state',
    'ticket_requester_updated',
    'ticket_requester_resolved',
    'FOR UPDATE',
    'hash_equals(',
    'resolved_at = CASE',
    'resolved_at = COALESCE(',
    'assignment_preserved',
    'already_resolved',
    'array $attachments = []',
    'persistReplyAttachments',
    'cleanupReplyAttachmentFiles',
] as $marker) {
    $expect(
        str_contains($repository, $marker),
        'Requester repository contract missing: ' . $marker
    );
}

$expect(
    substr_count($repository, 'array $attachments = []') === 2,
    'Reply attachment signatures changed.'
);
$expect(
    substr_count($repository, '$this->cleanupReplyAttachmentFiles(') === 2,
    'Reply rollback cleanup count changed.'
);

foreach ([
    'public function requesterReply(',
    'public function requesterResolve(',
    'TicketAttachmentUploadService',
    '$preparedAttachments',
    'requester_update_forbidden_state',
    'requester_resolve_forbidden_state',
] as $marker) {
    $expect(
        str_contains($service, $marker),
        'Requester service contract missing: ' . $marker
    );
}

$expect(
    substr_count($service, 'array $files = []') === 2,
    'Reply service upload signatures changed.'
);

foreach ([
    '/admin/ticketing/tickets/{public_reference}/requester-reply',
    "'intent'",
    "'resolve'",
    'requesterResolve(',
    'requester_resolved',
    'requester_update_forbidden_state',
    'requester_resolve_forbidden_state',
    "\$_FILES['attachments']",
] as $marker) {
    $expect(
        str_contains($routes, $marker),
        'Requester route contract missing: ' . $marker
    );
}

foreach ([
    'data-ticketing-requester-reply',
    'data-ticketing-requester-reply-form',
    'data-ticketing-requester-resolve-form',
    'name="intent"',
    'value="update"',
    'value="resolve"',
    'name="attachments[]"',
    'مشکلم حل شد',
    'افزودن توضیح',
] as $marker) {
    $expect(
        str_contains($view, $marker),
        'Requester detail contract missing: ' . $marker
    );
}

foreach ([
    'EnableTicketingRequesterReplyOperations',
    'ticketing.ticket.view',
    '{public_reference}/requester-reply',
    'admin_route_permissions',
] as $marker) {
    $expect(
        str_contains(
            $migration,
            $marker
        ),
        'Requester migration contract missing: '
        . $marker
    );
}

$expect(
    !str_contains(
        $migration,
        'ticketing.ticket.requester_reply'
    ),
    'Requester ownership action must not invent '
    . 'a Staff-style permission.'
);

$expect(
    str_contains(
        $registry,
        'EnableTicketingRequesterReplyOperations::class'
    ),
    'Requester migration is not registered.'
);

echo "TICKETING_LIFECYCLE_REQUESTER_REPLY_PASS\n";
