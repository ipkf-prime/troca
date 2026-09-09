<?php

declare(strict_types=1);

namespace IPKF\Logging;

final class SystemLogger
{
    private const CHANNELS = [
        'application',
        'security',
    ];

    private const LEVELS = [
        'DEBUG',
        'INFO',
        'NOTICE',
        'WARNING',
        'ERROR',
        'CRITICAL',
    ];

    private string $logDirectory;


    public function __construct(
        ?string $logDirectory = null
    ) {
        $this->logDirectory =
            $logDirectory !== null
            && trim($logDirectory) !== ''
                ? rtrim(
                    $logDirectory,
                    '/\\'
                )
                : $this->defaultLogDirectory();
    }


    public function application(
        string $level,
        string $eventCode,
        array $context = []
    ): void {
        $this->write(
            'application',
            $level,
            $eventCode,
            $context
        );
    }


    public function security(
        string $level,
        string $eventCode,
        array $context = []
    ): void {
        $this->write(
            'security',
            $level,
            $eventCode,
            $context
        );
    }


    public function write(
        string $channel,
        string $level,
        string $eventCode,
        array $context = []
    ): void {
        try {
            RequestContext::ensure();

            $channel =
                in_array(
                    $channel,
                    self::CHANNELS,
                    true
                )
                    ? $channel
                    : 'application';

            $level =
                strtoupper(
                    trim($level)
                );

            if (
                !in_array(
                    $level,
                    self::LEVELS,
                    true
                )
            ) {
                $level = 'INFO';
            }

            $eventCode =
                $this->eventCode(
                    $eventCode
                );

            $metadata =
                SecretMasker::sanitize(
                    $context[
                        'metadata'
                    ]
                    ?? []
                );

            if (!is_array($metadata)) {
                $metadata = [
                    'value' => $metadata,
                ];
            }

            $record = [
                'timestamp_utc' =>
                    gmdate(
                        'Y-m-d\TH:i:s\Z'
                    ),

                'level' =>
                    $level,

                'channel' =>
                    $channel,

                'event_code' =>
                    $eventCode,

                'module' =>
                    $this->nullableText(
                        $context[
                            'module'
                        ]
                        ?? null,
                        80
                    ),

                'action' =>
                    $this->nullableText(
                        $context[
                            'action'
                        ]
                        ?? null,
                        100
                    ),

                'actor_user_id' =>
                    isset(
                        $context[
                            'actor_user_id'
                        ]
                    )
                        ? (
                            (int) $context[
                                'actor_user_id'
                            ]
                            ?: null
                        )
                        : RequestContext::actorUserId(),

                'actor_user_reference' =>
                    $this->nullableText(
                        $context[
                            'actor_user_reference'
                        ]
                        ?? RequestContext::actorUserReference(),
                        100
                    ),

                'actor_type' =>
                    $this->nullableText(
                        $context[
                            'actor_type'
                        ]
                        ?? RequestContext::actorType(),
                        40
                    ),

                'target_type' =>
                    $this->nullableText(
                        $context[
                            'target_type'
                        ]
                        ?? null,
                        80
                    ),

                'target_id' =>
                    $this->nullableText(
                        $context[
                            'target_id'
                        ]
                        ?? null,
                        160
                    ),

                'correlation_id' =>
                    RequestContext::correlationId(),

                'request_id' =>
                    RequestContext::requestId(),

                'ip' =>
                    RequestContext::ip(),

                'user_agent' =>
                    RequestContext::userAgent(),

                'result' =>
                    $this->nullableText(
                        $context[
                            'result'
                        ]
                        ?? null,
                        60
                    ),

                'duration_ms' =>
                    isset(
                        $context[
                            'duration_ms'
                        ]
                    )
                        ? max(
                            0,
                            (int) $context[
                                'duration_ms'
                            ]
                        )
                        : null,

                'metadata' =>
                    $metadata,
            ];

            $record =
                SecretMasker::sanitize(
                    $record
                );

            if (!is_array($record)) {
                return;
            }

            $json =
                json_encode(
                    $record,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_INVALID_UTF8_SUBSTITUTE
                );

            if (!is_string($json)) {
                return;
            }

            $this->ensureDirectory();

            @file_put_contents(
                $this->pathFor(
                    $channel
                ),
                $json
                . PHP_EOL,
                FILE_APPEND
                | LOCK_EX
            );

        } catch (\Throwable) {
            /*
             * Logging is deliberately best-effort.
             * Never break the business request and never recurse.
             */
        }
    }


    private function eventCode(
        string $eventCode
    ): string {
        $eventCode =
            trim(
                $eventCode
            );

        if (
            preg_match(
                '/^[A-Z][A-Za-z0-9]*(?:\.[A-Z][A-Za-z0-9]*){1,7}$/',
                $eventCode
            ) === 1
        ) {
            return $eventCode;
        }

        return 'System.Log.InvalidEventCode';
    }


    private function nullableText(
        mixed $value,
        int $maxLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            SecretMasker::sanitizeText(
                trim(
                    (string) $value
                )
            );

        if ($value === '') {
            return null;
        }

        if (
            function_exists(
                'mb_substr'
            )
        ) {
            return mb_substr(
                $value,
                0,
                $maxLength,
                'UTF-8'
            );
        }

        return substr(
            $value,
            0,
            $maxLength
        );
    }


    private function defaultLogDirectory(): string
    {
        if (
            defined(
                'BASE_PATH'
            )
        ) {
            return rtrim(
                (string) BASE_PATH,
                '/\\'
            )
            . '/storage/logs';
        }

        return sys_get_temp_dir()
            . '/ipkf-logs';
    }


    private function ensureDirectory(): void
    {
        if (
            is_dir(
                $this->logDirectory
            )
        ) {
            return;
        }

        @mkdir(
            $this->logDirectory,
            0750,
            true
        );
    }


    private function pathFor(
        string $channel
    ): string {
        return $this->logDirectory
            . '/'
            . (
                $channel === 'security'
                    ? 'security.jsonl'
                    : 'system.jsonl'
            );
    }
}
