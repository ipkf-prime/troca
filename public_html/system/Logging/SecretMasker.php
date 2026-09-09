<?php

declare(strict_types=1);

namespace IPKF\Logging;

final class SecretMasker
{
    public const REDACTED =
        '[REDACTED]';


    public static function sanitize(
        mixed $value,
        ?string $key = null,
        int $depth = 0
    ): mixed {
        if (
            $key !== null
            && self::sensitiveKey(
                $key
            )
        ) {
            return self::REDACTED;
        }

        if ($depth > 8) {
            return '[MAX_DEPTH]';
        }

        if (is_array($value)) {
            $clean = [];

            foreach (
                $value
                as $childKey => $childValue
            ) {
                $clean[$childKey] =
                    self::sanitize(
                        $childValue,
                        is_string($childKey)
                            ? $childKey
                            : null,
                        $depth + 1
                    );
            }

            return $clean;
        }

        if (is_string($value)) {
            return self::sanitizeText(
                $value
            );
        }

        if (
            is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
        ) {
            return $value;
        }

        if ($value instanceof \Throwable) {
            return [
                'exception_class' =>
                    get_class($value),

                'message' =>
                    self::sanitizeText(
                        $value->getMessage()
                    ),

                'file' =>
                    $value->getFile(),

                'line' =>
                    $value->getLine(),
            ];
        }

        if (is_object($value)) {
            return [
                'object_class' =>
                    get_class($value),
            ];
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        return '[UNSUPPORTED]';
    }


    public static function sanitizeText(
        string $value
    ): string {
        $value =
            preg_replace(
                '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i',
                'Bearer '
                . self::REDACTED,
                $value
            )
            ?? $value;

        $value =
            preg_replace(
                '/\b(Basic)\s+[A-Za-z0-9+\/=]+/i',
                '$1 '
                . self::REDACTED,
                $value
            )
            ?? $value;

        $value =
            preg_replace(
                '/\b(password|passwd|secret|token|api[_-]?key|csrf|session[_-]?id)\b'
                . '\s*[:=]\s*'
                . '([^\s,;]+)/i',
                '$1='
                . self::REDACTED,
                $value
            )
            ?? $value;

        if (
            function_exists(
                'mb_substr'
            )
        ) {
            return mb_substr(
                $value,
                0,
                8000,
                'UTF-8'
            );
        }

        return substr(
            $value,
            0,
            8000
        );
    }


    public static function sensitiveKey(
        string $key
    ): bool {
        $normalized =
            strtolower(
                preg_replace(
                    '/[^a-z0-9]+/i',
                    '_',
                    $key
                )
                ?? $key
            );

        return preg_match(
            '/(?:^|_)'
            . '(?:'
            . 'password'
            . '|passwd'
            . '|secret'
            . '|token'
            . '|api_key'
            . '|authorization'
            . '|cookie'
            . '|csrf'
            . '|session_id'
            . ')'
            . '(?:_|$)/',
            $normalized
        ) === 1;
    }
}
