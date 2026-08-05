<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

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

$send = $read(
    'public_html/app/Services/'
    . 'NotificationSendCenterService.php'
);
$gateway = $read(
    'public_html/app/Services/'
    . 'NotificationGatewayService.php'
);
$smtp = $read(
    'public_html/app/Services/'
    . 'NotificationSmtpTransport.php'
);
$bale = $read(
    'public_html/app/Services/'
    . 'NotificationBaleGatewayAdapter.php'
);
$http = $read(
    'public_html/app/Services/'
    . 'NotificationProviderHttpTransport.php'
);
$route = $read(
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
    !str_contains(
        $send,
        'notification_send_multimedia_delivery_pending'
    )
    && str_contains(
        $send,
        'NotificationMediaUploadService'
    )
    && str_contains($gateway, "'media_assets'"),
    'Multimedia send path is incomplete.'
);

$expect(
    str_contains($smtp, 'multipart/mixed')
    && str_contains($bale, 'sendPhoto')
    && str_contains($bale, 'sendDocument')
    && str_contains($http, 'postMultipart'),
    'Multimedia adapters are incomplete.'
);

$expect(
    str_contains(
        $route,
        "\$_FILES['media_files']"
    )
    && str_contains(
        $view,
        'notification_send_media_required'
    )
    && str_contains(
        $style,
        'notification-multimedia-delivery-core-v061'
    ),
    'Multimedia route or form is incomplete.'
);

echo "Notification multimedia delivery core checks passed.\n";
