<?php

declare(strict_types=1);

namespace IPKF\Support;

use ErrorException;
use IPKF\Logging\RequestContext;
use IPKF\Logging\SecretMasker;
use IPKF\Logging\SystemLogger;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        ini_set(
            'display_errors',
            Env::isDebug()
                ? '1'
                : '0'
        );

        error_reporting(
            E_ALL
        );

        set_error_handler(
            static function (
                int $severity,
                string $message,
                string $file,
                int $line
            ): bool {
                if (
                    !(
                        error_reporting()
                        & $severity
                    )
                ) {
                    return false;
                }

                throw new ErrorException(
                    $message,
                    0,
                    $severity,
                    $file,
                    $line
                );
            }
        );

        set_exception_handler(
            [
                self::class,
                'handleException',
            ]
        );
    }


    public static function handleError(
        mixed $level,
        mixed $message,
        mixed $file,
        mixed $line
    ): void {
        self::render(
            'PHP_ERROR',
            (string) $message,
            (string) $file,
            (int) $line
        );
    }


    public static function handleException(
        Throwable $exception
    ): void {
        self::render(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
            get_class(
                $exception
            )
        );
    }


    protected static function render(
        string $type,
        string $message,
        string $file,
        int $line,
        ?string $trace = null,
        ?string $exceptionClass = null
    ): void {
        RequestContext::ensure();

        $correlationId =
            RequestContext::correlationId();

        self::log(
            $type,
            $message,
            $file,
            $line,
            $trace,
            $exceptionClass
        );

        if (
            PHP_SAPI === 'cli'
        ) {
            self::renderCli(
                $message,
                $file,
                $line,
                $trace,
                $correlationId
            );

            return;
        }

        http_response_code(
            500
        );

        if (
            !headers_sent()
        ) {
            header(
                'Content-Type: text/html; charset=UTF-8'
            );

            header(
                'X-Request-ID: '
                . RequestContext::requestId()
            );

            header(
                'X-Correlation-ID: '
                . $correlationId
            );
        }

        if (
            Env::isDebug()
        ) {
            self::renderDebugHtml(
                $type,
                $message,
                $file,
                $line,
                $trace,
                $correlationId
            );

            return;
        }

        echo
            '<!doctype html>'
            . '<html lang="fa-IR" dir="rtl">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>خطای داخلی سامانه</title>'
            . '</head>'
            . '<body dir="rtl">'
            . '<main>'
            . '<h1>خطای داخلی سامانه</h1>'
            . '<p>در پردازش درخواست خطایی رخ داده است.</p>'
            . '<p>شناسه پیگیری: <code>'
            . htmlspecialchars(
                $correlationId,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</code></p>'
            . '</main>'
            . '</body>'
            . '</html>';
    }


    protected static function log(
        string $type,
        string $message,
        string $file,
        int $line,
        ?string $trace,
        ?string $exceptionClass
    ): void {
        $logger =
            new SystemLogger();

        $logger->application(
            'CRITICAL',
            'System.Exception.Unhandled',
            [
                'module' =>
                    'system',

                'action' =>
                    'exception_handler',

                'result' =>
                    'failed',

                'metadata' => [
                    'type' =>
                        $type,

                    'exception_class' =>
                        $exceptionClass,

                    'message' =>
                        SecretMasker::sanitizeText(
                            $message
                        ),

                    'file' =>
                        $file,

                    'line' =>
                        $line,

                    'trace' =>
                        $trace !== null
                            ? SecretMasker::sanitizeText(
                                $trace
                            )
                            : null,
                ],
            ]
        );
    }


    private static function renderCli(
        string $message,
        string $file,
        int $line,
        ?string $trace,
        string $correlationId
    ): void {
        if (
            Env::isDebug()
        ) {
            $output =
                'Unhandled exception: '
                . $message
                . ' in '
                . $file
                . ':'
                . $line
                . PHP_EOL;

            if (
                $trace !== null
                && $trace !== ''
            ) {
                $output .=
                    $trace
                    . PHP_EOL;
            }
        } else {
            $output =
                'Internal application error.'
                . PHP_EOL;
        }

        $output .=
            'Correlation ID: '
            . $correlationId
            . PHP_EOL;

        if (
            defined(
                'STDERR'
            )
        ) {
            fwrite(
                STDERR,
                $output
            );

            return;
        }

        echo $output;
    }


    private static function renderDebugHtml(
        string $type,
        string $message,
        string $file,
        int $line,
        ?string $trace,
        string $correlationId
    ): void {
        echo
            '<h2>'
            . htmlspecialchars(
                $type,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</h2>';

        echo
            '<p><b>Message:</b> '
            . htmlspecialchars(
                $message,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</p>';

        echo
            '<p><b>File:</b> '
            . htmlspecialchars(
                $file,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</p>';

        echo
            '<p><b>Line:</b> '
            . $line
            . '</p>';

        echo
            '<p><b>Correlation ID:</b> '
            . htmlspecialchars(
                $correlationId,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            )
            . '</p>';

        if (
            $trace !== null
            && $trace !== ''
        ) {
            echo
                '<pre>'
                . htmlspecialchars(
                    $trace,
                    ENT_QUOTES
                    | ENT_SUBSTITUTE,
                    'UTF-8'
                )
                . '</pre>';
        }
    }
}
