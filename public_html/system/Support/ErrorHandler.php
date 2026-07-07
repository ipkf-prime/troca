<?php

namespace IPKF\Support;

use Throwable;

class ErrorHandler
{
    public static function register(): void
    {
        ini_set('display_errors', Env::isDebug() ? '1' : '0');
        error_reporting(E_ALL);

        set_error_handler(function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleError($level, $message, $file, $line): void
    {
        self::render("PHP ERROR", $message, $file, $line);
    }

    public static function handleException(Throwable $e): void
    {
        self::render("EXCEPTION", $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    }

    protected static function render($type, $message, $file, $line, $trace = null): void
    {
        http_response_code(500);

        if (Env::isDebug()) {
            echo "<h2>{$type}</h2>";
            echo "<p><b>Message:</b> " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
            echo "<p><b>File:</b> " . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . "</p>";
            echo "<p><b>Line:</b> {$line}</p>";

            if ($trace) {
                echo "<pre>" . htmlspecialchars($trace, ENT_QUOTES, 'UTF-8') . "</pre>";
            }
        } else {
            echo "Internal Server Error";
        }

        self::log($type, $message, $file, $line, $trace);
    }

    protected static function log($type, $message, $file, $line, $trace): void
    {
        $log = "[" . date('Y-m-d H:i:s') . "] $type: $message in $file:$line\n";

        file_put_contents(
            BASE_PATH . '/storage/logs/error.log',
            $log,
            FILE_APPEND
        );
    }
}
