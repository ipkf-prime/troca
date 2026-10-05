<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\IdentityVerificationRepository;
use App\Repositories\UserRepository;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

final class PasswordRecoveryService extends BaseService
{
    private const PURPOSE =
        'auth_password_reset';

    private const MAX_USER_REQUESTS_10_MIN =
        5;

    private const MAX_IP_REQUESTS_10_MIN =
        10;

    private const MAX_VERIFY_ATTEMPTS =
        5;

    private PDO $db;

    private UserRepository $users;

    private IdentityVerificationRepository $verification;

    private IdentityOtpDeliveryService $delivery;

    public function __construct(
        ?PDO $db = null,
        ?UserRepository $users = null,
        ?IdentityVerificationRepository $verification = null,
        ?IdentityOtpDeliveryService $delivery = null
    ) {
        $this->db =
            $db
            ?? (new ConnectionResolver())
                ->resolve('core.primary');

        $this->users =
            $users
            ?? new UserRepository();

        $this->verification =
            $verification
            ?? new IdentityVerificationRepository();

        $this->delivery =
            $delivery
            ?? new IdentityOtpDeliveryService();
    }

    public function request(
        string $identifier,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        /*
         * AUTH_PASSWORD_RECOVERY_A5_R3
         * AUTH_PASSWORD_RECOVERY_MULTI_CHANNEL_R3_M2C
         *
         * One logical recovery challenge is fanned out to every
         * currently verified recovery channel. Public responses
         * remain generic for account-enumeration resistance.
         */
        $identifier =
            trim($identifier);

        if ($identifier === '') {
            return $this->accepted();
        }

        $user =
            $this->users
                ->findByLoginIdentifier(
                    $identifier
                );

        if (!is_array($user)) {
            return $this->accepted();
        }

        $userId =
            (int) (
                $user['id']
                ?? 0
            );

        $account =
            $this->verification
                ->account(
                    $userId
                );

        if (
            $userId < 1
            || !is_array($account)
            || (string) (
                $account['status']
                ?? ''
            ) !== 'active'
        ) {
            return $this->accepted();
        }

        $destinations =
            $this->recoveryDestinations(
                $userId,
                $account
            );

        if (
            $destinations['sms'] === null
            && $destinations['email'] === null
            && $destinations['bale'] === null
        ) {
            return $this->accepted();
        }

        $ip =
            trim(
                (string) (
                    $ip
                    ?? ''
                )
            );

        /*
         * New canonical challenges use method=recovery.
         * Legacy open SMS recovery challenges remain counted
         * during the transition to prevent a rate-limit bypass.
         */
        $recentUserChallenges =
            $this->verification
                ->recentChallengeCountByPurposePrefix(
                    $userId,
                    'recovery',
                    self::PURPOSE,
                    10
                )
            + $this->verification
                ->recentChallengeCountByPurposePrefix(
                    $userId,
                    'sms',
                    self::PURPOSE,
                    10
                );

        $recentIpChallenges = 0;

        if ($ip !== '') {
            $recentIpChallenges =
                $this->verification
                    ->recentChallengeCountByIp(
                        $ip,
                        'recovery',
                        self::PURPOSE,
                        10
                    )
                + $this->verification
                    ->recentChallengeCountByIp(
                        $ip,
                        'sms',
                        self::PURPOSE,
                        10
                    );
        }

        if (
            $recentUserChallenges
                >= self::MAX_USER_REQUESTS_10_MIN
            || $recentIpChallenges
                >= self::MAX_IP_REQUESTS_10_MIN
        ) {
            return $this->accepted();
        }

        $code =
            (string) random_int(
                100000,
                999999
            );

        $hash =
            password_hash(
                $code,
                PASSWORD_DEFAULT
            );

        if (
            !is_string($hash)
            || $hash === ''
        ) {
            return $this->accepted();
        }

        try {
            $challengeId =
                $this->verification
                    ->createChallenge([
                        'user_id' =>
                            $userId,

                        'method' =>
                            'recovery',

                        'purpose' =>
                            self::PURPOSE,

                        'code_hash' =>
                            $hash,

                        'created_ip' =>
                            $ip !== ''
                                ? $ip
                                : null,

                        'created_user_agent' =>
                            trim(
                                (string) (
                                    $userAgent
                                    ?? ''
                                )
                            ) ?: null,
                    ]);

        } catch (Throwable) {
            return $this->accepted();
        }

        if ($challengeId < 1) {
            return $this->accepted();
        }

        $delivered = false;
        $devToken = null;

        if (
            is_string(
                $destinations['sms']
            )
        ) {
            try {
                $result =
                    $this->delivery
                        ->deliver(
                            'mobile',
                            $destinations['sms'],
                            $code,
                            'auth.password_reset.mobile_otp'
                        );

                if (
                    ($result['ok'] ?? false)
                    === true
                ) {
                    $delivered = true;

                    $devToken =
                        $devToken
                        ?? (
                            $result['dev_token']
                            ?? null
                        );
                }

            } catch (Throwable) {
                /*
                 * Continue with the remaining verified channels.
                 */
            }
        }

        if (
            is_string(
                $destinations['email']
            )
        ) {
            try {
                $result =
                    $this->delivery
                        ->deliver(
                            'email',
                            $destinations['email'],
                            $code,
                            'auth.password_reset.email_otp',
                            [],
                            $userId
                        );

                if (
                    ($result['ok'] ?? false)
                    === true
                ) {
                    $delivered = true;

                    $devToken =
                        $devToken
                        ?? (
                            $result['dev_token']
                            ?? null
                        );
                }

            } catch (Throwable) {
                /*
                 * Continue with Bale when available.
                 */
            }
        }

        if (
            is_string(
                $destinations['bale']
            )
        ) {
            try {
                if (
                    $this->deliverPrivateIpKfBot(
                        $destinations['bale'],
                        $code
                    )
                ) {
                    $delivered = true;
                }

            } catch (Throwable) {
                /*
                 * Never leak transport failures from recovery.
                 */
            }
        }

        if (!$delivered) {
            $this->verification
                ->consume(
                    $challengeId
                );

            return $this->accepted();
        }

        return [
            'ok' =>
                true,

            'status' =>
                'accepted',

            'dev_token' =>
                $devToken,
        ];
    }

