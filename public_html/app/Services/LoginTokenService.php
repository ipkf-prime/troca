<?php

namespace App\Services;

use App\Repositories\LoginTokenRepository;
use DateTimeImmutable;
use DateTimeZone;
use IPKF\Support\Env;

class LoginTokenService extends BaseService
{
    public function __construct(protected ?LoginTokenRepository $tokens = null)
    {
        $this->tokens ??= new LoginTokenRepository();
    }

    public function issue(int $userId, string $purpose, string $source, ?string $redirectPath, ?int $createdByUserId): array
    {
        $ttlSeconds = 300;
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAtUtc = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify("+{$ttlSeconds} seconds");
        $expiresAt = $expiresAtUtc->format('Y-m-d H:i:s');

        $this->tokens->create([
            'user_id' => $userId,
            'token_hash' => $this->hash($plain),
            'purpose' => $purpose,
            'source' => $source,
            'redirect_path' => $redirectPath,
            'expires_at' => $expiresAt,
            'created_by_user_id' => $createdByUserId,
        ]);

        return [
            'token' => $plain,
            'login_url' => $this->urlBase() . '/auth/token-login?token=' . rawurlencode($plain),
            'expires_at' => $this->isoUtc($expiresAtUtc),
            'expires_at_utc' => $this->isoUtc($expiresAtUtc),
            'expires_at_local' => $expiresAtUtc
                ->setTimezone(new DateTimeZone($this->timezone()))
                ->format(DATE_ATOM),
            'timezone' => $this->timezone(),
            'ttl_seconds' => $ttlSeconds,
        ];
    }

    public function urlBase(): string
    {
        $configured = trim((string) Env::get('APP_URL', ''));

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return "{$scheme}://{$host}";
    }

    public function timezone(): string
    {
        $timezone = (string) Env::get('APP_TIMEZONE', 'Asia/Tehran');

        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (\Throwable) {
            return 'Asia/Tehran';
        }
    }

    public function consume(string $plain): ?array
    {
        $token = $this->tokens->findConsumable($this->hash($plain));

        if ($token === null) {
            return null;
        }

        $this->tokens->markConsumed((int) $token['id']);

        return $token;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function isoUtc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
