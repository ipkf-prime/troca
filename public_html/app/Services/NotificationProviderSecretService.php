<?php

namespace App\Services;

use IPKF\Support\Env;
use RuntimeException;

class NotificationProviderSecretService extends BaseService
{
    private const KEY_VERSION = 1;

    public function encrypt(array $secrets): array
    {
        $normalized = $this->normalize($secrets);
        $plain = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
        $key = $this->key();

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(
                SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            );
            $ciphertext = sodium_crypto_secretbox(
                $plain,
                $nonce,
                $key
            );

            $envelope = [
                'version' => 1,
                'cipher' => 'sodium_secretbox',
                'nonce' => base64_encode($nonce),
                'ciphertext' => base64_encode($ciphertext),
            ];
            $cipherCode = 'sodium_secretbox';
        } elseif (function_exists('openssl_encrypt')) {
            $nonce = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $plain,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                '',
                16
            );

            if ($ciphertext === false) {
                throw new RuntimeException(
                    'notification_secret_encryption_failed'
                );
            }

            $envelope = [
                'version' => 1,
                'cipher' => 'aes_256_gcm',
                'nonce' => base64_encode($nonce),
                'tag' => base64_encode($tag),
                'ciphertext' => base64_encode($ciphertext),
            ];
            $cipherCode = 'aes_256_gcm';
        } else {
            throw new RuntimeException(
                'notification_secret_cipher_unavailable'
            );
        }

        return [
            'cipher_code' => $cipherCode,
            'key_version' => self::KEY_VERSION,
            'encrypted_payload' => base64_encode(
                json_encode(
                    $envelope,
                    JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                )
            ),
            'payload_checksum' => hash('sha256', $plain),
        ];
    }

    public function decrypt(string $encryptedPayload): array
    {
        $encodedEnvelope = base64_decode(
            trim($encryptedPayload),
            true
        );

        if ($encodedEnvelope === false) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        $envelope = json_decode(
            $encodedEnvelope,
            true,
            32,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($envelope)) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        $cipher = (string) ($envelope['cipher'] ?? '');
        $nonce = base64_decode(
            (string) ($envelope['nonce'] ?? ''),
            true
        );
        $ciphertext = base64_decode(
            (string) ($envelope['ciphertext'] ?? ''),
            true
        );

        if ($nonce === false || $ciphertext === false) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        $key = $this->key();

        if (
            $cipher === 'sodium_secretbox'
            && function_exists('sodium_crypto_secretbox_open')
        ) {
            $plain = sodium_crypto_secretbox_open(
                $ciphertext,
                $nonce,
                $key
            );
        } elseif (
            $cipher === 'aes_256_gcm'
            && function_exists('openssl_decrypt')
        ) {
            $tag = base64_decode(
                (string) ($envelope['tag'] ?? ''),
                true
            );

            if ($tag === false) {
                throw new RuntimeException(
                    'notification_secret_payload_invalid'
                );
            }

            $plain = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag
            );
        } else {
            throw new RuntimeException(
                'notification_secret_cipher_unsupported'
            );
        }

        if (!is_string($plain)) {
            throw new RuntimeException(
                'notification_secret_decryption_failed'
            );
        }

        $secrets = json_decode(
            $plain,
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($secrets)) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        return $secrets;
    }

    public function mask(array $secrets): array
    {
        $masked = [];

        foreach ($secrets as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text === '') {
                $masked[$key] = '';
                continue;
            }

            $length = mb_strlen($text, 'UTF-8');

            if ($length <= 4) {
                $masked[$key] = str_repeat('â€¢', $length);
                continue;
            }

            $masked[$key] =
                mb_substr($text, 0, 2, 'UTF-8')
                . str_repeat('â€¢', max(4, $length - 4))
                . mb_substr($text, -2, null, 'UTF-8');
        }

        return $masked;
    }

    private function normalize(array $secrets): array
    {
        $normalized = [];

        foreach ($secrets as $key => $value) {
            $key = strtolower(trim((string) $key));

            if (
                $key === ''
                || preg_match(
                    '/^[a-z][a-z0-9_]{0,79}$/',
                    $key
                ) !== 1
                || is_array($value)
                || is_object($value)
                || is_resource($value)
            ) {
                continue;
            }

            $normalized[$key] = trim(
                (string) ($value ?? '')
            );
        }

        ksort($normalized);

        return $normalized;
    }

    private function key(): string
    {
        $applicationKey = trim(
            (string) Env::get('APP_KEY', '')
        );

        if ($applicationKey === '') {
            throw new RuntimeException(
                'notification_secret_key_missing'
            );
        }

        if (str_starts_with($applicationKey, 'base64:')) {
            $decoded = base64_decode(
                substr($applicationKey, 7),
                true
            );

            if ($decoded !== false && $decoded !== '') {
                $applicationKey = $decoded;
            }
        }

        return hash(
            'sha256',
            "ipkf:notification-provider:v1\0"
            . $applicationKey,
            true
        );
    }
}