    public function confirm(
        string $identifier,
        string $code,
        string $password,
        string $confirmation
    ): array {
        $identifier =
            trim($identifier);

        $code =
            preg_replace(
                '/\D+/',
                '',
                $code
            ) ?: '';

        $passwordError =
            $this->passwordError(
                $identifier,
                $password,
                $confirmation
            );

        if ($passwordError !== null) {
            return [
                'ok' => false,
                'status' =>
                    $passwordError,
            ];
        }

        if (
            $identifier === ''
            || strlen($code) !== 6
        ) {
            return $this->invalidCode();
        }

        $user =
            $this->users
                ->findByLoginIdentifier(
                    $identifier
                );

        if (!is_array($user)) {
            return $this->invalidCode();
        }

        $userId =
            (int) ($user['id'] ?? 0);

        $account =
            $this->verification
                ->account(
                    $userId
                );

        if (
            $userId < 1
            || !is_array($account)
            || (string) (
                $account['status']
                ?? ''
            ) !== 'active'
        ) {
            return $this->invalidCode();
        }

        $destinations =
            $this->recoveryDestinations(
                $userId,
                $account
            );

        if (
            $destinations['sms'] === null
            && $destinations['email'] === null
            && $destinations['bale'] === null
        ) {
            return $this->invalidCode();
        }

        $challenge =
            $this->verification
                ->latestChallenge(
                    $userId,
                    'recovery',
                    self::PURPOSE
                );

        if (
            !is_array($challenge)
            || (int) (
                $challenge['attempts']
                ?? 0
            ) >= self::MAX_VERIFY_ATTEMPTS
        ) {
            return $this->invalidCode();
        }

        if (
            !password_verify(
                $code,
                (string) (
                    $challenge[
                        'code_hash'
                    ] ?? ''
                )
            )
        ) {
            $this->verification
                ->markAttempt(
                    (int) (
                        $challenge['id']
                        ?? 0
                    )
                );

            return $this->invalidCode();
        }

        $currentHash =
            $this->users
                ->passwordHashForUser(
                    $userId
                );

        if (
            is_string($currentHash)
            && $currentHash !== ''
            && password_verify(
                $password,
                $currentHash
            )
        ) {
            return [
                'ok' => false,
                'status' =>
                    'same_password',
            ];
        }

        /*
         * Consume every still-open reset challenge before
         * changing the password. If the password write fails,
         * the user may request a fresh OTP; a previously proven
         * OTP must never remain replayable.
         */
        if (!$this->consumeOpenChallenges(
            $userId
        )) {
            return [
                'ok' => false,
                'status' =>
                    'reset_failed',
            ];
        }

        $newHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        if (
            !is_string($newHash)
            || $newHash === ''
            || !$this->users
                ->replacePasswordAfterRecovery(
                    $userId,
                    $newHash
                )
        ) {
            return [
                'ok' => false,
                'status' =>
                    'reset_failed',
            ];
        }

        return [
            'ok' => true,
            'status' =>
                'password_reset',
        ];
    }

