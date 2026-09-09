<?php

declare(strict_types=1);

namespace IPKF\Logging;

final class RequestContext
{
    private static ?string $requestId = null;

    private static ?string $correlationId = null;

    private static ?string $ip = null;

    private static ?string $userAgent = null;

    private static ?int $actorUserId = null;

    private static ?string $actorUserReference = null;

    private static ?string $actorType = null;


    public static function initializeFromGlobals(): void
    {
        if (self::$requestId !== null) {
            return;
        }

        self::$requestId =
            self::generateIdentifier(
                'req'
            );

        $incomingCorrelation =
            trim(
                (string) (
                    $_SERVER[
                        'HTTP_X_CORRELATION_ID'
                    ]
                    ?? ''
                )
            );

        self::$correlationId =
            self::validIncomingIdentifier(
                $incomingCorrelation
            )
                ? $incomingCorrelation
                : self::$requestId;

        self::$ip =
            self::safeText(
                (string) (
                    $_SERVER[
                        'REMOTE_ADDR'
                    ]
                    ?? ''
                ),
                64
            );

        self::$userAgent =
            self::safeText(
                (string) (
                    $_SERVER[
                        'HTTP_USER_AGENT'
                    ]
                    ?? ''
                ),
                512
            );
    }


    public static function ensure(): void
    {
        self::initializeFromGlobals();
    }


    public static function requestId(): string
    {
        self::ensure();

        return (string) self::$requestId;
    }


    public static function correlationId(): string
    {
        self::ensure();

        return (string) self::$correlationId;
    }


    public static function ip(): ?string
    {
        self::ensure();

        return self::$ip !== ''
            ? self::$ip
            : null;
    }


    public static function userAgent(): ?string
    {
        self::ensure();

        return self::$userAgent !== ''
            ? self::$userAgent
            : null;
    }


    public static function setActor(
        ?int $userId,
        ?string $userReference = null,
        ?string $actorType = 'user'
    ): void {
        self::$actorUserId =
            $userId !== null
            && $userId > 0
                ? $userId
                : null;

        self::$actorUserReference =
            self::nullableSafeText(
                $userReference,
                100
            );

        self::$actorType =
            self::nullableSafeText(
                $actorType,
                40
            );
    }


    public static function actorUserId(): ?int
    {
        return self::$actorUserId;
    }


    public static function actorUserReference(): ?string
    {
        return self::$actorUserReference;
    }


    public static function actorType(): ?string
    {
        return self::$actorType;
    }


    /**
     * Test/worker isolation helper.
     */
    public static function reset(): void
    {
        self::$requestId = null;
        self::$correlationId = null;
        self::$ip = null;
        self::$userAgent = null;

        self::$actorUserId = null;
        self::$actorUserReference = null;
        self::$actorType = null;
    }


    private static function validIncomingIdentifier(
        string $value
    ): bool {
        return preg_match(
            '/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/',
            $value
        ) === 1;
    }


    private static function generateIdentifier(
        string $prefix
    ): string {
        try {
            $random =
                bin2hex(
                    random_bytes(16)
                );
        } catch (\Throwable) {
            $random =
                hash(
                    'sha256',
                    uniqid(
                        '',
                        true
                    )
                    . microtime(true)
                );
        }

        return $prefix
            . '_'
            . substr(
                $random,
                0,
                32
            );
    }


    private static function nullableSafeText(
        ?string $value,
        int $maxLength
    ): ?string {
        $value =
            self::safeText(
                (string) (
                    $value
                    ?? ''
                ),
                $maxLength
            );

        return $value !== ''
            ? $value
            : null;
    }


    private static function safeText(
        string $value,
        int $maxLength
    ): string {
        $value =
            trim(
                preg_replace(
                    '/[\x00-\x1F\x7F]/u',
                    '',
                    $value
                )
                ?? ''
            );

        if ($value === '') {
            return '';
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
}
