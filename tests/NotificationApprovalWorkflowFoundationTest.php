<?php

$root = dirname(__DIR__);

require_once $root
    . '/public_html/app/Services/'
    . 'NotificationApprovalStateMachine.php';

$migration = file_get_contents(
    $root
    . '/public_html/system/Database/Migrations/'
    . 'CreateNotificationApprovalWorkflowFoundation.php'
);

$migrate = file_get_contents(
    $root . '/public_html/public/migrate.php'
);

if (!is_string($migration) || !is_string($migrate)) {
    fwrite(
        STDERR,
        "Notification approval foundation sources are missing.\n"
    );
    exit(1);
}

$machine =
    new \App\Services\NotificationApprovalStateMachine();

$statuses = [
    'draft',
    'pending',
    'approved',
    'rejected',
    'cancelled',
    'expired',
    'dispatching',
    'dispatched',
    'partially_dispatched',
    'failed',
];

if ($machine->statuses() !== $statuses) {
    fwrite(STDERR, "Approval statuses are incomplete.\n");
    exit(1);
}

$allowedTransitions = [
    ['draft', 'pending'],
    ['pending', 'approved'],
    ['pending', 'rejected'],
    ['pending', 'cancelled'],
    ['pending', 'expired'],
    ['approved', 'dispatching'],
    ['dispatching', 'dispatched'],
    ['dispatching', 'partially_dispatched'],
    ['dispatching', 'failed'],
    ['partially_dispatched', 'dispatching'],
    ['failed', 'dispatching'],
];

foreach ($allowedTransitions as $transition) {
    if (!$machine->canTransition(
        $transition[0],
        $transition[1]
    )) {
        fwrite(
            STDERR,
            "Missing transition: "
            . implode(' -> ', $transition)
            . "\n"
        );
        exit(1);
    }
}

foreach ([
    'rejected',
    'cancelled',
    'expired',
    'dispatched',
] as $terminal) {
    if (!$machine->isTerminal($terminal)) {
        fwrite(
            STDERR,
            "Status is not terminal: {$terminal}\n"
        );
        exit(1);
    }
}

if ($machine->canTransition('pending', 'dispatching')) {
    fwrite(
        STDERR,
        "Pending request bypasses approval.\n"
    );
    exit(1);
}

$invalidTransitionRejected = false;

try {
    $machine->assertTransition(
        'draft',
        'dispatched'
    );
} catch (\DomainException $exception) {
    $invalidTransitionRejected =
        $exception->getMessage()
            === 'notification_approval_transition_invalid';
}

if (!$invalidTransitionRejected) {
    fwrite(
        STDERR,
        "Invalid transition was not rejected.\n"
    );
    exit(1);
}

$tables = [
    'notification_approval_requests',
    'notification_approval_targets',
    'notification_approval_steps',
    'notification_approval_decisions',
    'notification_approval_media_links',
    'notification_approval_dispatch_runs',
    'notification_approval_events',
];

foreach ($tables as $table) {
    if (!str_contains($migration, $table)) {
        fwrite(
            STDERR,
            "Missing approval table: {$table}\n"
        );
        exit(1);
    }
}

$permissions = [
    'notifications.approvals.view',
    'notifications.approvals.decide',
    'notifications.approvals.manage',
    'notifications.approvals.cancel_own',
];

foreach ($permissions as $permission) {
    if (!str_contains($migration, $permission)) {
        fwrite(
            STDERR,
            "Missing approval permission: {$permission}\n"
        );
        exit(1);
    }
}

foreach ([
    'payload_checksum_sha256',
    'approver_rule_json',
    'destination_hash',
    'provider_instance_id',
    'result_json',
    'from_status',
    'to_status',
] as $marker) {
    if (!str_contains($migration, $marker)) {
        fwrite(
            STDERR,
            "Missing snapshot or audit marker: {$marker}\n"
        );
        exit(1);
    }
}

if (
    !str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\'
        . 'CreateNotificationApprovalWorkflowFoundation()'
    )
) {
    fwrite(
        STDERR,
        "Approval workflow migration is not registered.\n"
    );
    exit(1);
}

echo "Notification approval workflow foundation checks passed.\n";
