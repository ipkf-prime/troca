<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once
    $root
    . '/public_html/system/Support/Env.php';

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$temp =
    sys_get_temp_dir()
    . '/ipkf-profile-'
    . bin2hex(random_bytes(6));

mkdir($temp, 0700, true);

try {
    $shared = $temp . '/shared.env';
    $local = $temp . '/local.env';

    file_put_contents(
        $shared,
        implode(PHP_EOL, [
            'APP_ENV=development',
            'DEPLOYMENT_ID=customer_prod',
            'TICKETING_APP_URL=https://ticketing.example.test',
            'AUTH_SESSION_LIFETIME=7200',
            'AUTH_COOKIE_DOMAIN=',
            'AUTH_COOKIE_SECURE=true',
            'AUTH_COOKIE_HTTPONLY=true',
            'AUTH_COOKIE_SAMESITE=Lax',
            '',
        ])
    );

    file_put_contents(
        $local,
        implode(PHP_EOL, [
            'IPKF_SHARED_ENV=' . $shared,
            'IPKF_MODULE=ticketing',
            '',
        ])
    );

    \IPKF\Support\Env::loadLayered($local);

    $expected = [
        'DEPLOYMENT_ID' => 'customer_prod',
        'IPKF_MODULE' => 'ticketing',
        'APP_URL' => 'https://ticketing.example.test',
        'AUTH_SESSION_NAME' => 'customer_prod_ticketing',
        'AUTH_SESSION_LIFETIME' => '7200',
        'AUTH_COOKIE_DOMAIN' => '',
        'AUTH_COOKIE_SECURE' => 'true',
        'AUTH_COOKIE_HTTPONLY' => 'true',
        'AUTH_COOKIE_SAMESITE' => 'Lax',
    ];

    foreach ($expected as $key => $value) {
        $actual = (string) \IPKF\Support\Env::get($key, '');

        $expect(
            hash_equals($value, $actual),
            $key
            . ' expected='
            . var_export($value, true)
            . ' actual='
            . var_export($actual, true)
        );
    }

    echo "DEPLOYMENT_PROFILE_FOUNDATION_PASS\n";

} finally {
    @unlink($temp . '/local.env');
    @unlink($temp . '/shared.env');
    @rmdir($temp);
}
