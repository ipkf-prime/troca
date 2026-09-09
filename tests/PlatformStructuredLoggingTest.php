<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


require_once
    $root
    . '/public_html/system/Logging/RequestContext.php';

require_once
    $root
    . '/public_html/system/Logging/SecretMasker.php';

require_once
    $root
    . '/public_html/system/Logging/SystemLogger.php';

require_once
    $root
    . '/public_html/system/Logging/AuditLogger.php';


use IPKF\Logging\RequestContext;
use IPKF\Logging\SecretMasker;
use IPKF\Logging\SystemLogger;


$fail =
    static function (
        string $message
    ): never {
        fwrite(
            STDERR,
            $message
            . PHP_EOL
        );

        exit(1);
    };


$assert =
    static function (
        bool $condition,
        string $message
    ) use ($fail): void {
        if (!$condition) {
            $fail(
                $message
            );
        }
    };


/*
 * --------------------------------------------------------------------------
 * Request context
 * --------------------------------------------------------------------------
 */

$oldServer =
    $_SERVER;

RequestContext::reset();

$_SERVER[
    'HTTP_X_CORRELATION_ID'
] =
    'corr_12345678';

$_SERVER[
    'REMOTE_ADDR'
] =
    '127.0.0.1';

$_SERVER[
    'HTTP_USER_AGENT'
] =
    'IPKF-Foundation-Test';


RequestContext::initializeFromGlobals();


$assert(
    preg_match(
        '/^req_[a-f0-9]{32}$/',
        RequestContext::requestId()
    ) === 1,
    'Request ID contract failed.'
);


$assert(
    RequestContext::correlationId()
        === 'corr_12345678',
    'Incoming correlation propagation failed.'
);


$assert(
    RequestContext::ip()
        === '127.0.0.1',
    'Request IP capture failed.'
);


/*
 * --------------------------------------------------------------------------
 * Secret masking
 * --------------------------------------------------------------------------
 */

$masked =
    SecretMasker::sanitize(
        [
            'username' =>
                'demo',

            'password' =>
                'VerySecretPassword',

            'csrf_token' =>
                'csrf-value',

            'nested' => [
                'api_key' =>
                    'api-key-value',

                'safe' =>
                    'visible',
            ],
        ]
    );


$assert(
    is_array($masked),
    'Masked payload is invalid.'
);


$assert(
    $masked[
        'password'
    ] === SecretMasker::REDACTED,
    'Password was not redacted.'
);


$assert(
    $masked[
        'csrf_token'
    ] === SecretMasker::REDACTED,
    'CSRF token was not redacted.'
);


$assert(
    $masked[
        'nested'
    ][
        'api_key'
    ] === SecretMasker::REDACTED,
    'API key was not redacted.'
);


$assert(
    $masked[
        'nested'
    ][
        'safe'
    ] === 'visible',
    'Safe metadata was unexpectedly changed.'
);


$text =
    SecretMasker::sanitizeText(
        'Authorization: Bearer abc.def.ghi password=hello token=xyz'
    );


$assert(
    !str_contains(
        $text,
        'abc.def.ghi'
    )
    &&
    !str_contains(
        $text,
        'password=hello'
    )
    &&
    !str_contains(
        $text,
        'token=xyz'
    ),
    'Secret text masking failed.'
);


/*
 * --------------------------------------------------------------------------
 * Structured JSONL writer
 * --------------------------------------------------------------------------
 */

$tmp =
    sys_get_temp_dir()
    . '/ipkf-logging-test-'
    . bin2hex(
        random_bytes(8)
    );


$logger =
    new SystemLogger(
        $tmp
    );


$logger->application(
    'INFO',
    'Foundation.Logging.Test',
    [
        'module' =>
            'foundation',

        'action' =>
            'test',

        'result' =>
            'success',

        'duration_ms' =>
            12,

        'metadata' => [
            'safe' =>
                'yes',

            'password' =>
                'must-not-appear',
        ],
    ]
);


$path =
    $tmp
    . '/system.jsonl';


$assert(
    is_file(
        $path
    ),
    'Structured system log file was not created.'
);


$line =
    trim(
        (string) file_get_contents(
            $path
        )
    );


$record =
    json_decode(
        $line,
        true
    );


$assert(
    is_array(
        $record
    ),
    'Structured log is not valid JSON.'
);


foreach ([
    'timestamp_utc',
    'level',
    'channel',
    'event_code',
    'module',
    'action',
    'correlation_id',
    'request_id',
    'ip',
    'user_agent',
    'result',
    'duration_ms',
    'metadata',
] as $field) {

    $assert(
        array_key_exists(
            $field,
            $record
        ),
        'Structured field missing: '
        . $field
    );
}


$assert(
    $record[
        'event_code'
    ] === 'Foundation.Logging.Test',
    'Event code changed unexpectedly.'
);


$assert(
    $record[
        'correlation_id'
    ] === 'corr_12345678',
    'Correlation ID missing from structured log.'
);


$assert(
    $record[
        'metadata'
    ][
        'password'
    ] === SecretMasker::REDACTED,
    'Structured metadata secret was not masked.'
);


$assert(
    !str_contains(
        $line,
        'must-not-appear'
    ),
    'Raw secret leaked to log line.'
);


/*
 * --------------------------------------------------------------------------
 * Static runtime contract
 * --------------------------------------------------------------------------
 */

$middleware =
    file_get_contents(
        $root
        . '/public_html/system/Http/Middleware/LogMiddleware.php'
    );


$errorHandler =
    file_get_contents(
        $root
        . '/public_html/system/Support/ErrorHandler.php'
    );


$response =
    file_get_contents(
        $root
        . '/public_html/system/Http/Response.php'
    );


$assert(
    is_string($middleware)
    &&
    str_contains(
        $middleware,
        'Http.Request.Completed'
    )
    &&
    str_contains(
        $middleware,
        'Http.Request.Failed'
    ),
    'Request structured event contract missing.'
);


$assert(
    !str_contains(
        (string) $middleware,
        'file_put_contents('
    )
    &&
    !str_contains(
        (string) $middleware,
        'error_log('
    ),
    'LogMiddleware still performs direct logging.'
);


$assert(
    is_string($errorHandler)
    &&
    str_contains(
        $errorHandler,
        'System.Exception.Unhandled'
    )
    &&
    str_contains(
        $errorHandler,
        'شناسه پیگیری'
    ),
    'Error handler structured/safe response contract missing.'
);


$assert(
    !str_contains(
        (string) $errorHandler,
        'file_put_contents('
    )
    &&
    !str_contains(
        (string) $errorHandler,
        'error_log('
    ),
    'ErrorHandler still performs direct logging.'
);


$assert(
    is_string($response)
    &&
    str_contains(
        $response,
        'public function statusCode(): int'
    ),
    'Response status getter contract missing.'
);


$assert(
    interface_exists(
        \IPKF\Logging\AuditLogger::class
    ),
    'AuditLogger abstraction missing.'
);


/*
 * Cleanup
 */

@unlink(
    $path
);

@rmdir(
    $tmp
);

$_SERVER =
    $oldServer;

RequestContext::reset();


echo
    "PLATFORM_STRUCTURED_LOGGING_PASS"
    . PHP_EOL;
