<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $relative
) use ($root): string {
    $value = file_get_contents(
        $root . '/' . $relative
    );

    if (!is_string($value)) {
        throw new RuntimeException(
            'Missing ' . $relative
        );
    }

    return $value;
};

$presence = $read(
    'public_html/app/Services/'
    . 'OnlinePresenceService.php'
);

$migration = $read(
    'public_html/system/Database/'
    . 'Migrations/'
    . 'CreatePublicRuntimePresence.php'
);

$service = $read(
    'public_html/app/Services/'
    . 'PublicLandingService.php'
);

$layout = $read(
    'public_html/resources/views/admin/'
    . 'layout.php'
);

$landing = $read(
    'public_html/resources/views/site/'
    . 'landing.php'
);

$admin = $read(
    'public_html/resources/views/admin/'
    . 'public-page.php'
);

$css = $read(
    'public_html/public/assets/css/'
    . 'public-landing.css'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
};

$expect(
    str_contains(
        $presence,
        'online_user_presence'
    )
    && str_contains(
        $presence,
        "resolve('core.primary')"
    )
    && str_contains(
        $presence,
        'ONLINE_WINDOW_MINUTES = 5'
    ),
    'Real online presence service missing.'
);

$expect(
    str_contains(
        $migration,
        'CreatePublicRuntimePresence'
    )
    && str_contains(
        $migration,
        'runtime_online_position'
    ),
    'Presence migration/layout defaults missing.'
);

$expect(
    !str_contains(
        $service,
        "'online_users' => null"
    )
    && str_contains(
        $service,
        'runtimeSlots'
    ),
    'Landing still uses fake/empty online runtime.'
);

$expect(
    str_contains(
        $layout,
        'ONLINE_PRESENCE_TOUCH_V1'
    ),
    'Authenticated activity touch missing.'
);

$expect(
    str_contains(
        $landing,
        "['right', 'center', 'left']"
    )
    && str_contains(
        $landing,
        'runtime-strip__zone--<?= landing_h('
    )
    && str_contains(
        $landing,
        '$runtimeSlots[$runtimeZone]'
    ),
    'Three-zone runtime layout missing.'
);

$expect(
    str_contains(
        $admin,
        '$runtimePositionFields'
    )
    && str_contains(
        $admin,
        "'runtime_online_position'"
    )
    && str_contains(
        $admin,
        '$runtimePositionOptions'
    )
    && str_contains(
        $admin,
        'چیدمان نوار وضعیت'
    )
    && str_contains(
        $admin,
        'name="<?= landing_admin_h('
    ),
    'Runtime placement management missing.'
);

$expect(
    str_contains(
        $css,
        'RUNTIME THREE-ZONE V4'
    ),
    'Three-zone CSS missing.'
);

echo "PUBLIC_RUNTIME_PRESENCE_CONTRACT=PASS\n";
