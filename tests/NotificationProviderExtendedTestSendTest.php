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

$service = $read(
    'public_html/app/Services/'
    . 'NotificationProviderTestService.php'
);
$http = $read(
    'public_html/app/Services/'
    . 'NotificationProviderHttpTransport.php'
);
$repository = $read(
    'public_html/app/Repositories/'
    . 'NotificationProviderManagementRepository.php'
);
$routes = $read(
    'public_html/routes/communication-center.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'EnableNotificationProviderExtendedTestSend.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrate = $read(
    'public_html/public/migrate.php'
);

$expect(
    str_contains($service, "public function send(")
    && str_contains(
        $service,
        "if (\$providerCode === 'kavenegar')"
    )
    && str_contains(
        $service,
        "if (\$providerCode === 'bale_bot')"
    )
    && str_contains(
        $service,
        "'https://api.kavenegar.com/v1/'"
    )
    && str_contains(
        $service,
        "'https://tapi.bale.ai'"
    ),
    'Extended provider test dispatch is incomplete.'
);

$expect(
    str_contains($http, 'curl_init(')
    && str_contains($http, 'postJson(')
    && str_contains($http, 'postForm(')
    && str_contains(
        $http,
        'provider_test_api_timeout'
    ),
    'Provider HTTP transport is incomplete.'
);

$expect(
    str_contains(
        $repository,
        "string \$testKind = 'email'"
    )
    && str_contains(
        $repository,
        "'provider.test_'"
    ),
    'Provider test audit classification is incomplete.'
);

$expect(
    str_contains($routes, "/test-send'")
    && str_contains(
        $routes,
        ")->send(\n"
    ),
    'Extended provider test route is missing.'
);

$expect(
    str_contains(
        $view,
        'data-provider-test-kind'
    )
    && str_contains(
        $view,
        "'provider_test_sms_sent'"
    )
    && str_contains(
        $view,
        "'provider_test_bale_sent'"
    )
    && str_contains(
        $view,
        "endpoint: 'test-send'"
    ),
    'Extended provider test interface is incomplete.'
);

$expect(
    str_contains($migration, '/test-send')
    && str_contains(
        $registry,
        'EnableNotificationProviderExtendedTestSend::class'
    )
    && str_contains(
        $migrate,
        'new \\IPKF\\Database\\Migrations\\EnableNotificationProviderExtendedTestSend()'
    ),
    'Extended provider test migration registration is incomplete.'
);

echo "Extended notification provider test-send checks passed.\n";
