<?php

namespace App\Services;

use IPKF\Database\Database;
use IPKF\Support\Env;

class MfaDeliveryChannelService extends BaseService
{
    public function registry(): array
    {
        return ['totp', 'recovery', 'email', 'sms', 'bot'];
    }

    public function configuredMethods(): array
    {
        $methods = ['totp', 'recovery'];

        foreach (['email', 'sms', 'bot'] as $method) {
            if ($this->configured($method)) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    public function configured(string $method): bool
    {
        return match ($method) {
            'email' => filter_var(Env::get('MFA_EMAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
                && Env::get('MAIL_HOST', '') !== ''
                && Env::get('MAIL_FROM_ADDRESS', '') !== '',
            'sms' => filter_var(Env::get('MFA_SMS_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
                && Env::get('KAVENEGAR_API_KEY', '') !== ''
                && Env::get('KAVENEGAR_SENDER', '') !== '',
            'bot' => filter_var(Env::get('MFA_BOT_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
                && Env::get('BALE_BOT_TOKEN', '') !== ''
                && Env::get('BALE_API_BASE_URL', '') !== '',
            default => in_array($method, ['totp', 'recovery'], true),
        };
    }

    public function createChallenge(int $userId, string $method, string $purpose = 'mfa_login'): array
    {
        if (!in_array($method, ['email', 'sms', 'bot'], true)) {
            return ['status' => 'error', 'error' => 'unsupported_channel'];
        }

        if (!$this->configured($method)) {
            return ['status' => 'error', 'error' => 'channel_not_configured'];
        }

        /*
         * SMS_POLICY_MFA_GATE_V1
         *
         * Temporary provider-hour restrictions must
         * not consume the MFA challenge rate limit.
         */
        if ($method === 'sms') {
            $policy =
                (new SmsDeliveryPolicyService())
                    ->decision();

            if (!($policy['allowed'] ?? false)) {
                return [
                    'status' => 'error',
                    'error' =>
                        (string) (
                            $policy['status']
                            ?? 'sms_window_closed'
                        ),
                    'next_allowed_at' =>
                        $policy[
                            'next_allowed_at'
                        ] ?? null,
                ];
            }
        }

        if (!$this->allowedByRateLimit($userId, $method)) {
            return ['status' => 'error', 'error' => 'rate_limited'];
        }

        $code = (string) random_int(100000, 999999);
        if (!$this->deliver($userId, $method, $code)) {
            return ['status' => 'error', 'error' => 'channel_delivery_failed'];
        }

        $db = Database::connect();
        $statement = $db->prepare("
            INSERT INTO mfa_delivery_challenges (
                user_id, method, purpose, code_hash, expires_at, created_ip,
                created_user_agent, created_at, updated_at
            )
            VALUES (?, ?, ?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE), ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $statement->execute([
            $userId,
            $method,
            $purpose,
            password_hash($code, PASSWORD_DEFAULT),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return [
            'status' => 'ok',
            'method' => $method,
            'expires_in' => 300,
            'dev_otp' => $this->devExposeOtp() ? $code : null,
        ];
    }

    public function verifyChallenge(int $userId, string $method, string $code): bool
    {
        $db = Database::connect();
        $statement = $db->prepare("
            SELECT id, code_hash, attempts
            FROM mfa_delivery_challenges
            WHERE user_id = ?
              AND method = ?
              AND consumed_at IS NULL
              AND expires_at >= CURRENT_TIMESTAMP
            ORDER BY id DESC
            LIMIT 1
        ");
        $statement->execute([$userId, $method]);
        $challenge = $statement->fetch();

        if (!$challenge || (int) $challenge['attempts'] >= 5) {
            return false;
        }

        if (!password_verify($code, (string) $challenge['code_hash'])) {
            $db->prepare('UPDATE mfa_delivery_challenges SET attempts = attempts + 1, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([(int) $challenge['id']]);
            return false;
        }

        $db->prepare('UPDATE mfa_delivery_challenges SET consumed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([(int) $challenge['id']]);

        return true;
    }

    private function allowedByRateLimit(int $userId, string $method): bool
    {
        $statement = Database::connect()->prepare("
            SELECT COUNT(*)
            FROM mfa_delivery_challenges
            WHERE user_id = ?
              AND method = ?
              AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 10 MINUTE)
        ");
        $statement->execute([$userId, $method]);

        return (int) $statement->fetchColumn() < 5;
    }

    private function deliver(int $userId, string $method, string $code): bool
    {
        return match ($method) {
            'email' => $this->deliverEmail($userId, $code),
            'sms' => $this->deliverSms($userId, $code),
            'bot' => $this->deliverBot($userId, $code),
            default => false,
        };
    }

    private function deliverEmail(int $userId, string $code): bool
    {
        $destination = $this->destination($userId, 'email');

        if ($destination === null || !function_exists('mail')) {
            return false;
        }

        $subject = 'IPKF verification code';
        $body = "Your IPKF verification code is: {$code}";
        $headers = 'From: ' . (string) Env::get('MAIL_FROM_ADDRESS', '');

        return @mail($destination, $subject, $body, $headers);
    }

    private function deliverSms(int $userId, string $code): bool
    {
        $destination = $this->destination($userId, 'mobile');

        if ($destination === null || !function_exists('curl_init')) {
            return false;
        }

        $apiKey = rawurlencode((string) Env::get('KAVENEGAR_API_KEY', ''));
        $sender = rawurlencode((string) Env::get('KAVENEGAR_SENDER', ''));
        $receptor = rawurlencode($destination);
        $message = rawurlencode("IPKF verification code: {$code}");
        $url = "https://api.kavenegar.com/v1/{$apiKey}/sms/send.json?sender={$sender}&receptor={$receptor}&message={$message}";

        return $this->postUrl($url);
    }

    private function deliverBot(int $userId, string $code): bool
    {
        $destination = $this->destination($userId, 'mobile');

        if ($destination === null || !function_exists('curl_init')) {
            return false;
        }

        $baseUrl = rtrim((string) Env::get('BALE_API_BASE_URL', ''), '/');
        $token = rawurlencode((string) Env::get('BALE_BOT_TOKEN', ''));

        if ($baseUrl === '' || $token === '') {
            return false;
        }

        return $this->postUrl("{$baseUrl}/bot{$token}/sendMessage", [
            'chat_id' => $destination,
            'text' => "IPKF verification code: {$code}",
        ]);
    }

    private function destination(int $userId, string $field): ?string
    {
        $statement = Database::connect()->prepare("
            SELECT users.email, users.mobile, persons.email AS person_email, persons.mobile AS person_mobile
            FROM users
            LEFT JOIN persons ON persons.id = users.person_id
            WHERE users.id = ?
            LIMIT 1
        ");
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!$user) {
            return null;
        }

        if ($field === 'email') {
            return $user['email'] ?: ($user['person_email'] ?: null);
        }

        return $user['mobile'] ?: ($user['person_mobile'] ?: null);
    }

    private function postUrl(string $url, ?array $payload = null): bool
    {
        $curl = curl_init($url);

        if ($curl === false) {
            return false;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);

        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return $status >= 200 && $status < 300;
    }

    private function devExposeOtp(): bool
    {
        return Env::isDebug() && filter_var(Env::get('MFA_DEV_EXPOSE_OTP', false), FILTER_VALIDATE_BOOLEAN);
    }
}
