<?php

$root = dirname(__DIR__);

$paths = [
    'repository' =>
        $root
        . '/public_html/app/Repositories/'
        . 'NotificationApprovalRepository.php',

    'workflow' =>
        $root
        . '/public_html/app/Services/'
        . 'NotificationApprovalWorkflowService.php',

    'management' =>
        $root
        . '/public_html/app/Services/'
        . 'NotificationApprovalManagementService.php',

    'dispatch' =>
        $root
        . '/public_html/app/Services/'
        . 'NotificationApprovalDispatchService.php',

    'state' =>
        $root
        . '/public_html/app/Services/'
        . 'NotificationApprovalStateMachine.php',

    'settings' =>
        $root
        . '/public_html/app/Services/'
        . 'CommunicationSettingsService.php',

    'route' =>
        $root
        . '/public_html/routes/'
        . 'communication-center.php',

    'view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'communication-settings.php',

    'migration' =>
        $root
        . '/public_html/system/Database/Migrations/'
        . 'EnableNotificationApprovalManagement.php',
];

$source = [];

foreach ($paths as $name => $path) {
    $value = file_get_contents($path);

    if (!is_string($value)) {
        fwrite(
            STDERR,
            "Missing notification approval source: {$name}\n"
        );
        exit(1);
    }

    $source[$name] = $value;
}

$requireMarkers = static function (
    string $name,
    string $text,
    array $markers
): void {
    foreach ($markers as $marker) {
        if (!str_contains($text, $marker)) {
            fwrite(
                STDERR,
                "Missing {$name} marker: {$marker}\n"
            );
            exit(1);
        }
    }
};

/*
 * Submission workflow:
 * approval-required requests may become pending,
 * but submission itself must never dispatch.
 */
$requireMarkers(
    'workflow',
    $source['workflow'],
    [
        'notifications.send.request',
        'NotificationApprovalStateMachine::DRAFT',
        'NotificationApprovalStateMachine::PENDING',
        'createPendingRequest',
        'approval_required',
    ]
);

if (str_contains(
    $source['workflow'],
    'sendDirect('
)) {
    fwrite(
        STDERR,
        "Pending approval submission must not dispatch.\n"
    );
    exit(1);
}

/*
 * Repository concurrency/audit controls.
 */
$requireMarkers(
    'repository',
    $source['repository'],
    [
        'FOR UPDATE',
        'recordDecision',
        'notification_approval_decisions',
        'notification_approval_dispatch_runs',
        "'request_approved'",
        "'request_rejected'",
        "'dispatch_started'",
        "'dispatch_finished'",
        'startDispatch',
        'finishDispatch',
        'lockDispatchRun',
        'destination_masked',
        "a.status_code = 'active'",
        'a.checksum_sha256',
    ]
);

/*
 * Approval management:
 * decision is committed before dispatch starts.
 */
$management = $source['management'];

$approveStart = strpos(
    $management,
    'public function approve('
);

$rejectStart = strpos(
    $management,
    'public function reject('
);

if (
    $approveStart === false
    || $rejectStart === false
    || $rejectStart <= $approveStart
) {
    fwrite(
        STDERR,
        "Approval management method boundaries are invalid.\n"
    );
    exit(1);
}

$approveSource = substr(
    $management,
    $approveStart,
    $rejectStart - $approveStart
);

$decisionPosition = strpos(
    $approveSource,
    '$decision = $this->decide('
);

$dispatchPosition = strpos(
    $approveSource,
    '$dispatch = $this->dispatch->dispatch('
);

if (
    $decisionPosition === false
    || $dispatchPosition === false
    || $decisionPosition >= $dispatchPosition
) {
    fwrite(
        STDERR,
        "Approval decision must complete before dispatch starts.\n"
    );
    exit(1);
}

$nextMethod = strpos(
    $management,
    'private function decide(',
    $rejectStart
);

$rejectSource = substr(
    $management,
    $rejectStart,
    $nextMethod === false
        ? null
        : $nextMethod - $rejectStart
);

if (str_contains(
    $rejectSource,
    'dispatch->dispatch'
)) {
    fwrite(
        STDERR,
        "Rejected approval requests must never dispatch.\n"
    );
    exit(1);
}

$requireMarkers(
    'management',
    $management,
    [
        'notifications.approvals.view',
        'notifications.approvals.decide',
        'assertTransition',
        'lockByReference',
        'lockActiveStep',
        'recordDecision',
        'notification_approval_reject_reason_required',
    ]
);

/*
 * Dispatch workflow.
 */
$dispatch = $source['dispatch'];

