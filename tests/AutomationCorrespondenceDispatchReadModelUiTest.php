<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$service =
    file_get_contents(
        $root
        . '/public_html/app/Services/Automation/'
        . 'Correspondence/'
        . 'CorrespondenceDispatchReadModelService.php'
    );

$routes =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

$view =
    file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'automation-correspondence-detail.php'
    );

foreach ([
    'service' => $service,
    'routes' => $routes,
    'view' => $view,
] as $name => $source) {
    if (!is_string($source)) {
        throw new RuntimeException(
            "D6B {$name} source unavailable."
        );
    }
}


foreach ([
    'class CorrespondenceDispatchReadModelService',
    'public function forCorrespondence(',
    'correspondence_dispatches',
    'correspondence_dispatch_attempts',
    "'primary_recipient'",
    "'retryable'",
    "'needs_review'",
    'وضعیت نامشخص — نیازمند بررسی',
] as $required) {
    if (!str_contains(
        $service,
        $required
    )) {
        throw new RuntimeException(
            'D6B read model missing: '
            . $required
        );
    }
}


foreach ([
    'INSERT INTO',
    'UPDATE correspond',
    'DELETE FROM',
    'REPLACE INTO',
    'beginTransaction(',
    'commit(',
    'rollBack(',
    'CorrespondenceDispatchAttemptService',
    'CorrespondenceDispatchCompletionService',
    'CorrespondenceDispatchAggregateCompletionService',
    'NotificationGateway',
    'NotificationSmtp',
    'sendDirect',
    'curl_',
    'fsockopen',
    'stream_socket_client',
] as $forbidden) {
    if (str_contains(
        $service,
        $forbidden
    )) {
        throw new RuntimeException(
            'Read-model boundary violation: '
            . $forbidden
        );
    }
}


if (
    substr_count(
        $routes,
        'CorrespondenceDispatchReadModelService'
    ) !== 1
    ||
    !str_contains(
        $routes,
        "'dispatch_monitor'"
    )
) {
    throw new RuntimeException(
        'Detail GET wiring invalid.'
    );
}


foreach ([
    'data-dispatch-read-model',
    'رهگیری ارسال',
    'درخواست',
    'تلاش',
    'تعداد تلاش‌ها',
    'آخرین تلاش',
    'زمان درخواست',
    'تکمیل آخرین تلاش',
    'زمان ارسال',
    'کد رهگیری',
    'وضعیت نامشخص — نیازمند بررسی',
    'قابل تلاش مجدد',
    'هنوز درخواست ارسالی برای این',
] as $required) {
    if (!str_contains(
        $view,
        $required
    )) {
        throw new RuntimeException(
            'D6B UI missing: '
            . $required
        );
    }
}


$start =
    strpos(
        $view,
        'data-dispatch-read-model'
    );

$end =
    strpos(
        $view,
        "<?php elseif (\$activeTab === 'relations'): ?>",
        $start !== false
            ? $start
            : 0
    );

if (
    $start === false
    ||
    $end === false
    ||
    $end <= $start
) {
    throw new RuntimeException(
        'D6B monitor boundary invalid.'
    );
}


$fragment =
    substr(
        $view,
        $start,
        $end - $start
    );


foreach ([
    '<form',
    'type="submit"',
    'CorrespondenceDispatchAttemptService',
    'CorrespondenceDispatchCompletionService',
    'CorrespondenceDispatchAggregateCompletionService',
    'completeSuccess(',
    'completeIfReady(',
] as $forbidden) {
    if (str_contains(
        $fragment,
        $forbidden
    )) {
        throw new RuntimeException(
            'D6B monitor contains action: '
            . $forbidden
        );
    }
}


echo "Automation correspondence D6B read-only monitor checks passed.\n";
