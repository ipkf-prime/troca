<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$source = [
    'policy' => file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'SmsDeliveryPolicyService.php'
    ),

    'settings' => file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'CommunicationSettingsService.php'
    ),

    'route' => file_get_contents(
        $root
        . '/public_html/routes/'
        . 'communication-center.php'
    ),

    'view' => file_get_contents(
        $root
        . '/public_html/resources/views/admin/'
        . 'communication-settings.php'
    ),

    'identity' => file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'IdentityOtpDeliveryService.php'
    ),

    'registration' => file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'PublicRegistrationOtpService.php'
    ),

    'mfa' => file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'MfaDeliveryChannelService.php'
    ),

    'gateway' => file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'NotificationGatewayService.php'
    ),

    'adapter' => file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'NotificationKavenegarGatewayAdapter.php'
    ),
];

foreach ($source as $name => $content) {
    if (!is_string($content)) {
        throw new RuntimeException(
            'Source unavailable: '
            . $name
        );
    }
}

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
        $source['policy'],
        "'communications.sms_policy'"
    ),
    'SMS policy namespace missing.'
);

$expect(
    str_contains(
        $source['policy'],
        "'07:00'"
    )
    && str_contains(
        $source['policy'],
        "'22:00'"
    ),
    'Default SMS delivery window missing.'
);

$expect(
    str_contains(
        $source['policy'],
        "Clock::displayTimezoneName()"
    )
    && str_contains(
        $source['policy'],
        "Clock::displayTimezone()"
    ),
    'Central display timezone policy missing.'
);

$expect(
    str_contains(
        $source['policy'],
        "'all_day'"
    )
    && str_contains(
        $source['policy'],
        "'next_allowed_at'"
    )
    && str_contains(
        $source['policy'],
        "'next_allowed_utc'"
    ),
    '24-hour/next delivery decision contract missing.'
);

$expect(
    str_contains(
        $source['settings'],
        "'sms_policy'"
    )
    && str_contains(
        $source['settings'],
        'saveSmsPolicy('
    )
    && str_contains(
        $source['settings'],
        "['routing']"
    ),
    'Communication settings SMS policy integration missing.'
);

$expect(
    str_contains(
        $source['route'],
        '/admin/communications/settings/sms-policy'
    )
    && str_contains(
        $source['route'],
        'saveSmsPolicy('
    )
    && str_contains(
        $source['route'],
        'invalid_csrf'
    ),
    'SMS policy management route missing.'
);

$expect(
    str_contains(
        $source['view'],
        'data-sms-policy-card'
    )
    && str_contains(
        $source['view'],
        'ارسال پیامک به‌صورت ۲۴ ساعته'
    )
    && str_contains(
        $source['view'],
        'start_time'
    )
    && str_contains(
        $source['view'],
        'end_time'
    ),
    'SMS policy admin UI missing.'
);

$expect(
    str_contains(
        $source['identity'],
        'SMS_POLICY_IDENTITY_OTP_GATE_V1'
    )
    && str_contains(
        $source['identity'],
        'SmsDeliveryPolicyService'
    ),
    'Identity OTP SMS gate missing.'
);

$registrationPolicy =
    strpos(
        $source['registration'],
        'SMS_POLICY_REGISTRATION_GATE_V1'
    );

$registrationChallenge =
    strpos(
        $source['registration'],
        'createChallenge(['
    );

$expect(
    $registrationPolicy !== false
    && $registrationChallenge !== false
    && $registrationPolicy
        < $registrationChallenge,
    'Registration policy must precede challenge creation.'
);

$mfaPolicy =
    strpos(
        $source['mfa'],
        'SMS_POLICY_MFA_GATE_V1'
    );

$mfaRate =
    strpos(
        $source['mfa'],
        'allowedByRateLimit('
    );

$expect(
    $mfaPolicy !== false
    && $mfaRate !== false
    && $mfaPolicy < $mfaRate,
    'MFA SMS policy must precede rate-limit consumption.'
);

$gatewayPolicy =
    strpos(
        $source['gateway'],
        'SMS_POLICY_GATEWAY_GATE_V1'
    );

$gatewayTracking =
    strpos(
        $source['gateway'],
        'createDirectDelivery('
    );

$expect(
    $gatewayPolicy !== false
    && $gatewayTracking !== false
    && $gatewayPolicy
        < $gatewayTracking,
    'Gateway policy must precede delivery tracking.'
);

$expect(
    str_contains(
        $source['adapter'],
        'SMS_POLICY_KAVENEGAR_DEFENCE_V1'
    )
    && str_contains(
        $source['adapter'],
        'notification_gateway_sms_window_closed'
    ),
    'Kavenegar adapter policy defence missing.'
);

$expect(
    !str_contains(
        $source['policy'],
        'KAVENEGAR_API_KEY'
    )
    && !str_contains(
        $source['policy'],
        'KAVENEGAR_SENDER'
    ),
    'Business delivery-window policy must not own provider secrets.'
);

echo "SMS_DELIVERY_POLICY_CONTRACT=PASS"
    . PHP_EOL;
