<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class NotificationProviderHttpTransport extends BaseService
{
    public function postJson(
        string $url,
        array $payload,
        int $timeout = 15
    ): array {
        return $this->request(
            $url,
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            ['Content-Type: application/json'],
            $timeout
        );
    }

    public function postForm(
        string $url,
        array $payload,
        int $timeout = 15
    ): array {
        return $this->request(
            $url,
            http_build_query(
                $payload,
                '',
                '&',
                PHP_QUERY_RFC3986
            ),
            [
                'Content-Type: '
                . 'application/x-www-form-urlencoded',
            ],
            $timeout
        );
    }

    private function request(
        string $url,
        string $body,
        array $headers,
        int $timeout
    ): array {
        $timeout = max(3, min(30, $timeout));
        $startedAt = microtime(true);

        if (function_exists('curl_init')) {
            return $this->requestWithCurl(
                $url,
                $body,
                $headers,
                $timeout,
                $startedAt
            );
        }

        return $this->requestWithStreams(
            $url,
            $body,
            $headers,
            $timeout,
            $startedAt
        );
    }

    private function requestWithCurl(
        string $url,
        string $body,
        array $headers,
        int $timeout,
        float $startedAt
    ): array {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'provider_test_api_connection_failed'
            );
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge(
                $headers,
                [
                    'Accept: application/json',
                    'User-Agent: IPKF-Notification-Test/1.0',
                ]
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($curl, $options);

        $responseBody = curl_exec($curl);
        $errorNumber = curl_errno($curl);
        $statusCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException(
                $errorNumber === 28
                    ? 'provider_test_api_timeout'
                    : 'provider_test_api_connection_failed'
            );
        }

        return $this->result(
            $statusCode,
            (string) $responseBody,
            $startedAt
        );
    }

    private function requestWithStreams(
        string $url,
        string $body,
        array $headers,
        int $timeout,
        float $startedAt
    ): array {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode(
                    "\r\n",
                    array_merge(
                        $headers,
                        [
                            'Accept: application/json',
                            'User-Agent: '
                            . 'IPKF-Notification-Test/1.0',
                        ]
                    )
                ),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
            ],
        ]);

        $previous = set_error_handler(
            static function (
                int $severity,
                string $message
            ): never {
                throw new RuntimeException($message);
            }
        );

        try {
            $responseBody = file_get_contents(
                $url,
                false,
                $context
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                str_contains(
                    strtolower($exception->getMessage()),
                    'timed out'
                )
                    ? 'provider_test_api_timeout'
                    : 'provider_test_api_connection_failed',
                0,
                $exception
            );
        } finally {
            restore_error_handler();
        }

        if (!is_string($responseBody)) {
            throw new RuntimeException(
                'provider_test_api_connection_failed'
            );
        }

        $statusCode = 0;
        $responseHeaders = $http_response_header ?? [];

        foreach ($responseHeaders as $header) {
            if (preg_match(
                '#^HTTP/\S+\s+([0-9]{3})#i',
                (string) $header,
                $matches
            ) === 1) {
                $statusCode = (int) $matches[1];
            }
        }

        return $this->result(
            $statusCode,
            $responseBody,
            $startedAt
        );
    }

    private function result(
        int $statusCode,
        string $body,
        float $startedAt
    ): array {
        $decoded = null;

        try {
            $value = json_decode(
                $body,
                true,
                64,
                JSON_THROW_ON_ERROR
            );

            if (is_array($value)) {
                $decoded = $value;
            }
        } catch (Throwable) {
            $decoded = null;
        }

        return [
            'status_code' => $statusCode,
            'body' => $body,
            'json' => $decoded,
            'duration_ms' => (int) round(
                (microtime(true) - $startedAt) * 1000
            ),
        ];
    }
}
