<?php
declare(strict_types=1);

namespace IPKF\Support {
    /** Test-only environment double. This script cannot read actual .env. */
    final class Env
    {
        public static array $values = [];
        public static function get(string $key, mixed $default = null): mixed
        {
            return self::$values[$key] ?? $default;
        }
    }
}

namespace {
    use App\Services\BaleSafirCampaignService;
    use IPKF\Support\Env;

    $repoRoot = isset($argv[1]) && is_dir($argv[1]) ? rtrim($argv[1], '/') : dirname(__DIR__);
    $root = is_file($repoRoot . '/public_html/app/Services/BaleSafirCampaignService.php')
        ? $repoRoot . '/public_html/'
        : $repoRoot . '/';
    require $root . 'app/Services/BaleSafirCampaignService.php';

    function gate(bool $pass, string $name): void
    {
        if (!$pass) {
            throw new \RuntimeException('FAIL=' . $name);
        }
        echo $name . "=PASS\n";
    }

    $service = new BaleSafirCampaignService();
    $method = new \ReflectionMethod(BaleSafirCampaignService::class, 'manualTest');
    gate($method->getNumberOfRequiredParameters() === 4, 'FOUR_ARGUMENT_SERVER_BOUND_METHOD');
    gate(
        array_map(static fn ($p) => $p->getName(), $method->getParameters())
            === ['userId', 'templateCode', 'inputFields', 'nonce'],
        'NO_CLIENT_RECIPIENT_PARAMETER'
    );

    $code = file_get_contents($root . 'app/Services/BaleSafirCampaignService.php');
    $route = file_get_contents($root . 'routes/bale-safir-campaigns.php');
    $view = file_get_contents($root . 'resources/views/admin/partials/bale-safir-campaign.php');

    gate(is_string($route) && !str_contains($route, "input('test_phone'"), 'ROUTE_IGNORES_CLIENT_PHONE');
    gate(is_string($view) && !str_contains($view, 'name="test_phone"'), 'UI_DOES_NOT_COLLECT_PHONE');
    gate(is_string($view) && str_contains($view, 'test_phone_masked')
        && str_contains($view, 'مقصد ثابت ارسال آزمایشی'), 'ONLY_MASKED_SERVER_PHONE_DISPLAYED');
    gate(is_string($view) && str_contains($view, 'confirm_manual_test'), 'REAL_SEND_EXPLICIT_CONFIRMATION_PRESERVED');
    gate(is_string($route) && str_contains($route, "in_array(\$action, ['test', 'manual_test', 'dispatch'], true) && !\$maySend"), 'PROVIDER_SEND_PERMISSION_GATE_PRESERVED');
    gate(is_string($code) && str_contains($code, "Env::get('BALE_SAFIR_TEST_PHONE', '')")
        && str_contains($code, '$this->sendOne($allowedPhone,')
        && str_contains($code, '$this->maskPhone($allowedPhone)')
        && !str_contains($code, '$requestedPhone'),
        'SEND_AND_AUDIT_USE_SERVER_ALLOWLIST_ONLY');

    $test = static function (array $flags, string $expected) use ($service): void {
        Env::$values = $flags;
        $error = null;
        try {
            $service->manualTest(1, 'invite', ['name' => 'نمونه'], str_repeat('a', 32));
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }
        gate($error === $expected, 'NO_SEND_' . strtoupper($expected));
    };

    $test([], 'safir_manual_test_disabled');
    $test([
        'BALE_SAFIR_TEST_SEND_ENABLED' => true,
        'APP_ENV' => 'production',
        'BALE_SAFIR_TEST_PRODUCTION_APPROVED' => false,
    ], 'safir_manual_test_disabled');
    $test([
        'BALE_SAFIR_TEST_SEND_ENABLED' => true,
        'APP_ENV' => 'production',
        'BALE_SAFIR_TEST_PRODUCTION_APPROVED' => true,
        'BALE_SAFIR_API_ACCESS_KEY' => '',
    ], 'safir_key_missing');
    $test([
        'BALE_SAFIR_TEST_SEND_ENABLED' => true,
        'APP_ENV' => 'production',
        'BALE_SAFIR_TEST_PRODUCTION_APPROVED' => true,
        'BALE_SAFIR_API_ACCESS_KEY' => 'test-only-key',
        'BALE_SAFIR_TEST_PHONE' => '',
    ], 'safir_test_phone_not_configured');
    $test([
        'BALE_SAFIR_TEST_SEND_ENABLED' => true,
        'APP_ENV' => 'production',
        'BALE_SAFIR_TEST_PRODUCTION_APPROVED' => true,
        'BALE_SAFIR_API_ACCESS_KEY' => 'test-only-key',
        'BALE_SAFIR_TEST_PHONE' => 'bad-phone',
    ], 'safir_test_phone_not_configured');

    echo "SAFIR_V244_SERVER_BOUND_RECIPIENT=PASS\n";
    echo "DATABASE_MUTATION=NO\nPROVIDER_SEND=NO\n";
}
