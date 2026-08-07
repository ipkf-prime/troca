<?php

$root = dirname(__DIR__);

$repositoryPath =
    $root
    . '/public_html/app/Repositories/'
    . 'NotificationApprovalRepository.php';

$servicePath =
    $root
    . '/public_html/app/Services/'
    . 'NotificationApprovalWorkflowService.php';

$sendCenterPath =
    $root
    . '/public_html/app/Services/'
    . 'NotificationSendCenterService.php';

$routePath =
    $root
    . '/public_html/routes/'
    . 'communication-center.php';

$viewPath =
    $root
    . '/public_html/resources/views/admin/'
    . 'communication-settings.php';

$repository = file_get_contents(
    $repositoryPath
);

$service = file_get_contents(
    $servicePath
);

$sendCenter = file_get_contents(
    $sendCenterPath
);

$route = file_get_contents(
    $routePath
);

$view = file_get_contents(
    $viewPath
);

foreach (
    [
        'repository' => $repository,
        'service' => $service,
        'send center' => $sendCenter,
        'route' => $route,
        'view' => $view,
    ] as $name => $source
) {
    if (!is_string($source)) {
        fwrite(
            STDERR,
            "Notification approval {$name} source is missing.\n"
        );
        exit(1);
    }
}

foreach (
    [
        'notification_approval_requests',
        'notification_approval_targets',
        'notification_approval_steps',
        'notification_approval_media_links',
        'notification_approval_events',
    ] as $table
) {
    if (
        !str_contains(
            $repository,
            $table
        )
    ) {
        fwrite(
            STDERR,
            "Operational repository does not persist {$table}.\n"
        );
        exit(1);
    }
}

foreach (
    [
        'beginTransaction()',
        'commit()',
        'rollBack()',
        "'request_created'",
        "'request_submitted'",
        "'draft'",
        "'pending'",
    ] as $marker
) {
    if (
        !str_contains(
            $repository,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Missing transactional/audit marker: {$marker}\n"
        );
        exit(1);
    }
}

foreach (
    [
        'notifications.send.request',
        'notifications.approvals.decide',
        'NotificationApprovalStateMachine::DRAFT',
        'NotificationApprovalStateMachine::PENDING',
        'payload_checksum_sha256',
        'destination_hash',
        'approval_required',
        'idempotency_key',
        'createPendingRequest',
    ] as $marker
) {
    if (
        !str_contains(
            $service,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Missing operational workflow marker: {$marker}\n"
        );
        exit(1);
    }
}

if (
    str_contains(
        $service,
        'sendDirect('
    )
) {
    fwrite(
        STDERR,
        "Approval submission must not dispatch directly.\n"
    );
    exit(1);
}

foreach (
    [
        'NotificationApprovalWorkflowService',
        "\$policy === 'approval_required'",
        'approval->submit',
        'media->cleanup',
        "'pending_approval'",
        'sendDirect(',
    ] as $marker
) {
    if (
        !str_contains(
            $sendCenter,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Missing send-center approval marker: {$marker}\n"
        );
        exit(1);
    }
}

if (
    str_contains(
        $sendCenter,
        'notification_send_approval_required'
    )
) {
    fwrite(
        STDERR,
        "Approval-required users must not be rejected by send center.\n"
    );
    exit(1);
}

$approvalPosition = strpos(
    $sendCenter,
    'approval->submit'
);

$dispatchPosition = strpos(
    $sendCenter,
    'sendDirect('
);

if (
    $approvalPosition === false
    || $dispatchPosition === false
    || $approvalPosition >= $dispatchPosition
) {
    fwrite(
        STDERR,
        "Approval submission must occur before direct dispatch path.\n"
    );
    exit(1);
}

foreach (
    [
        'workflow_status',
        'pending_approval',
        'notification_send_approval_submitted',
        'notification_send_completed',
    ] as $marker
) {
    if (
        !str_contains(
            $route,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Missing approval route marker: {$marker}\n"
        );
        exit(1);
    }
}

foreach (
    [
        'notification_send_approval_submitted',
        'درخواست ارسال اعلان برای بررسی و تأیید ثبت شد.',
    ] as $marker
) {
    if (
        !str_contains(
            $view,
            $marker
        )
    ) {
        fwrite(
            STDERR,
            "Missing approval UI marker: {$marker}\n"
        );
        exit(1);
    }
}

echo
    "Notification approval operational checks passed.\n";
