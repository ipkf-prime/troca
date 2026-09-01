<?php

namespace App\Services\Infrastructure;

/**
 * Shared ClamAV process driver.
 *
 * 0 => clean
 * 1 => infected
 * other => error
 *
 * Scanner failure remains fail-closed at the consuming service.
 */
final class ClamAvProcessScanner implements MalwareScannerInterface
{
    public const RESULT_CLEAN = 'clean';
    public const RESULT_INFECTED = 'infected';
    public const RESULT_ERROR = 'error';

    private ?string $binary;

    private int $timeoutSeconds;


    public function __construct(
        ?string $binary = null,
        ?int $timeoutSeconds = null,
        ?SharedFileInfrastructureSettingsService $settings = null
    ) {
        if (
            $binary === null
            || trim($binary) === ''
            || $timeoutSeconds === null
        ) {
            $settings ??=
                new SharedFileInfrastructureSettingsService();

            if (
                $binary === null
                || trim($binary) === ''
            ) {
                $binary =
                    $settings
                        ->effectiveScannerBinary();
            }

            if ($timeoutSeconds === null) {
                $timeoutSeconds =
                    $settings
                        ->effectiveScanTimeoutSeconds();
            }
        }

        $binary =
            is_string($binary)
                ? trim($binary)
                : '';

        $this->binary =
            $binary !== ''
                ? $binary
                : null;

        $this->timeoutSeconds =
            max(
                5,
                min(
                    300,
                    (int) (
                        $timeoutSeconds
                        ?? 45
                    )
                )
            );
    }


    public function binary(): ?string
    {
        return $this->binary;
    }


    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }


    public function available(): bool
    {
        return
            $this->binary !== null
            && is_file($this->binary)
            && is_executable($this->binary)
            && function_exists('proc_open');
    }


    public function scan(
        string $path
    ): string {
        $realPath =
            realpath($path);

        if (
            $realPath === false
            || !is_file($realPath)
            || !is_readable($realPath)
            || !$this->available()
        ) {
            return self::RESULT_ERROR;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process =
            proc_open(
                [
                    $this->binary,
                    '--infected',
                    '--no-summary',
                    $realPath,
                ],
                $descriptors,
                $pipes,
                null,
                null,
                [
                    'bypass_shell' => true,
                ]
            );

        if (!is_resource($process)) {
            return self::RESULT_ERROR;
        }

        fclose(
            $pipes[0]
        );

        stream_set_blocking(
            $pipes[1],
            false
        );

        stream_set_blocking(
            $pipes[2],
            false
        );

        $startedAt =
            microtime(true);

        $exitCode = null;

        $timedOut = false;

        do {
            /*
             * Drain output without exposing scanner internals.
             */
            stream_get_contents(
                $pipes[1],
                8192
            );

            stream_get_contents(
                $pipes[2],
                8192
            );

            $status =
                proc_get_status(
                    $process
                );

            if (
                !(
                    $status['running']
                    ?? false
                )
            ) {
                $exitCode =
                    (int) (
                        $status['exitcode']
                        ?? -1
                    );

                break;
            }

            if (
                microtime(true) - $startedAt
                >= $this->timeoutSeconds
            ) {
                $timedOut = true;

                proc_terminate(
                    $process
                );

                break;
            }

            usleep(
                50000
            );

        } while (true);

        stream_get_contents(
            $pipes[1]
        );

        stream_get_contents(
            $pipes[2]
        );

        fclose(
            $pipes[1]
        );

        fclose(
            $pipes[2]
        );

        $closeCode =
            proc_close(
                $process
            );

        if ($timedOut) {
            return self::RESULT_ERROR;
        }

        if (
            $exitCode === null
            || $exitCode < 0
        ) {
            $exitCode =
                $closeCode;
        }

        return match ($exitCode) {
            0 =>
                self::RESULT_CLEAN,

            1 =>
                self::RESULT_INFECTED,

            default =>
                self::RESULT_ERROR,
        };
    }
}
