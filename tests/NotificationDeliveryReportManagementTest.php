<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: cannot read {$path}\n");
        exit(1);
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationDeliveryReportRepository.php'
);
$service = $read(
    'public_html/app/Services/'
    . 'NotificationDeliveryReportService.php'
);
$settings = $read(
    'public_html/app/Services/'
    . 'CommunicationSettingsService.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);

$expect(
    str_contains(
        $repository,
        'class NotificationDeliveryReportRepository'
    )
    && str_contains(
        $repository,
        'public function page(array $filters)'
    )
    && str_contains(
        $repository,
        'notification_delivery_attempts'
    )
    && str_contains(
        $repository,
        'fallback_count'
    )
    && str_contains(
        $repository,
        'provider_message_reference'
    ),
    'Report repository is incomplete.'
);

$expect(
    str_contains(
        $service,
        'notifications.reports.view'
    )
    && str_contains(
        $service,
        'sanitizeMetadata'
    )
    && str_contains(
        $service,
        'PersianDate::toGregorianDate'
    ),
    'Report service is incomplete.'
);

$expect(
    str_contains(
        $settings,
        'NotificationDeliveryReportService'
    )
    && str_contains(
        $settings,
        "'delivery_report'"
    )
    && str_contains(
        $routes,
        "'provider' =>"
    )
    && str_contains(
        $routes,
        "'per_page' =>"
    ),
    'Settings and route integration are incomplete.'
);

$expect(
    str_contains(
        $view,
        'notification-delivery-report-v061'
    )
    && str_contains(
        $view,
        'data-notification-report-toggle'
    )
    && str_contains(
        $view,
        'provider_message_reference'
    )
    && str_contains(
        $view,
        'گزارش یکپارچه ارسال‌ها'
    )
    && str_contains(
        $style,
        'notification-delivery-report-style-v061'
    ),
    'Report UI is incomplete.'
);

echo "Notification delivery report checks passed.\n";
