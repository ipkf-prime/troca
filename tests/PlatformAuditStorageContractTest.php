<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


require_once
    $root
    . '/public_html/system/Logging/AuditLogger.php';

require_once
    $root
    . '/public_html/system/Logging/RequestContext.php';

require_once
    $root
    . '/public_html/system/Logging/SecretMasker.php';

require_once
    $root
    . '/public_html/system/Logging/DatabaseAuditLogger.php';


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


$files = [
    'docs/PLATFORM_AUDIT_BOUNDARY.md',

    'public_html/system/Database/Migrations/CreatePlatformAuditFoundation.php',

    'public_html/system/Logging/DatabaseAuditLogger.php',
];


foreach (
    $files
    as $file
) {
    $assert(
        is_file(
            $root
            . '/'
            . $file
        ),
        'Missing Platform Audit file: '
        . $file
    );
}


$assert(
    is_subclass_of(
        \IPKF\Logging\DatabaseAuditLogger::class,
        \IPKF\Logging\AuditLogger::class
    ),
    'DatabaseAuditLogger does not implement AuditLogger.'
);


$migration =
    file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/CreatePlatformAuditFoundation.php'
    );


$logger =
    file_get_contents(
        $root
        . '/public_html/system/Logging/DatabaseAuditLogger.php'
    );


$registry =
    file_get_contents(
        $root
        . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php'
    );


$boundary =
    file_get_contents(
        $root
        . '/docs/PLATFORM_AUDIT_BOUNDARY.md'
    );


foreach ([
    'platform_audit_events',
    'public_reference',
    'schema_version',
    'event_code',
    'module_code',
    'actor_user_id',
    'actor_user_reference',
    'actor_type',
    'target_type',
    'target_id',
    'before_json',
    'after_json',
    'changed_fields_json',
    'metadata_json',
    'request_id',
    'correlation_id',
    'ip_address',
    'user_agent',
    'retention_policy_code',
    'retain_until',
    'occurred_at',
] as $column) {

    $assert(
        str_contains(
            (string) $migration,
            $column
        ),
        'Platform Audit migration missing contract: '
        . $column
    );
}


$assert(
    str_contains(
        (string) $migration,
        'ENGINE=InnoDB'
    ),
    'Platform Audit store must use InnoDB.'
);


$assert(
    !str_contains(
        (string) $migration,
        'FOREIGN KEY'
    ),
    'Platform Audit must not depend on user/account foreign keys.'
);


foreach ([
    'implements AuditLogger',
    "resolve(\n                'core.primary'",
    'SecretMasker::sanitize',
    'RequestContext::requestId()',
    'RequestContext::correlationId()',
    'INSERT INTO platform_audit_events',
] as $needle) {

    $assert(
        str_contains(
            (string) $logger,
            $needle
        ),
        'DatabaseAuditLogger contract missing: '
        . $needle
    );
}


$assert(
    !preg_match(
        '/\bUPDATE\s+platform_audit_events\b/i',
        (string) $logger
    )
    &&
    !preg_match(
        '/\bDELETE\s+FROM\s+platform_audit_events\b/i',
        (string) $logger
    ),
    'DatabaseAuditLogger must be insert-only.'
);


$assert(
    str_contains(
        (string) $registry,
        'CreatePlatformAuditFoundation::class'
    ),
    'Platform Audit migration is not registered.'
);


$assert(
    substr_count(
        (string) $registry,
        'CreatePlatformAuditFoundation::class'
    ) === 1,
    'Platform Audit migration registration is not unique.'
);


foreach ([
    'complements existing domain event/audit tables',
    'core.primary',
    'When atomicity with a Core transaction is required',
    'No platform-wide retention duration is invented',
] as $needle) {

    $assert(
        str_contains(
            (string) $boundary,
            $needle
        ),
        'Platform Audit boundary missing: '
        . $needle
    );
}


echo
    "PLATFORM_AUDIT_STORAGE_CONTRACT_PASS"
    . PHP_EOL;
