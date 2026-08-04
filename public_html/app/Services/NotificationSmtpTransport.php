<?php

namespace App\Services;

use RuntimeException;

class NotificationSmtpTransport extends BaseService
{
    public function send(array $message): array
    {
        $host = trim((string) ($message['host'] ?? ''));
        $port = (int) ($message['port'] ?? 0);
        $encryption = strtolower(trim(
            (string) ($message['encryption'] ?? 'none')
        ));
        $username = trim(
            (string) ($message['username'] ?? '')
        );
        $password = (string) ($message['password'] ?? '');
        $fromAddress = trim(
            (string) ($message['from_address'] ?? '')
        );
        $fromName = trim(
            (string) ($message['from_name'] ?? '')
        );
        $recipient = trim(
            (string) ($message['recipient'] ?? '')
        );
        $subject = trim(
            (string) ($message['subject'] ?? '')
        );
        $body = trim((string) ($message['body'] ?? ''));
        $timeout = max(
            3,
            min(30, (int) ($message['timeout'] ?? 12))
        );

        $endpoint = $encryption === 'ssl'
            ? 'ssl://' . $host . ':' . $port
            : 'tcp://' . $host . ':' . $port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $errorNumber = 0;
        $errorMessage = '';

        $socket = @stream_socket_client(
            $endpoint,
            $errorNumber,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($socket)) {
            throw new RuntimeException(
                'provider_test_connection_failed'
            );
        }

        stream_set_timeout($socket, $timeout);
        $startedAt = microtime(true);
        $messageId = $this->messageId($fromAddress);

        try {
            $this->expect(
                $this->readResponse($socket),
                [220],
                'provider_test_connection_failed'
            );

            $clientName = $this->clientName();
            $this->hello($socket, $clientName);

            if ($encryption === 'tls') {
                $this->command(
                    $socket,
                    'STARTTLS',
                    [220],
                    'provider_test_tls_failed'
                );

                $cryptoEnabled = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );

                if ($cryptoEnabled !== true) {
                    throw new RuntimeException(
                        'provider_test_tls_failed'
                    );
                }

                $this->hello($socket, $clientName);
            }

            if ($username !== '') {
                $this->authenticate(
                    $socket,
                    $username,
                    $password
                );
            }

            $this->command(
                $socket,
                'MAIL FROM:<' . $fromAddress . '>',
                [250],
                'provider_test_sender_rejected'
            );

            $this->command(
                $socket,
                'RCPT TO:<' . $recipient . '>',
                [250, 251],
                'provider_test_recipient_rejected'
            );

            $this->command(
                $socket,
                'DATA',
                [354],
                'provider_test_send_failed'
            );

            $payload = $this->payload(
                $fromAddress,
                $fromName,
                $recipient,
                $subject,
                $body,
                $messageId
            );

            $this->writeAll(
                $socket,
                $payload . "\r\n.\r\n"
            );

            $accepted = $this->readResponse($socket);

            $this->expect(
                $accepted,
                [250],
                'provider_test_send_failed'
            );

            try {
                $this->command(
                    $socket,
                    'QUIT',
                    [221],
                    'provider_test_send_failed'
                );
            } catch (RuntimeException) {
                // The message was already accepted by the server.
            }

            return [
                'message_id' => $messageId,
                'response_code' => (int) $accepted['code'],
                'duration_ms' => (int) round(
                    (microtime(true) - $startedAt) * 1000
                ),
            ];
        } finally {
            fclose($socket);
        }
    }

    private function hello(mixed $socket, string $clientName): array
    {
        $this->writeAll(
            $socket,
            'EHLO ' . $clientName . "\r\n"
        );
        $response = $this->readResponse($socket);

        if ((int) $response['code'] === 250) {
            return $response;
        }

        return $this->command(
            $socket,
            'HELO ' . $clientName,
            [250],
            'provider_test_connection_failed'
        );
    }

    private function authenticate(
        mixed $socket,
        string $username,
        string $password
    ): void {
        $first = $this->command(
            $socket,
            'AUTH LOGIN',
            [334, 235, 503],
            'provider_test_auth_failed'
        );

        if (in_array(
            (int) $first['code'],
            [235, 503],
            true
        )) {
            return;
        }

        $this->command(
            $socket,
            base64_encode($username),
            [334],
            'provider_test_auth_failed'
        );

        $this->command(
            $socket,
            base64_encode($password),
            [235, 503],
            'provider_test_auth_failed'
        );
    }

    private function command(
        mixed $socket,
        string $command,
        array $expectedCodes,
        string $errorCode
    ): array {
        $this->writeAll(
            $socket,
            $command . "\r\n"
        );

        $response = $this->readResponse($socket);
        $this->expect(
            $response,
            $expectedCodes,
            $errorCode
        );

        return $response;
    }

    private function readResponse(mixed $socket): array
    {
        $lines = [];
        $code = 0;

        for ($index = 0; $index < 100; $index++) {
            $line = fgets($socket, 4096);

            if ($line === false) {
                $metadata = stream_get_meta_data($socket);

                throw new RuntimeException(
                    !empty($metadata['timed_out'])
                        ? 'provider_test_timeout'
                        : 'provider_test_connection_failed'
                );
            }

            $line = rtrim($line, "\r\n");
            $lines[] = $line;

            if (preg_match(
                '/^([0-9]{3})([ -])/',
                $line,
                $matches
            ) !== 1) {
                continue;
            }

            $code = (int) $matches[1];

            if ($matches[2] === ' ') {
                return [
                    'code' => $code,
                    'message' => implode("\n", $lines),
                ];
            }
        }

        throw new RuntimeException(
            'provider_test_connection_failed'
        );
    }

    private function expect(
        array $response,
        array $expectedCodes,
        string $errorCode
    ): void {
        if (!in_array(
            (int) ($response['code'] ?? 0),
            $expectedCodes,
            true
        )) {
            throw new RuntimeException($errorCode);
        }
    }

    private function writeAll(
        mixed $socket,
        string $payload
    ): void {
        $length = strlen($payload);
        $offset = 0;

        while ($offset < $length) {
            $written = fwrite(
                $socket,
                substr($payload, $offset)
            );

            if ($written === false || $written === 0) {
                throw new RuntimeException(
                    'provider_test_connection_failed'
                );
            }

            $offset += $written;
        }
    }

    private function payload(
        string $fromAddress,
        string $fromName,
        string $recipient,
        string $subject,
        string $body,
        string $messageId
    ): string {
        $safeFromName = $this->headerText(
            $fromName !== '' ? $fromName : $fromAddress
        );
        $safeSubject = $this->headerText($subject);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $safeFromName
                . ' <' . $fromAddress . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $safeSubject,
            'Message-ID: <' . $messageId . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-IPKF-Notification-Test: 1',
        ];

        $encodedBody = rtrim(
            chunk_split(
                base64_encode($body),
                76,
                "\r\n"
            )
        );

        $payload = implode("\r\n", $headers)
            . "\r\n\r\n"
            . $encodedBody;

        return preg_replace(
            '/(?m)^\./',
            '..',
            $payload
        ) ?? $payload;
    }

    private function headerText(string $value): string
    {
        $value = trim(
            preg_replace(
                '/[\r\n]+/',
                ' ',
                $value
            ) ?? ''
        );

        return '=?UTF-8?B?'
            . base64_encode($value)
            . '?=';
    }

    private function messageId(string $fromAddress): string
    {
        $domain = substr(
            strrchr($fromAddress, '@') ?: '',
            1
        );

        if (
            $domain === ''
            || preg_match(
                '/^[a-z0-9.-]+$/i',
                $domain
            ) !== 1
        ) {
            $domain = 'localhost';
        }

        return bin2hex(random_bytes(12))
            . '.' . time()
            . '@' . $domain;
    }

    private function clientName(): string
    {
        $name = trim((string) gethostname());

        if (
            $name === ''
            || preg_match(
                '/^[a-z0-9.-]+$/i',
                $name
            ) !== 1
        ) {
            return 'localhost';
        }

        return $name;
    }
}
