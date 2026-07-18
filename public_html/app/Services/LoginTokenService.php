<?php

namespace App\Services;

use App\Repositories\LoginTokenRepository;
use IPKF\Support\Env;
use IPKF\Support\Clock;

class LoginTokenService extends BaseService
{
    public function __construct(protected ?LoginTokenRepository $tokens = null)
    {
        $this->tokens ??= new LoginTokenRepository();
    }

    public function issue(int $userId, string $purpose, string $source, ?string $redirectPath, ?int $createdByUserId, int $ttlSeconds = 300, array $metadata = []): array
    {
        $ttlSeconds = max(30, min(300, $ttlSeconds));
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAtUtc = Clock::nowUtc()->modify("+{$ttlSeconds} seconds");
        $expiresAt = Clock::databaseTimestamp($expiresAtUtc);

        $this->tokens->create([
            'user_id' => $userId,
            'token_hash' => password_hash($plain, PASSWORD_DEFAULT),
            'purpose' => $purpose,
            'source' => $source,
            'redirect_path' => $redirectPath,
            'expires_at' => $expiresAt,
            'created_by_user_id' => $createdByUserId,
            'metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);

        return [
            'token' => $plain,
            'login_url' => $this->urlBase() . '/auth/token-login?token=' . rawurlencode($plain),
            'expires_at' => Clock::isoUtc($expiresAtUtc),
            'expires_at_utc' => Clock::isoUtc($expiresAtUtc),
            'expires_at_local' => Clock::convertToDisplayTimezone($expiresAtUtc)
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
        return Clock::displayTimezoneName();
    }

    public function consume(string $plain, ?string $expectedPurpose = null, ?string $expectedSource = null, array $requiredMetadata = []): ?array
    {
        $plain = trim($plain);

        if ($plain === '') {
            return null;
        }

        foreach ($this->tokens->candidates() as $token) {
            if ($this->expired((string) $token['expires_at'])) {
                continue;
            }

            if (($expectedPurpose !== null && !hash_equals($expectedPurpose, (string) ($token['purpose'] ?? '')))
                || ($expectedSource !== null && !hash_equals($expectedSource, (string) ($token['source'] ?? '')))
            ) {
                continue;
            }

            $metadata = json_decode((string) ($token['metadata_json'] ?? ''), true);
            foreach ($requiredMetadata as $key => $value) {
                if (!is_array($metadata) || !hash_equals((string) $value, (string) ($metadata[$key] ?? ''))) {
                    continue 2;
                }
            }

            if (!password_verify($plain, (string) $token['token_hash'])) {
                continue;
            }

            if (!$this->tokens->claim((int) $token['id'])) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private function expired(string $expiresAt): bool
    {
        $expires = Clock::parseStoredInstant($expiresAt);

        if ($expires === null) {
            return true;
        }

        return $expires < Clock::nowUtc();
    }
}
