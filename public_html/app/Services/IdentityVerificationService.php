<?php

namespace App\Services;

use App\Repositories\IdentityVerificationRepository;
use Throwable;

class IdentityVerificationService extends BaseService
{
    private const MAX_REQUESTS = 5;

    public function __construct(
        private ?IdentityVerificationRepository $verification = null,
        private ?IdentityOtpDeliveryService $delivery = null
    ) {
        $this->verification ??=
            new IdentityVerificationRepository();
        $this->delivery ??=
            new IdentityOtpDeliveryService();
    }

    public function page(int $userId): array
    {
        $account = $this->verification->account($userId)
            ?? [];

        return [
            'email' => (string) (
                $account['email'] ?? ''
            ),
            'email_norm' => (string) (
                $account['email_norm']
                ?? ''
            ),
            'mobile' => (string) (
                $account['mobile'] ?? ''
            ),
            'email_verified' => !empty(
                $account['email_verified_at']
            ),
            'mobile_verified' => !empty(
                $account['mobile_verified_at']
            ),
        ];
    }

    public function request(
        int $userId,
        string $field
    ): array {
        $field = strtolower(trim($field));

        if (!in_array(
            $field,
            ['email', 'mobile'],
            true
        )) {
            return $this->error('invalid_field');
        }

        $account = $this->verification->account($userId);

        if ($account === null) {
            return $this->error('account_not_found');
        }

        if (
            $field === 'email'
            && (
                (string) (
                    $account['status']
                    ?? ''
                ) !== 'active'
                || empty(
                    $account[
                        'mobile_verified_at'
                    ]
                )
            )
        ) {
            return $this->error(
                'email_verification_not_allowed'
            );
        }

        if (
            $field === 'email'
            && !empty(
                $account[
                    'email_verified_at'
                ]
            )
        ) {
            return $this->error(
                'already_verified'
            );
        }

        $destination = trim((string) (
            $account[$field] ?? ''
        ));

        if ($destination === '') {
            return $this->error('destination_missing');
        }

        $method = $field === 'email'
            ? 'email'
            : 'sms';
        $purpose =
            $this->purpose(
                $field,
                $account
            );

        if (
            $this->verification->recentChallengeCount(
                $userId,
                $method,
                $purpose
            ) >= self::MAX_REQUESTS
        ) {
            return $this->error('rate_limited');
        }

        $code = (string) random_int(100000, 999999);
        $delivered = $this->delivery->deliver(
            $field,
            $destination,
            $code,
            null,
            [],
            $userId
        );

        if (($delivered['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'status' => (string) (
                    $delivered['status']
                    ?? 'delivery_failed'
                ),
            ];
        }

        try {
            $this->verification->createChallenge([
                'user_id' => $userId,
                'method' => $method,
                'purpose' => $purpose,
                'code_hash' => password_hash(
                    $code,
                    PASSWORD_DEFAULT
                ),
            ]);
        } catch (Throwable) {
            return $this->error('challenge_not_created');
        }

        return [
            'ok' => true,
            'status' => (string) (
                $delivered['status'] ?? 'sent'
            ),
            'field' => $field,
            'dev_token' => $delivered['dev_token']
                ?? null,
        ];
    }

    public function confirm(
        int $userId,
        string $field,
        string $code
    ): array {
        $field = strtolower(trim($field));
        $code = preg_replace(
            '/\D+/',
            '',
            $code
        ) ?: '';

        if (
            !in_array(
                $field,
                ['email', 'mobile'],
                true
            )
            || strlen($code) !== 6
        ) {
            return $this->error('invalid_code');
        }

        $account =
            $this->verification->account(
                $userId
            );

        if ($account === null) {
            return $this->error(
                'account_not_found'
            );
        }

        if (
            $field === 'email'
            && (
                (string) (
                    $account['status']
                    ?? ''
                ) !== 'active'
                || empty(
                    $account[
                        'mobile_verified_at'
                    ]
                )
            )
        ) {
            return $this->error(
                'email_verification_not_allowed'
            );
        }

        $expectedEmail =
            $field === 'email'
                ? strtolower(
                    trim(
                        (string) (
                            $account['email_norm']
                            ?: $account['email']
                            ?: ''
                        )
                    )
                )
                : '';

        $method = $field === 'email'
            ? 'email'
            : 'sms';
        $challenge = $this->verification
            ->latestChallenge(
                $userId,
                $method,
                $this->purpose(
                    $field,
                    $account
                )
            );

        if (
            $challenge === null
            || (int) ($challenge['attempts'] ?? 0) >= 5
        ) {
            return $this->error(
                'invalid_or_expired_code'
            );
        }

        if (!password_verify(
            $code,
            (string) $challenge['code_hash']
        )) {
            $this->verification->markAttempt(
                (int) $challenge['id']
            );

            return $this->error(
                'invalid_or_expired_code'
            );
        }

        if ($field === 'email') {
            if (
                !$this->verification
                    ->claimVerifiedEmail(
                        $userId,
                        $expectedEmail
                    )
            ) {
                return $this->error(
                    'email_unavailable'
                );
            }

            $this->verification->consume(
                (int) $challenge['id']
            );

        } else {
            $this->verification->consume(
                (int) $challenge['id']
            );

            $this->verification->markVerified(
                $userId,
                $field
            );
        }

        return [
            'ok' => true,
            'status' => 'verified',
            'field' => $field,
        ];
    }

    private function purpose(
        string $field,
        array $account = []
    ): string {
        if ($field !== 'email') {
            return
                'identity_'
                . $field
                . '_verification';
        }

        $email =
            strtolower(
                trim(
                    (string) (
                        $account['email_norm']
                        ?: $account['email']
                        ?: ''
                    )
                )
            );

        return
            'identity_email_verification:'
            . substr(
                hash(
                    'sha256',
                    $email
                ),
                0,
                24
            );
    }

    private function error(string $status): array
    {
        return [
            'ok' => false,
            'status' => $status,
        ];
    }
}