$requireMarkers(
    'dispatch',
    $dispatch,
    [
        'isDispatchable',
        'assertTransition',
        'startDispatch',
        'dispatchTargets',
        'mediaAssets',
        'sendDirect',
        'markTargetSent',
        'markTargetFailed',
        'lockDispatchRun',
        'finishDispatch',
        'NotificationApprovalStateMachine::DISPATCHED',
        'NotificationApprovalStateMachine::PARTIALLY_DISPATCHED',
        'NotificationApprovalStateMachine::FAILED',
        'notifications.approvals.decide',
        'notifications.approvals.manage',
    ]
);

/*
 * Gateway delivery attribution belongs to original requester.
 * Approval actor remains dispatch-run auditor.
 */
if (!str_contains(
    $dispatch,
    '$requesterUserId = (int)'
)) {
    fwrite(
        STDERR,
        "Dispatch requester attribution is missing.\n"
    );
    exit(1);
}

if (!str_contains(
    $dispatch,
    "\$this->gateway->sendDirect(\n"
    . "                        \$requesterUserId,"
)) {
    fwrite(
        STDERR,
        "Gateway dispatch must be attributed to requester.\n"
    );
    exit(1);
}

if (!str_contains(
    $dispatch,
    "\$repository->startDispatch(\n"
    . "                            \$request,\n"
    . "                            \$actorUserId,"
)) {
    fwrite(
        STDERR,
        "Dispatch run must remain audited by approving actor.\n"
    );
    exit(1);
}

/*
 * Pending must not be a dispatchable status.
 */
$state = $source['state'];

$dispatchableStart = strpos(
    $state,
    'public function isDispatchable('
);

if ($dispatchableStart === false) {
    fwrite(
        STDERR,
        "State machine isDispatchable method is missing.\n"
    );
    exit(1);
}

$dispatchableEnd = strpos(
    $state,
    "\n    }",
    $dispatchableStart
);

$dispatchableSource = substr(
    $state,
    $dispatchableStart,
    $dispatchableEnd === false
        ? null
        : $dispatchableEnd - $dispatchableStart
);

foreach (
    [
        'self::APPROVED',
        'self::PARTIALLY_DISPATCHED',
        'self::FAILED',
    ] as $marker
) {
    if (!str_contains(
        $dispatchableSource,
        $marker
    )) {
        fwrite(
            STDERR,
            "Dispatchable state missing: {$marker}\n"
        );
        exit(1);
    }
}

if (str_contains(
    $dispatchableSource,
    'self::PENDING'
)) {
    fwrite(
        STDERR,
        "Pending approval must not be dispatchable.\n"
    );
    exit(1);
}

/*
 * Settings access model.
 */
$requireMarkers(
    'settings',
    $source['settings'],
    [
        "'approvals' => [",
        'notifications.approvals.view',
        'NotificationApprovalManagementService',
        'notification_approval_management',
        'notifications.approvals.decide',
    ]
);

/*
 * Dynamic routes, CSRF and service-level decision endpoints.
 */
$requireMarkers(
    'route',
    $source['route'],
    [
        '/admin/communications/settings/approvals/{reference}/approve',
        '/admin/communications/settings/approvals/{reference}/reject',
        'NotificationApprovalManagementService',
        'communicationAccess',
        'new \IPKF\Security\Csrf()',
        'notification_approval_approved_dispatched',
        'notification_approval_rejected',
    ]
);

/*
 * Route registry must independently authorize decisions.
 */
$requireMarkers(
    'migration',
    $source['migration'],
    [
        'notifications.approvals.view',
        'notifications.approvals.decide',
        'approvals/{reference}/approve',
        'approvals/{reference}/reject',
        'notification-approval-queue',
        'section=approvals',
    ]
);

/*
 * Approval UI privacy:
 * only masked destinations are allowed inside this section.
 */
$view = $source['view'];

$approvalViewStart = strpos(
    $view,
    "\$section === 'approvals'"
);

$sendViewStart = strpos(
    $view,
    "\$section === 'send'",
    $approvalViewStart === false
        ? 0
        : $approvalViewStart
);

if (
    $approvalViewStart === false
    || $sendViewStart === false
    || $sendViewStart <= $approvalViewStart
) {
    fwrite(
        STDERR,
        "Approval view section boundaries are invalid.\n"
    );
    exit(1);
}

$approvalView = substr(
    $view,
    $approvalViewStart,
    $sendViewStart - $approvalViewStart
);

$requireMarkers(
    'approval view',
    $approvalView,
    [
        'destination_masked',
        '$approvalCanDecide',
        'تأیید و ارسال',
        'رد درخواست',
        "name=\"reason\"",
        "required",
        'AdminFormat::digits',
    ]
);

if (str_contains(
    $approvalView,
    'destination_snapshot'
)) {
    fwrite(
        STDERR,
        "Approval UI must never expose raw destinations.\n"
    );
    exit(1);
}

echo
    "Notification approval management operational checks passed.\n";
