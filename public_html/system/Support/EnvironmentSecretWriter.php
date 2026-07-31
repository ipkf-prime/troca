<?php

namespace IPKF\Support;

use JsonException;
use RuntimeException;

final class EnvironmentSecretWriter
{
    public function write(string $key, string $value): void
    {
        if (preg_match('/^[A-Z][A-Z0-9_]{2,127}$/', $key) !== 1) {
            throw new RuntimeException('Invalid environment key.');
        }
        if ($value === '' || str_contains($value, "\0") || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new RuntimeException('Invalid secret value.');
        }

        $path = trim((string) Env::get('IPKF_SHARED_ENV', ''));
        if ($path === '' || !$this->isAbsolutePath($path)) {
            throw new RuntimeException('Shared environment path is not configured.');
        }

        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Shared environment directory is not writable.');
        }
        if (file_exists($path) && (!is_file($path) || !is_readable($path) || !is_writable($path))) {
            throw new RuntimeException('Shared environment file is not writable.');
        }

        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Unable to open the environment lock.');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the shared environment.');
            }

            $contents = file_exists($path) ? file_get_contents($path) : '';
            if ($contents === false) {
                throw new RuntimeException('Unable to read the shared environment.');
            }

            $updated = $this->replace($contents, $key, $this->encode($value));
            $temporary = tempnam($directory, '.ipkf-env-');
            if ($temporary === false) {
                throw new RuntimeException('Unable to create a temporary environment file.');
            }

            try {
                if (file_put_contents($temporary, $updated, LOCK_EX) === false) {
                    throw new RuntimeException('Unable to write the shared environment.');
                }
                @chmod($temporary, 0600);
                if (!rename($temporary, $path)) {
                    throw new RuntimeException('Unable to publish the shared environment.');
                }
            } finally {
                if (isset($temporary) && is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function replace(string $contents, string $key, string $encoded): string
    {
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\R/', $contents);
        if ($lines === false) {
            throw new RuntimeException('Unable to parse the shared environment.');
        }

        $replacement = $key . '=' . $encoded;
        $found = false;
        foreach ($lines as &$line) {
            if (preg_match('/^\s*' . preg_quote($key, '/') . '\s*=/', $line) === 1) {
                if (!$found) {
                    $line = $replacement;
                    $found = true;
                } else {
                    $line = '# duplicate removed: ' . $key;
                }
            }
        }
        unset($line);

        if (!$found) {
            while ($lines !== [] && end($lines) === '') {
                array_pop($lines);
            }
            $lines[] = $replacement;
        }

        return implode($newline, $lines) . $newline;
    }

    private function encode(string $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the secret value.', 0, $exception);
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
