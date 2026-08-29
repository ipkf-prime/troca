<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

require_once
    $root
    . '/public_html/system/Support/Env.php';


$assert =
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


$temp =
    sys_get_temp_dir()
    . '/ipkf-module-runtime-isolation-'
    . bin2hex(
        random_bytes(8)
    );

if (!mkdir($temp, 0700, true)) {
    throw new RuntimeException(
        'Cannot create temporary test directory.'
    );
}


try {

    $shared =
        $temp
        . '/shared.env';

    $local =
        $temp
        . '/local.env';


    file_put_contents(
        $shared,
        implode(
            PHP_EOL,
            [
                'APP_ENV=development',
                'APP_DEBUG=true',
                'TICKETING_APP_URL=https://shared.example.test',
                'TICKETING_DB_HOST=shared-db',
                'TICKETING_DB_PORT=3306',
                'TICKETING_DB_DATABASE=shared_ticketing',
                'TICKETING_DB_USERNAME=shared_user',
                'TICKETING_DB_PASSWORD=shared_password',
                'TICKETING_DB_CHARSET=utf8mb4',
                'NON_MODULE_SECRET=shared-only',
                '',
            ]
        )
    );


    file_put_contents(
        $local,
        implode(
            PHP_EOL,
            [
                'IPKF_SHARED_ENV=' . $shared,
                'IPKF_MODULE=ticketing',
                'IPKF_MODULE_RUNTIME_OVERRIDE=true',
                'APP_ENV=production',
                'APP_DEBUG=false',
                'TICKETING_APP_URL=https://ticketing.example.test',
                'TICKETING_DB_HOST=prod-db',
                'TICKETING_DB_PORT=3307',
                'TICKETING_DB_DATABASE=prod_ticketing',
                'TICKETING_DB_USERNAME=prod_user',
                'TICKETING_DB_PASSWORD=prod_password',
                '',
            ]
        )
    );


    \IPKF\Support\Env::loadLayered(
        $local
    );


    $assert(
        \IPKF\Support\Env::moduleRuntimeOverrideEnabled(
            'ticketing'
        ),
        'Ticketing runtime override must be enabled.'
    );


    $assert(
        !\IPKF\Support\Env::moduleRuntimeOverrideEnabled(
            'work'
        ),
        'Runtime override must be limited to current module.'
    );


    foreach ([
        'APP_ENV' =>
            'production',

        'APP_DEBUG' =>
            'false',

        'APP_URL' =>
            'https://ticketing.example.test',

        'TICKETING_APP_URL' =>
            'https://ticketing.example.test',

        'TICKETING_DB_HOST' =>
            'prod-db',

        'TICKETING_DB_PORT' =>
            '3307',

        'TICKETING_DB_DATABASE' =>
            'prod_ticketing',

        'TICKETING_DB_USERNAME' =>
            'prod_user',

        'TICKETING_DB_PASSWORD' =>
            'prod_password',

        'TICKETING_DB_CHARSET' =>
            'utf8mb4',

        'NON_MODULE_SECRET' =>
            'shared-only',
    ] as $key => $expected) {

        $actual =
            (string) \IPKF\Support\Env::get(
                $key,
                ''
            );

        $assert(
            hash_equals(
                $expected,
                $actual
            ),
            'Unexpected layered value for '
            . $key
            . ': '
            . $actual
        );
    }


    $connection =
        file_get_contents(
            $root
            . '/public_html/system/Database/Connections/'
            . 'ConnectionRegistry.php'
        );

    $urls =
        file_get_contents(
            $root
            . '/public_html/system/Support/'
            . 'ApplicationUrlRegistry.php'
        );


    $assert(
        is_string($connection)
        &&
        str_contains(
            $connection,
            'Env::moduleRuntimeOverrideEnabled('
        ),
        'Connection registry override contract missing.'
    );


    $assert(
        is_string($urls)
        &&
        str_contains(
            $urls,
            'Env::moduleRuntimeOverrideEnabled('
        ),
        'Application URL override contract missing.'
    );


    echo "MODULE_RUNTIME_ISOLATION_CONTRACT_PASS\n";

} finally {

    @unlink(
        $temp
        . '/local.env'
    );

    @unlink(
        $temp
        . '/shared.env'
    );

    @rmdir(
        $temp
    );
}