    private function passwordError(
        string $identifier,
        string $password,
        string $confirmation
    ): ?string {
        if ($password !== $confirmation) {
            return 'password_confirmation';
        }

        $length =
            function_exists('mb_strlen')
                ? mb_strlen(
                    $password,
                    'UTF-8'
                )
                : strlen($password);

        if (
            $length < 8
            || $length > 128
            || preg_match(
                '/\p{L}/u',
                $password
            ) !== 1
            || preg_match(
                '/[0-9]/',
                $password
            ) !== 1
        ) {
            return 'password_policy';
        }

        $user =
            $identifier !== ''
                ? $this->users
                    ->findByLoginIdentifier(
                        $identifier
                    )
                : null;

        if (is_array($user)) {
            $tokens = array_filter([
                strtolower(
                    trim(
                        (string) (
                            $user['username']
                            ?? ''
                        )
                    )
                ),
                strtolower(
                    trim(
                        (string) strtok(
                            (string) (
                                $user['email']
                                ?? ''
                            ),
                            '@'
                        )
                    )
                ),
            ]);

            $lowerPassword =
                function_exists(
                    'mb_strtolower'
                )
                    ? mb_strtolower(
                        $password,
                        'UTF-8'
                    )
                    : strtolower(
                        $password
                    );

            foreach ($tokens as $token) {
                if (
                    strlen($token) >= 4
                    && str_contains(
                        $lowerPassword,
                        $token
                    )
                ) {
                    return 'password_identity';
                }
            }
        }

        return null;
    }

