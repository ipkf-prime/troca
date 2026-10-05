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
            fwrite(
                STDERR,
                "FAIL={$message}\n"
            );

            exit(1);
        }
    };

$read =
    static function (
        string $path
    ) use (
        $root
    ): string {
        $content =
            file_get_contents(
                $root
                . '/'
                . $path
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Unreadable file: '
                . $path
            );
        }

        return $content;
    };

$migration =
    $read(
        'public_html/system/Database/Migrations/'
        . 'SeedPasswordRecoveryEmailBaleTemplates.php'
    );

$registry =
    $read(
        'public_html/system/Database/Application/'
        . 'ApplicationMigrationRegistry.php'
    );

$delivery =
    $read(
        'public_html/app/Services/'
        . 'IdentityOtpDeliveryService.php'
    );

$recovery =
    $read(
        'public_html/app/Services/'
        . 'PasswordRecoveryService.php'
    );

$deployment =
    $read(
        'docs/deployment/'
        . 'password-recovery.md'
    );

$expect(
    str_contains(
        $migration,
        'TEMPLATES_JSON'
    )
    && str_contains(
        $migration,
        'JSON_THROW_ON_ERROR'
    ),
    'Exact escaped template payload contract missing'
);

$expect(
    str_contains(
        $migration,
        'auth.password_reset.email_otp'
    )
    && str_contains(
        $migration,
        'auth.password_reset.bale_otp'
    ),
    'Recovery template codes missing from source persistence'
);

$expect(
    str_contains(
        $migration,
        'event_type'
    )
    && str_contains(
        $migration,
        'INSERT IGNORE INTO notification_templates'
    ),
    'Recovery runtime-template persistence contract incomplete'
);

$expect(
    !str_contains(
        $migration,
        'notification_template_definitions'
    ),
    'B1 must not fabricate missing management definitions'
);

$createPosition =
    strpos(
        $registry,
        'CreateDynamicMessageTemplateManagement::class'
    );

$seedPosition =
    strpos(
        $registry,
        'SeedPasswordRecoveryEmailBaleTemplates::class'
    );

$expect(
    $createPosition !== false
    && $seedPosition !== false
    && $seedPosition > $createPosition,
    'Recovery template migration registration order invalid'
);

$expect(
    str_contains(
        $delivery,
        'PASSWORD_RECOVERY_SMS_ENABLED'
    ),
    'Recovery SMS feature flag is not consumed'
);

$expect(
    str_contains(
        $deployment,
        'PASSWORD_RECOVERY_SMS_ENABLED=true'
    ),
    'Recovery SMS deployment contract missing'
);

$expect(
    str_contains(
        $recovery,
        'PASSWORD_RECOVERY_BALE_SECRET_FILE'
    ),
    'Dedicated recovery Bale secret pointer missing'
);

$expect(
    !str_contains(
        $recovery,
        'BALE_BOT_TOKEN'
    ),
    'Legacy/general Bale token used by password recovery'
);

$expect(
    !str_contains(
        $recovery,
        'PASSWORD_RECOVERY_EMAIL_ENABLED'
    )
    && !str_contains(
        $delivery,
        'PASSWORD_RECOVERY_EMAIL_ENABLED'
    )
    && !str_contains(
        $deployment,
        'PASSWORD_RECOVERY_EMAIL_ENABLED=true'
    ),
    'Unwanted recovery email feature flag introduced'
);

$expect(
    str_contains(
        $deployment,
        'post-production hardening'
    ),
    'Definition hardening deferral not recorded'
);

echo
    "PASSWORD_RECOVERY_PRODUCTION_CLOSURE_CONTRACT=PASS\n";
