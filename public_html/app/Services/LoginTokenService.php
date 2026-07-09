<?php

namespace App\Services;

use App\Repositories\LoginTokenRepository;
use IPKF\Support\Env;

class LoginTokenService extends BaseService
{
    public function __construct(protected ?LoginTokenRepository $tokens = null)
    {
        $this->tokens ??= new LoginTokenRepository();
    }

    public function issue(int $userId, string $purpose, string $source, ?string $redirectPath, ?int $createdByUserId): array
    {
        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = date('Y-m-d H:i:s', time() + 300);
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
            'login_url' => rtrim((string) Env::get('APP_URL', ''), '/') . '/auth/token-login?token=' . rawurlencode($plain),
            'expires_at' => $expiresAt,
        ];
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
}