    private function consumeOpenChallenges(
        int $userId
    ): bool {
        try {
            $statement =
                $this->db->prepare("
                    UPDATE
                        mfa_delivery_challenges
                    SET consumed_at =
                            CURRENT_TIMESTAMP,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE user_id = ?
                      AND method IN (
                            'recovery',
                            'sms'
                      )
                      AND purpose = ?
                      AND consumed_at IS NULL
                ");

            $statement->execute([
                $userId,
                self::PURPOSE,
            ]);

            return true;

        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     sms:?string,
     *     email:?string,
     *     bale:?string
     * }
     */
    private function recoveryDestinations(
        int $userId,
        array $account
    ): array {
        $mobile =
            trim(
                (string) (
                    $account['mobile_norm']
                    ?? $account['mobile']
                    ?? ''
                )
            );

        $sms =
            !empty(
                $account[
                    'mobile_verified_at'
                ]
            )
            && preg_match(
                '/^09[0-9]{9}$/D',
                $mobile
            ) === 1
                ? $mobile
                : null;

        $email =
            strtolower(
                trim(
                    (string) (
                        $account['email_norm']
                        ?? $account['email']
                        ?? ''
                    )
                )
            );

        $verifiedEmail =
            !empty(
                $account[
                    'email_verified_at'
                ]
            )
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) !== false
                ? $email
                : null;

        return [
            'sms' =>
                $sms,

            'email' =>
                $verifiedEmail,

            'bale' =>
                $this->verifiedIpKfBotChatId(
                    $userId
                ),
        ];
    }

    private function verifiedIpKfBotChatId(
        int $userId
    ): ?string {
        if ($userId < 1) {
            return null;
        }

        try {
            $repository =
                new \App\Repositories\NotificationMessengerEnrollmentRepository();

            $providers =
                $repository
                    ->membershipAuthBaleProviders();

            $matches = [];

            foreach (
                $providers
                as $provider
            ) {
                $configuration =
                    json_decode(
                        (string) (
                            $provider[
                                'configuration_json'
                            ] ?? ''
                        ),
                        true
                    );

                if (
                    !is_array(
                        $configuration
                    )
                ) {
                    continue;
                }

                $username =
                    ltrim(
                        trim(
                            (string) (
                                $configuration[
                                    'bot_username'
                                ] ?? ''
                            )
                        ),
                        '@'
                    );

                $purpose =
                    trim(
                        (string) (
                            $configuration[
                                'bot_purpose_code'
                            ] ?? ''
                        )
                    );

                if (
                    strcasecmp(
                        $username,
                        'ipkfbot'
                    ) === 0
                    && $purpose ===
                        'membership_auth'
                ) {
                    $matches[] =
                        $provider;
                }
            }

            if (
                count($matches)
                !== 1
            ) {
                return null;
            }

            $providerId =
                (int) (
                    $matches[0]['id']
                    ?? 0
                );

            if ($providerId < 1) {
                return null;
            }

            $statuses =
                $repository
                    ->connectionStatuses(
                        $providerId
                    );

            $binding =
                $statuses[
                    $userId
                ]['binding']
                ?? null;

            if (
                !is_array($binding)
                || trim(
                    (string) (
                        $binding[
                            'verified_at'
                        ] ?? ''
                    )
                ) === ''
            ) {
                return null;
            }

            $chatId =
                trim(
                    (string) (
                        $binding[
                            'chat_id'
                        ] ?? ''
                    )
                );

            if (
                preg_match(
                    '/^-?[0-9]{1,20}$/D',
                    $chatId
                ) !== 1
            ) {
                return null;
            }

            return $chatId;

        } catch (Throwable) {
            return null;
        }
    }

    private function deliverPrivateIpKfBot(
        string $chatId,
        string $code
    ): bool {
        if (
            preg_match(
                '/^-?[0-9]{1,20}$/D',
                $chatId
            ) !== 1
            || preg_match(
                '/^[0-9]{6}$/D',
                $code
            ) !== 1
        ) {
            return false;
        }

        try {
            $message =
                (
                    new DynamicMessageTemplateService()
                )->render(
                    'auth.password_reset.bale_otp',
                    'messenger',
                    [
                        'code' =>
                            $code,

                        'expires_minutes' =>
                            '5',
                    ]
                );

        } catch (Throwable) {
            return false;
        }

        $body =
            trim(
                (string) (
                    $message['body']
                    ?? ''
                )
            );

        if ($body === '') {
            return false;
        }

        $config =
            $this->privateIpKfBotConfig();

        if ($config === null) {
            return false;
        }

        if (
            !function_exists(
                'curl_init'
            )
        ) {
            return false;
        }

        $payload =
            json_encode(
                [
                    'chat_id' =>
                        (int) $chatId,

                    'text' =>
                        $body,
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

        if (!is_string($payload)) {
            return false;
        }

        $url =
            $config['api_base']
            . '/bot'
            . rawurlencode(
                $config['bot_token']
            )
            . '/sendMessage';

        $curl =
            curl_init(
                $url
            );

        if ($curl === false) {
            return false;
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_POST =>
                    true,

                CURLOPT_RETURNTRANSFER =>
                    true,

                CURLOPT_CONNECTTIMEOUT =>
                    10,

                CURLOPT_TIMEOUT =>
                    20,

                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],

                CURLOPT_POSTFIELDS =>
                    $payload,
            ]
        );

        $response =
            curl_exec(
                $curl
            );

        $status =
            (int) curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

        curl_close(
            $curl
        );

        if (
            !is_string($response)
            || $status < 200
            || $status >= 300
        ) {
            return false;
        }

        $decoded =
            json_decode(
                $response,
                true
            );

        return is_array($decoded)
            && (
                $decoded['ok']
                ?? false
            ) === true;
    }

    /**
     * @return null|array{
     *     api_base:string,
     *     bot_token:string
     * }
     */
    private function privateIpKfBotConfig(): ?array
    {
        $pointer =
            trim(
                (string) \IPKF\Support\Env::get(
                    'PASSWORD_RECOVERY_BALE_SECRET_FILE',
                    ''
                )
            );

        if (
            $pointer === ''
            || !is_file($pointer)
            || is_link($pointer)
        ) {
            return null;
        }

        if (
            (
                fileperms(
                    $pointer
                )
                & 0777
            ) !== 0600
        ) {
            return null;
        }

        $raw =
            file_get_contents(
                $pointer
            );

        $config =
            is_string($raw)
                ? json_decode(
                    $raw,
                    true
                )
                : null;

        if (!is_array($config)) {
            return null;
        }

        $username =
            ltrim(
                trim(
                    (string) (
                        $config[
                            'bot_username'
                        ] ?? ''
                    )
                ),
                '@'
            );

        $purpose =
            trim(
                (string) (
                    $config[
                        'bot_purpose_code'
                    ]
                    ?? $config[
                        'bot_purpose'
                    ]
                    ?? $config[
                        'purpose'
                    ]
                    ?? ''
                )
            );

        $apiBase =
            rtrim(
                trim(
                    (string) (
                        $config[
                            'api_base'
                        ] ?? ''
                    )
                ),
                '/'
            );

        $botToken =
            trim(
                (string) (
                    $config[
                        'bot_token'
                    ] ?? ''
                )
            );

        $parts =
            parse_url(
                $apiBase
            );

        if (
            strcasecmp(
                $username,
                'ipkfbot'
            ) !== 0
            || $purpose !==
                'membership_auth'
            || $botToken === ''
            || !is_array($parts)
            || strtolower(
                (string) (
                    $parts['scheme']
                    ?? ''
                )
            ) !== 'https'
            || strtolower(
                (string) (
                    $parts['host']
                    ?? ''
                )
            ) !== 'tapi.bale.ai'
        ) {
            return null;
        }

        return [
            'api_base' =>
                $apiBase,

            'bot_token' =>
                $botToken,
        ];
    }

    private function accepted(): array
    {
        return [
            'ok' => true,
            'status' => 'accepted',
        ];
    }

    private function invalidCode(): array
    {
        return [
            'ok' => false,
            'status' =>
                'invalid_or_expired_code',
        ];
    }
}
