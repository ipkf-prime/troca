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
        $attachments = array_values(array_filter(
            is_array($message['attachments'] ?? null)
                ? $message['attachments']
                : [],
            static fn (mixed $attachment): bool =>
                is_array($attachment)
                && is_readable((string) (
                    $attachment['path'] ?? ''
                ))
        ));
        $timeout = max(
            3,
            min(30, (int) ($message['timeout'] ?? 12))
        );
        $isTest = !array_key_exists(
            'is_test',
            $message
        ) || !empty($message['is_test']);

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
                $messageId,
                $isTest,
                $attachments
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
        string $messageId,
        bool $isTest,
        array $attachments = []
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
        ];

        $headers[] = $isTest
            ? 'X-IPKF-Notification-Test: 1'
            : 'X-IPKF-Notification-Gateway: 1';

        if ($attachments === []) {
            $headers[] =
                'Content-Type: text/plain; charset=UTF-8';
            $headers[] =
                'Content-Transfer-Encoding: base64';

            return $this->dotStuff(
                implode("\r\n", $headers)
                . "\r\n\r\n"
                . $this->encodePart($body)
            );
        }

        $boundary = '=_IPKF_'
            . bin2hex(random_bytes(18));
        $headers[] =
            'Content-Type: multipart/mixed; boundary="'
            . $boundary . '"';

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $this->encodePart($body),
        ];

        foreach ($attachments as $attachment) {
            $path = (string) (
                $attachment['path'] ?? ''
            );

            if ($path === '' || !is_readable($path)) {
                throw new RuntimeException(
                    'provider_test_send_failed'
                );
            }

            $content = file_get_contents($path);

            if (!is_string($content)) {
                throw new RuntimeException(
                    'provider_test_send_failed'
                );
            }

            $mime = trim((string) (
                $attachment['mime_type']
                ?? 'application/octet-stream'
            ));
            $name = basename(str_replace(
                '\\',
                '/',
                (string) (
                    $attachment['original_name']
                    ?? basename($path)
                )
            ));
            $name = trim(preg_replace(
                '/[\r\n]+/',
                ' ',
                $name
            ) ?? 'attachment');
            $ascii = preg_replace(
                '/[^A-Za-z0-9._-]+/',
                '_',
                $name
            ) ?: 'attachment';

            array_push(
                $parts,
                '--' . $boundary,
                'Content-Type: ' . $mime
                    . '; name="' . $ascii . '"',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename="'
                    . $ascii
                    . '"; filename*=UTF-8\'\''
                    . rawurlencode($name),
                '',
                $this->encodePart($content)
            );
        }

        $parts[] = '--' . $boundary . '--';
        $parts[] = '';

        return $this->dotStuff(
            implode("\r\n", $headers)
            . "\r\n\r\n"
            . implode("\r\n", $parts)
        );
    }

    private function encodePart(string $content): string
    {
        return rtrim(chunk_split(
            base64_encode($content),
            76,
            "\r\n"
        ));
    }

    private function dotStuff(string $payload): string
    {
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
