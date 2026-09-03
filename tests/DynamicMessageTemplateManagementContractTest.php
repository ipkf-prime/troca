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

$paths = [
    'renderer' =>
        $root
        . '/public_html/app/Services/'
        . 'DynamicMessageTemplateService.php',

    'service' =>
        $root
        . '/public_html/app/Services/'
        . 'NotificationTemplateManagementService.php',

    'migration' =>
        $root
        . '/public_html/system/Database/Migrations/'
        . 'CreateDynamicMessageTemplateManagement.php',

    'registry' =>
        $root
        . '/public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php',

    'routes' =>
        $root
        . '/public_html/routes/'
        . 'system-message-templates.php',

    'loader' =>
        $root
        . '/public_html/system/Routing/'
        . 'RouteLoader.php',

    'view' =>
        $root
        . '/public_html/resources/views/admin/'
        . 'message-templates.php',

    'rbac' =>
        $root
        . '/public_html/app/Services/'
        . 'AdminNavigationRbacService.php',
];

$source = [];

foreach (
    $paths as $key => $path
) {
    $expect(
        is_readable($path),
        'A2 required file missing: '
            . $key
    );

    $value =
        file_get_contents(
            $path
        );

    $expect(
        is_string($value),
        'A2 file unreadable: '
            . $key
    );

    $source[$key] =
        $value;
}

$expect(
    str_contains(
        $source['migration'],
        'notification_template_definitions'
    )
    && str_contains(
        $source['migration'],
        'allowed_variables_json'
    )
    && str_contains(
        $source['migration'],
        'sample_variables_json'
    ),
    'Template definition metadata is missing.'
);

$expect(
    str_contains(
        $source['migration'],
        'notification_template_change_log'
    )
    && str_contains(
        $source['migration'],
        'snapshot_json'
    )
    && str_contains(
        $source['migration'],
        'actor_user_id'
    ),
    'Template audit foundation is missing.'
);

$expect(
    !str_contains(
        $source['migration'],
        'DELETE FROM notification_templates'
    ),
    'A2 must not physically delete templates.'
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
            $source['migration'],
            $code
        ),
        'Missing A2 definition: '
            . $code
    );
}

$expect(
    str_contains(
        $source['renderer'],
        'public function renderContent('
    )
    && str_contains(
        $source['renderer'],
        "'brand_name'"
    ),
    'Central preview renderer is missing.'
);

$expect(
    str_contains(
        $source['service'],
        'SELECT GET_LOCK(?, 5)'
    )
    && str_contains(
        $source['service'],
        'beginTransaction()'
    )
    && str_contains(
        $source['service'],
        'FOR UPDATE'
    ),
    'Version save serialization contract is missing.'
);

$expect(
    str_contains(
        $source['service'],
        'UPDATE notification_templates'
    )
    && str_contains(
        $source['service'],
        'INSERT INTO'
    )
    && str_contains(
        $source['service'],
        'notification_templates'
    )
    && str_contains(
        $source['service'],
        '$newVersion'
    ),
    'Versioned template save contract is missing.'
);

$expect(
    str_contains(
        $source['service'],
        'message_template_unknown_variable:'
    )
    && str_contains(
        $source['service'],
        'message_template_variable_syntax_invalid'
    ),
    'Strict variable allow-list validation is missing.'
);

$expect(
    str_contains(
        $source['service'],
        'NotificationGatewayService'
    )
    && str_contains(
        $source['service'],
        "'template_test'"
    )
    && str_contains(
        $source['service'],
        "'bale' => 'messenger'"
    ),
    'External test-send gateway contract is missing.'
);

foreach (
    [
        '/admin/communications/templates',
        '/admin/communications/templates/save',
        '/admin/communications/templates/preview',
        '/admin/communications/templates/test-send',
    ] as $route
) {
    $expect(
        str_contains(
            $source['routes'],
            $route
        ),
        'A2 route missing: '
            . $route
    );
}

$expect(
    str_contains(
        $source['routes'],
        'new \IPKF\Security\Csrf()'
    ),
    'A2 CSRF contract is missing.'
);

$expect(
    str_contains(
        $source['view'],
        'متغیرهای مجاز'
    )
    && str_contains(
        $source['view'],
        'ذخیره نسخه جدید'
    )
    && str_contains(
        $source['view'],
        'پیش‌نمایش'
    )
    && str_contains(
        $source['view'],
        'ارسال آزمایشی'
    )
    && str_contains(
        $source['view'],
        'تاریخچه نسخه‌ها'
    ),
    'A2 management UI contract is incomplete.'
);

$expect(
    str_contains(
        $source['service'],
        'channel_supports_subject'
    )
    && str_contains(
        $source['service'],
        'supports_subject'
    ),
    'Channel subject capability contract is missing.'
);

$expect(
    str_contains(
        $source['view'],
        'AdminFormat::digits'
    )
    && str_contains(
        $source['view'],
        'AdminFormat::jalaliDateTime'
    ),
    'Persian number/date presentation contract is missing.'
);

$expect(
    str_contains(
        $source['view'],
        '$supportsSubject'
    )
    && str_contains(
        $source['view'],
        'message-template-filter-form'
    ),
    'A2 UI polish contract is incomplete.'
);

$expect(
    !str_contains(
        $source['view'],
        'حذف متن'
    ),
    'System template delete UI must not exist.'
);

$expect(
    str_contains(
        $source['registry'],
        'CreateDynamicMessageTemplateManagement::class'
    ),
    'A2 migration is not registered.'
);

$expect(
    str_contains(
        $source['loader'],
        'routes/system-message-templates.php'
    ),
    'A2 route file is not registered.'
);

$expect(
    str_contains(
        $source['rbac'],
        "'/admin/communications/templates'"
    )
    && str_contains(
        $source['rbac'],
        "'notifications.send.manage'"
    ),
    'A2 static RBAC fallback is missing.'
);

echo
    "DYNAMIC_MESSAGE_TEMPLATE_MANAGEMENT_CONTRACT=PASS\n";
