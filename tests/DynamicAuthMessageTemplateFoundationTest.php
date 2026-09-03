<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

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

$migrationPath =
    $root
    . '/public_html/system/Database/Migrations/'
    . 'SeedDynamicAuthMembershipMessageTemplates.php';

$servicePath =
    $root
    . '/public_html/app/Services/'
    . 'DynamicMessageTemplateService.php';

$deliveryPath =
    $root
    . '/public_html/app/Services/'
    . 'IdentityOtpDeliveryService.php';

$registryPath =
    $root
    . '/public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php';

foreach (
    [
        $migrationPath,
        $servicePath,
        $deliveryPath,
        $registryPath,
    ] as $file
) {
    $expect(
        is_readable($file),
        'Required dynamic message file missing: '
            . $file
    );
}

$migration =
    file_get_contents(
        $migrationPath
    );

$service =
    file_get_contents(
        $servicePath
    );

$delivery =
    file_get_contents(
        $deliveryPath
    );

$registry =
    file_get_contents(
        $registryPath
    );

$expect(
    is_string($migration)
    && is_string($service)
    && is_string($delivery)
    && is_string($registry),
    'Dynamic message source unreadable.'
);

foreach (
    [
        'auth.identity.mobile_verification',
        'auth.identity.email_verification',
        'auth.registration.mobile_otp',
        'auth.password_reset.mobile_otp',
        'auth.bale.enrollment',
        'membership.request.received',
        'membership.request.approved',
        'membership.request.rejected',
        'membership.role.changed',
        'membership.revoked',
        'membership.restored',
    ] as $code
) {
    $expect(
        str_contains(
            $migration,
            $code
        ),
        'Missing dynamic template: '
            . $code
    );
}

$expect(
    str_contains(
        $service,
        'FROM notification_templates'
    )
    && str_contains(
        $service,
        'is_active = 1'
    )
    && str_contains(
        $service,
        'ORDER BY version DESC'
    ),
    'Dynamic template lookup contract missing.'
);

$expect(
    str_contains(
        $migration,
        "'in_app'"
    )
    && !str_contains(
        $migration,
        "'internal',"
    ),
    'Membership templates must use the canonical in_app channel.'
);

$expect(
    str_contains(
        $service,
        'FROM notification_channels'
    )
    && str_contains(
        $service,
        'notification_channels.is_active = 1'
    ),
    'Template lookup must reject inactive or unknown channels.'
);

$expect(
    str_contains(
        $service,
        'message_template_variable_missing'
    )
    && str_contains(
        $service,
        'brand_name'
    ),
    'Strict variable rendering contract missing.'
);

$expect(
    str_contains(
        $delivery,
        'DynamicMessageTemplateService'
    )
    && str_contains(
        $delivery,
        'auth.identity.mobile_verification'
    )
    && str_contains(
        $delivery,
        'auth.identity.email_verification'
    ),
    'Identity OTP is not template-driven.'
);

foreach (
    [
        'کد تأیید IPKF',
        'کد تأیید حساب IPKF',
        'کد تأیید شما: {$code}',
    ] as $legacy
) {
    $expect(
        !str_contains(
            $delivery,
            $legacy
        ),
        'Legacy hardcoded OTP copy remains.'
    );
}

$expect(
    str_contains(
        $registry,
        'SeedDynamicAuthMembershipMessageTemplates::class'
    ),
    'Dynamic message migration is not registered.'
);

echo
    "DYNAMIC_AUTH_MESSAGE_TEMPLATE_FOUNDATION=PASS\n";
