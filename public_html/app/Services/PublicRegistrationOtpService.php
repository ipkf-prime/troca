<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\IdentityVerificationRepository;
use App\Repositories\UserRepository;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use RuntimeException;
use Throwable;

final class PublicRegistrationOtpService extends BaseService
{
    private const REGISTRATION_LOCK =
        'troca.public_registration';

    private const PURPOSE_PREFIX =
        'public_registration:';

    private const ATTEMPT_MINUTES = 30;

    private const OTP_MINUTES = 5;

    private const RESEND_COOLDOWN_SECONDS = 60;

    private const MAX_USER_REQUESTS_10_MIN = 5;

    private const MAX_IP_REQUESTS_10_MIN = 10;

    private const MAX_VERIFY_ATTEMPTS = 5;

    private PDO $db;

    private IdentityVerificationRepository $verification;

    private IdentityOtpDeliveryService $delivery;

    private UserRepository $users;

    public function __construct(
        ?PDO $db = null,
        ?IdentityVerificationRepository $verification = null,
        ?IdentityOtpDeliveryService $delivery = null,
        ?UserRepository $users = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve('core.primary');

        $this->verification =
            $verification
            ?? new IdentityVerificationRepository();

        $this->delivery =
            $delivery
            ?? new IdentityOtpDeliveryService();

        $this->users =
            $users
            ?? new UserRepository();
    }

    public function canStartFromIp(
        ?string $ip
    ): bool {
        $ip =
            trim(
                (string) (
                    $ip
                    ?? ''
                )
            );

        if ($ip === '') {
            return true;
        }

        $statement =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM public_registration_attempts
                WHERE created_ip = ?
                  AND created_at >=
                      DATE_SUB(
                          CURRENT_TIMESTAMP,
                          INTERVAL 10 MINUTE
                      )
            ");

        $statement->execute([
            $ip,
        ]);

        return
            (int) $statement->fetchColumn()
            < self::MAX_IP_REQUESTS_10_MIN;
    }

    public function startAttempt(
        array $data
    ): array {
        $userId =
            (int) (
                $data['user_id']
                ?? 0
            );

        $fullName =
            trim(
                (string) (
                    $data['full_name']
                    ?? ''
                )
            );

        $mobile =
            trim(
                (string) (
                    $data['mobile']
                    ?? ''
                )
            );

        $email =
            trim(
                (string) (
                    $data['email']
                    ?? ''
                )
            );

        $passwordHash =
            trim(
                (string) (
                    $data['password_hash']
                    ?? ''
                )
            );

        $ip =
            trim(
                (string) (
                    $data['created_ip']
                    ?? ''
                )
            );

        $userAgent =
            trim(
                (string) (
                    $data['created_user_agent']
                    ?? ''
                )
            );

        if (
            $userId < 1
            || $fullName === ''
            || $mobile === ''
            || $passwordHash === ''
        ) {
            return $this->error(
                'attempt_invalid'
            );
        }

        if (
            !$this->canStartFromIp(
                $ip
            )
            || $this->verification
                ->recentChallengeCountByPurposePrefix(
                    $userId,
                    'sms',
                    self::PURPOSE_PREFIX,
                    10
                )
                >= self::MAX_USER_REQUESTS_10_MIN
            || (
                $ip !== ''
                && $this->verification
                    ->recentChallengeCountByIp(
                        $ip,
                        'sms',
                        self::PURPOSE_PREFIX,
                        10
                    )
                    >= self::MAX_IP_REQUESTS_10_MIN
            )
        ) {
            return $this->error(
                'rate_limited'
            );
        }

        try {
            $token =
                bin2hex(
                    random_bytes(32)
                );

            $nonceHash =
                hash(
                    'sha256',
                    $token
                );

            $statement =
                $this->db->prepare("
                    INSERT INTO
                        public_registration_attempts (
                            user_id,
                            nonce_hash,
                            full_name,
                            mobile,
                            mobile_norm,
                            email,
                            email_norm,
                            password_hash,
                            status,
                            verification_attempts,
                            expires_at,
                            created_ip,
                            created_user_agent,
                            created_at,
                            updated_at
                        )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'pending',
                        0,
                        DATE_ADD(
                            CURRENT_TIMESTAMP,
                            INTERVAL 30 MINUTE
                        ),
                        ?,
                        ?,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

            $statement->execute([
                $userId,
                $nonceHash,
                $fullName,
                $mobile,
                $mobile,
                $email !== ''
                    ? $email
                    : null,
                $email !== ''
                    ? $email
                    : null,
                $passwordHash,
                $ip !== ''
                    ? $ip
                    : null,
                $userAgent !== ''
                    ? $userAgent
                    : null,
            ]);

            $attemptId =
                (int) $this->db
                    ->lastInsertId();

            if ($attemptId < 1) {
                throw new RuntimeException(
                    'attempt_insert_failed'
                );
            }

            /*
             * Only the newest form submission may activate
             * this pending identity. Password hashes from
             * superseded submissions are scrubbed.
             */
            $statement =
                $this->db->prepare("
                    UPDATE public_registration_attempts
                    SET status = 'superseded',
                        password_hash = '',
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE user_id = ?
                      AND id <> ?
                      AND status = 'pending'
                ");

            $statement->execute([
                $userId,
                $attemptId,
            ]);

            $attempt =
                $this->attemptById(
                    $attemptId
                );

            if (!is_array($attempt)) {
                throw new RuntimeException(
                    'attempt_reload_failed'
                );
            }

            $delivery =
                $this->sendChallenge(
                    $attempt,
                    false
                );

            return [
                'ok' => true,
                'status' =>
                    (string) (
                        $delivery['status']
                        ?? 'delivery_failed'
                    ),
                'delivery_ok' =>
                    (bool) (
                        $delivery['ok']
                        ?? false
                    ),
                'attempt_id' =>
                    $attemptId,
                'attempt_token' =>
                    $token,
                'masked_mobile' =>
                    $this->maskMobile(
                        $mobile
                    ),
                'dev_token' =>
                    $delivery['dev_token']
                    ?? null,
            ];

        } catch (Throwable) {
            return $this->error(
                'attempt_not_created'
            );
        }
    }

    public function state(
        int $attemptId,
        string $token
    ): array {
        $attempt =
            $this->attemptForToken(
                $attemptId,
                $token
            );

        if (!is_array($attempt)) {
            return $this->error(
                'attempt_invalid'
            );
        }

        $status =
            (string) (
                $attempt['status']
                ?? ''
            );

        if ($status !== 'pending') {
            return [
                'ok' => false,
                'status' =>
                    $status === 'consumed'
                        ? 'already_completed'
                        : 'attempt_invalid',
                'masked_mobile' =>
                    $this->maskMobile(
                        (string) $attempt[
                            'mobile'
                        ]
                    ),
            ];
        }

        if ($this->expired(
            $attempt
        )) {
            return [
                'ok' => false,
                'status' =>
                    'attempt_expired',
                'masked_mobile' =>
                    $this->maskMobile(
                        (string) $attempt[
                            'mobile'
                        ]
                    ),
            ];
        }

        $wait =
            $this->resendWaitSeconds(
                $attempt
            );

        return [
            'ok' => true,
            'status' => 'pending',
            'attempt_id' =>
                (int) $attempt['id'],
            'masked_mobile' =>
                $this->maskMobile(
                    (string) $attempt[
                        'mobile'
                    ]
                ),
            'verification_attempts' =>
                (int) (
                    $attempt[
                        'verification_attempts'
                    ]
                    ?? 0
                ),
            'remaining_attempts' =>
                max(
                    0,
                    self::MAX_VERIFY_ATTEMPTS
                    - (int) (
                        $attempt[
                            'verification_attempts'
                        ]
                        ?? 0
                    )
                ),
            'resend_wait_seconds' =>
                $wait,
            'can_resend' =>
                $wait === 0,
            'expires_at' =>
                (string) (
                    $attempt['expires_at']
                    ?? ''
                ),
        ];
    }

    public function resend(
        int $attemptId,
        string $token
    ): array {
        $attempt =
            $this->attemptForToken(
                $attemptId,
                $token
            );

        if (
            !is_array($attempt)
            || (string) (
                $attempt['status']
                ?? ''
            ) !== 'pending'
        ) {
            return $this->error(
                'attempt_invalid'
            );
        }

        if ($this->expired(
            $attempt
        )) {
            return $this->error(
                'attempt_expired'
            );
        }

        if (
            (int) (
                $attempt[
                    'verification_attempts'
                ]
                ?? 0
            )
            >= self::MAX_VERIFY_ATTEMPTS
        ) {
            return $this->error(
                'attempt_locked'
            );
        }

        $wait =
            $this->resendWaitSeconds(
                $attempt
            );

        if ($wait > 0) {
            return [
                'ok' => false,
                'status' =>
                    'resend_cooldown',
                'wait_seconds' =>
                    $wait,
            ];
        }

        return $this->sendChallenge(
            $attempt,
            true
        );
    }

    public function confirm(
        int $attemptId,
        string $token,
        string $code
    ): array {
        $attempt =
            $this->attemptForToken(
                $attemptId,
                $token
            );

        if (!is_array($attempt)) {
            return $this->error(
                'attempt_invalid'
            );
        }

        if (
            (string) (
                $attempt['status']
                ?? ''
            ) !== 'pending'
        ) {
            return $this->error(
                'attempt_invalid'
            );
        }

        if ($this->expired(
            $attempt
        )) {
            return $this->error(
                'attempt_expired'
            );
        }

        if (
            (int) (
                $attempt[
                    'verification_attempts'
                ]
                ?? 0
            )
            >= self::MAX_VERIFY_ATTEMPTS
        ) {
            return $this->error(
                'attempt_locked'
            );
        }

        $code =
            $this->normalizeCode(
                $code
            );

        if (
            preg_match(
                '/^\d{6}$/D',
                $code
            ) !== 1
        ) {
            return $this->error(
                'invalid_code'
            );
        }

        $purpose =
            $this->purpose(
                (int) $attempt['id']
            );

        $challenge =
            $this->verification
                ->latestChallenge(
                    (int) $attempt[
                        'user_id'
                    ],
                    'sms',
                    $purpose
                );

        if (
            !is_array($challenge)
            || (int) (
                $challenge['attempts']
                ?? 0
            ) >= self::MAX_VERIFY_ATTEMPTS
        ) {
            return $this->error(
                'invalid_or_expired_code'
            );
        }

        if (
            !password_verify(
                $code,
                (string) (
                    $challenge[
                        'code_hash'
                    ]
                    ?? ''
                )
            )
        ) {
            $this->verification
                ->markAttempt(
                    (int) $challenge['id']
                );

            $this->incrementAttemptFailure(
                (int) $attempt['id']
            );

            return $this->error(
                'invalid_or_expired_code'
            );
        }

        $activated =
            $this->activate(
                $attempt,
                (int) $challenge['id']
            );

        if (!$activated) {
            return $this->error(
                'activation_failed'
            );
        }

        return [
            'ok' => true,
            'status' => 'verified',
            'user_id' =>
                (int) $attempt[
                    'user_id'
                ],
        ];
    }

    private function sendChallenge(
        array $attempt,
        bool $resend
    ): array {
        /*
         * SMS_POLICY_REGISTRATION_GATE_V1
         *
         * Do not create a challenge while the
         * provider line is outside its permitted
         * delivery window.
         */
        $smsPolicy =
            (new SmsDeliveryPolicyService())
                ->decision();

        if (!($smsPolicy['allowed'] ?? false)) {
            return [
                'ok' => false,
                'status' =>
                    (string) (
                        $smsPolicy['status']
                        ?? 'sms_window_closed'
                    ),
                'next_allowed_at' =>
                    $smsPolicy[
                        'next_allowed_at'
                    ] ?? null,
                'dev_token' => null,
            ];
        }


        $userId =
            (int) $attempt['user_id'];

        $ip =
            trim(
                (string) (
                    $attempt[
                        'created_ip'
                    ]
                    ?? ''
                )
            );

        if (
            $this->verification
                ->recentChallengeCountByPurposePrefix(
                    $userId,
                    'sms',
                    self::PURPOSE_PREFIX,
                    10
                )
                >= self::MAX_USER_REQUESTS_10_MIN
            || (
                $ip !== ''
                && $this->verification
                    ->recentChallengeCountByIp(
                        $ip,
                        'sms',
                        self::PURPOSE_PREFIX,
                        10
                    )
                    >= self::MAX_IP_REQUESTS_10_MIN
            )
        ) {
            return $this->error(
                'rate_limited'
            );
        }

        if (
            $resend
            && $this->resendWaitSeconds(
                $attempt
            ) > 0
        ) {
            return $this->error(
                'resend_cooldown'
            );
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
            return $this->error(
                'challenge_not_created'
            );
        }

        $purpose =
            $this->purpose(
                (int) $attempt['id']
            );

        try {
            $challengeId =
                $this->verification
                    ->createChallenge([
                        'user_id' =>
                            $userId,
                        'method' =>
                            'sms',
                        'purpose' =>
                            $purpose,
                        'code_hash' =>
                            $hash,
                        'created_ip' =>
                            $ip !== ''
                                ? $ip
                                : null,
                        'created_user_agent' =>
                            $attempt[
                                'created_user_agent'
                            ]
                            ?? null,
                    ]);
        } catch (Throwable) {
            return $this->error(
                'challenge_not_created'
            );
        }

        if ($challengeId < 1) {
            return $this->error(
                'challenge_not_created'
            );
        }

        $delivered =
            $this->delivery->deliver(
                'mobile',
                (string) $attempt[
                    'mobile'
                ],
                $code,
                'auth.registration.mobile_otp'
            );

        if (
            ($delivered['ok'] ?? false)
            !== true
        ) {
            /*
             * A challenge whose transport failed must
             * never be accepted later.
             */
            $this->verification
                ->consume(
                    $challengeId
                );

            return [
                'ok' => false,
                'status' =>
                    (string) (
                        $delivered['status']
                        ?? 'delivery_failed'
                    ),
            ];
        }

        return [
            'ok' => true,
            'status' =>
                $resend
                    ? 'resend_sent'
                    : (string) (
                        $delivered['status']
                        ?? 'sent'
                    ),
            'dev_token' =>
                $delivered[
                    'dev_token'
                ]
                ?? null,
        ];
    }


    /*
     * PUBLIC_REGISTRATION_BALE_MOBILE_ATTESTATION_A3_2B1_V2
     *
     * SMS OTP remains the primary verification mechanism.
     *
     * Bale may independently prove ownership of the SAME registered
     * mobile through the existing signed webhook + request_contact
     * flow.
     *
     * No email address may substitute for mobile ownership.
     *
     * A verified Bale binding alone is not sufficient. Registration
     * activation requires a verified self-service enrollment created
     * during this exact pending registration attempt.
     */
    public function baleEnrollment(
        int $attemptId,
        string $attemptToken
    ): array {
        $state =
            $this->state(
                $attemptId,
                $attemptToken
            );

        if (($state['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'status' => (string) (
                    $state['status']
                    ?? 'attempt_invalid'
                ),
            ];
        }

        $attempt =
            $this->pendingAttemptForBale(
                $attemptId
            );

        if (!is_array($attempt)) {
            return [
                'ok' => false,
                'status' => 'attempt_invalid',
            ];
        }

        $userId =
            (int) (
                $attempt['user_id']
                ?? 0
            );

        $mobile =
            trim(
                (string) (
                    $attempt['mobile_norm']
                    ?? ''
                )
            );

        $user =
            $this->user(
                $userId
            );

        if (
            $userId < 1
            || preg_match(
                '/^09[0-9]{9}$/D',
                $mobile
            ) !== 1
            || !is_array($user)
            || (string) (
                $user['status']
                ?? ''
            ) !== 'pending_verification'
            || (string) (
                $user['mobile_norm']
                ?? ''
            ) !== $mobile
            || !empty(
                $user['mobile_verified_at']
            )
        ) {
            return [
                'ok' => false,
                'status' => 'attempt_invalid',
            ];
        }

        try {
            $repository =
                new \App\Repositories\NotificationMessengerEnrollmentRepository();

            $providers =
                $repository
                    ->membershipAuthBaleProviders();

            if (count($providers) !== 1) {
                return [
                    'ok' => false,
                    'status' => 'bale_unavailable',
                ];
            }

            $provider =
                $providers[0];

            $providerId =
                (int) (
                    $provider['id']
                    ?? 0
                );

            if ($providerId < 1) {
                return [
                    'ok' => false,
                    'status' => 'bale_unavailable',
                ];
            }

            $runtime =
                new NotificationProviderRuntimeService();

            $configuration =
                $runtime->configuration(
                    $provider
                );

            $template =
                trim(
                    (string) (
                        $configuration[
                            'enrollment_link_template'
                        ] ?? ''
                    )
                );

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

            if (
                $template === ''
                && $username !== ''
            ) {
                $template =
                    'https://ble.ir/'
                    . rawurlencode(
                        $username
                    )
                    . '?start={token}';
            }

            if (
                $template === ''
                || !str_contains(
                    $template,
                    '{token}'
                )
            ) {
                return [
                    'ok' => false,
                    'status' => 'bale_unavailable',
                ];
            }

            $token =
                bin2hex(
                    random_bytes(24)
                );

            $link =
                str_replace(
                    '{token}',
                    rawurlencode($token),
                    $template
                );

            if (
                !$this->safeBaleEnrollmentLink(
                    $link
                )
            ) {
                return [
                    'ok' => false,
                    'status' => 'bale_unavailable',
                ];
            }

            $attemptExpiresAt =
                strtotime(
                    (string) (
                        $attempt['expires_at']
                        ?? ''
                    )
                );

            if (
                $attemptExpiresAt === false
                || $attemptExpiresAt <= time()
            ) {
                return [
                    'ok' => false,
                    'status' => 'attempt_expired',
                ];
            }

            /*
             * Enrollment may never outlive the registration attempt.
             * Each generated Bale link is capped at 15 minutes.
             */
            $enrollmentExpiresAt =
                min(
                    $attemptExpiresAt,
                    time() + 900
                );

            if (
                $enrollmentExpiresAt <= time()
            ) {
                return [
                    'ok' => false,
                    'status' => 'attempt_expired',
                ];
            }

            $enrollment =
                $repository
                    ->createEnrollment(
                        $userId,
                        $providerId,
                        $mobile,
                        hash(
                            'sha256',
                            $token
                        ),
                        /*
                         * invited_by_user_id is non-null in the
                         * existing schema.
                         *
                         * This is a self-service registration
                         * enrollment, therefore the pending user is
                         * explicitly recorded as initiator.
                         */
                        $userId,
                        date(
                            'Y-m-d H:i:s',
                            $enrollmentExpiresAt
                        )
                    );

            return [
                'ok' => true,
                'status' =>
                    'bale_enrollment_ready',
                'link' => $link,
                'provider_instance_id' =>
                    $providerId,
                'enrollment_reference' =>
                    (string) (
                        $enrollment[
                            'public_reference'
                        ] ?? ''
                    ),
            ];

        } catch (Throwable) {
            /*
             * Public endpoints must not expose provider,
             * configuration or transport details.
             */
            return [
                'ok' => false,
                'status' => 'bale_unavailable',
            ];
        }
    }

    public function confirmBaleAttestation(
        int $attemptId,
        string $attemptToken
    ): array {
        $state =
            $this->state(
                $attemptId,
                $attemptToken
            );

        if (($state['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'status' => (string) (
                    $state['status']
                    ?? 'attempt_invalid'
                ),
            ];
        }

        $attempt =
            $this->pendingAttemptForBale(
                $attemptId
            );

        if (!is_array($attempt)) {
            return [
                'ok' => false,
                'status' => 'attempt_invalid',
            ];
        }

        $userId =
            (int) (
                $attempt['user_id']
                ?? 0
            );

        $mobile =
            trim(
                (string) (
                    $attempt['mobile_norm']
                    ?? ''
                )
            );

        $attemptCreatedAt =
            trim(
                (string) (
                    $attempt['created_at']
                    ?? ''
                )
            );

        $user =
            $this->user(
                $userId
            );

        if (
            $userId < 1
            || preg_match(
                '/^09[0-9]{9}$/D',
                $mobile
            ) !== 1
            || $attemptCreatedAt === ''
            || !is_array($user)
            || (string) (
                $user['status']
                ?? ''
            ) !== 'pending_verification'
            || (string) (
                $user['mobile_norm']
                ?? ''
            ) !== $mobile
            || !empty(
                $user['mobile_verified_at']
            )
        ) {
            return [
                'ok' => false,
                'status' => 'attempt_invalid',
            ];
        }

        try {
            $repository =
                new \App\Repositories\NotificationMessengerEnrollmentRepository();

            $providers =
                $repository
                    ->membershipAuthBaleProviders();

            if (count($providers) !== 1) {
                return [
                    'ok' => false,
                    'status' => 'bale_unavailable',
                ];
            }

            $providerId =
                (int) (
                    $providers[0]['id']
                    ?? 0
                );

            if ($providerId < 1) {
                return [
                    'ok' => false,
                    'status' => 'bale_unavailable',
                ];
            }

            /*
             * SECURITY:
             *
             * A pre-existing Bale binding is insufficient.
             *
             * There must be a verified enrollment:
             *   - for this pending user,
             *   - for this membership/auth provider,
             *   - for this exact mobile,
             *   - initiated by this pending user,
             *   - created no earlier than this registration attempt,
             *   - completed by the existing signed Bale webhook.
             *
             * The active binding produced/updated by that webhook
             * must also exist for the same user/provider/mobile.
             */
            $proof =
                $this->db->prepare("
                    SELECT
                        enrollments.id
                            AS enrollment_id,
                        bindings.id
                            AS binding_id
                    FROM
                        notification_messenger_enrollments
                            AS enrollments
                    INNER JOIN
                        notification_messenger_bindings
                            AS bindings
                      ON bindings.user_id =
                            enrollments.user_id
                     AND bindings.provider_instance_id =
                            enrollments.provider_instance_id
                     AND bindings.mobile_norm =
                            enrollments.mobile_norm
                    WHERE enrollments.user_id = ?
                      AND enrollments.provider_instance_id = ?
                      AND enrollments.mobile_norm = ?
                      AND enrollments.invited_by_user_id = ?
                      AND enrollments.status_code =
                            'verified'
                      AND enrollments.verified_at
                            IS NOT NULL
                      AND enrollments.used_at
                            IS NOT NULL
                      AND enrollments.created_at >= ?
                      AND bindings.status_code =
                            'active'
                      AND bindings.verified_at
                            IS NOT NULL
                      AND bindings.revoked_at
                            IS NULL
                    ORDER BY
                        enrollments.verified_at DESC,
                        enrollments.id DESC
                    LIMIT 1
                ");

            $proof->execute([
                $userId,
                $providerId,
                $mobile,
                $userId,
                $attemptCreatedAt,
            ]);

            $proofRow =
                $proof->fetch(
                    PDO::FETCH_ASSOC
                );

            if (
                !is_array($proofRow)
                || (int) (
                    $proofRow[
                        'enrollment_id'
                    ] ?? 0
                ) < 1
                || (int) (
                    $proofRow[
                        'binding_id'
                    ] ?? 0
                ) < 1
            ) {
                return [
                    'ok' => false,
                    'status' => 'bale_pending',
                ];
            }

            /*
             * Reuse the current SMS challenge when one exists.
             *
             * Bale can still verify a registration for which SMS
             * delivery failed before challenge creation; in that case
             * challengeId remains zero and closeSuccessfulAttempt()
             * skips challenge consumption.
             */
            $challenge =
                $this->db->prepare("
                    SELECT id
                    FROM mfa_delivery_challenges
                    WHERE user_id = ?
                      AND purpose = ?
                      AND consumed_at IS NULL
                      AND (
                            expires_at IS NULL
                            OR expires_at >
                                CURRENT_TIMESTAMP
                      )
                    ORDER BY id DESC
                    LIMIT 1
                ");

            $challenge->execute([
                $userId,
                'public_registration:'
                    . (int) $attempt['id'],
            ]);

            $challengeId =
                (int) (
                    $challenge
                        ->fetchColumn()
                    ?: 0
                );

            if (
                !$this->activate(
                    $attempt,
                    $challengeId
                )
            ) {
                return [
                    'ok' => false,
                    'status' =>
                        'activation_failed',
                ];
            }

            return [
                'ok' => true,
                'status' => 'bale_verified',
                'enrollment_id' =>
                    (int) $proofRow[
                        'enrollment_id'
                    ],
                'binding_id' =>
                    (int) $proofRow[
                        'binding_id'
                    ],
            ];

        } catch (Throwable) {
            return [
                'ok' => false,
                'status' => 'bale_unavailable',
            ];
        }
    }

    private function pendingAttemptForBale(
        int $attemptId
    ): ?array {
        if ($attemptId < 1) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT *
                FROM public_registration_attempts
                WHERE id = ?
                  AND status = 'pending'
                  AND expires_at >
                        CURRENT_TIMESTAMP
                LIMIT 1
            ");

        $statement->execute([
            $attemptId,
        ]);

        $attempt =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($attempt)
            ? $attempt
            : null;
    }

    private function safeBaleEnrollmentLink(
        string $link
    ): bool {
        $parts =
            parse_url(
                trim($link)
            );

        if (!is_array($parts)) {
            return false;
        }

        $scheme =
            strtolower(
                (string) (
                    $parts['scheme']
                    ?? ''
                )
            );

        $host =
            strtolower(
                (string) (
                    $parts['host']
                    ?? ''
                )
            );

        return
            $scheme === 'https'
            && in_array(
                $host,
                [
                    'ble.ir',
                    'bale.ai',
                ],
                true
            )
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private function activate(
        array $attempt,
        int $challengeId
    ): bool {
        if (!$this->acquireLock()) {
            return false;
        }

        try {
            $userId =
                (int) $attempt[
                    'user_id'
                ];

            $user =
                $this->user(
                    $userId
                );

            if (!is_array($user)) {
                return false;
            }

            /*
             * Idempotent recovery:
             * activation may have succeeded while the
             * final attempt/challenge close write failed.
             */
            if (
                (string) (
                    $user['status']
                    ?? ''
                ) === 'active'
                && !empty(
                    $user[
                        'mobile_verified_at'
                    ]
                )
                && (string) (
                    $user['mobile_norm']
                    ?? ''
                ) === (string) $attempt[
                    'mobile_norm'
                ]
            ) {
                if (
                    !$this->ensureExactlyBaseRole(
                        $userId
                    )
                ) {
                    return false;
                }

                $this->closeSuccessfulAttempt(
                    $attempt,
                    $challengeId
                );

                return true;
            }

            if (
                (string) (
                    $user['status']
                    ?? ''
                )
                !== 'pending_verification'
            ) {
                return false;
            }

            if (
                $this->activeMobileExists(
                    (string) $attempt[
                        'mobile_norm'
                    ],
                    $userId
                )
            ) {
                return false;
            }

            $email =
                trim(
                    (string) (
                        $attempt['email_norm']
                        ?? ''
                    )
                );

            if (
                $email !== ''
                && $this->activeEmailExists(
                    $email,
                    $userId
                )
            ) {
                return false;
            }

            if (
                !$this->ensureExactlyBaseRole(
                    $userId
                )
            ) {
                return false;
            }

            /*
             * Person and role writes occur while the user
             * is still non-authenticatable.
             */
            $statement =
                $this->db->prepare("
                    UPDATE persons
                    SET full_name = ?,
                        mobile = ?,
                        mobile_norm = ?,
                        email = ?,
                        email_norm = ?,
                        status = 'active',
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE id = ?
                ");

            $statement->execute([
                (string) $attempt[
                    'full_name'
                ],
                (string) $attempt[
                    'mobile'
                ],
                (string) $attempt[
                    'mobile_norm'
                ],
                $email !== ''
                    ? (string) $attempt[
                        'email'
                    ]
                    : null,
                $email !== ''
                    ? $email
                    : null,
                (int) $user[
                    'person_id'
                ],
            ]);

            /*
             * This is the final authentication-eligibility
             * step. All required data/role writes are done
             * before status becomes active.
             */
            $statement =
                $this->db->prepare("
                    UPDATE users
                    SET email = ?,
                        email_norm = ?,
                        mobile = ?,
                        mobile_norm = ?,
                        password_hash = ?,
                        status = 'active',
                        mobile_verified_at =
                            CURRENT_TIMESTAMP,
                        email_verified_at = NULL,
                        last_password_change_at =
                            CURRENT_TIMESTAMP,
                        force_password_change = 0,
                        failed_login_attempts = 0,
                        locked_until = NULL,
                        updated_at =
                            CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND status =
                          'pending_verification'
                      AND deleted_at IS NULL
                ");

            $statement->execute([
                $email !== ''
                    ? (string) $attempt[
                        'email'
                    ]
                    : null,
                $email !== ''
                    ? $email
                    : null,
                (string) $attempt[
                    'mobile'
                ],
                (string) $attempt[
                    'mobile_norm'
                ],
                (string) $attempt[
                    'password_hash'
                ],
                $userId,
            ]);

            if (
                $statement->rowCount()
                !== 1
            ) {
                return false;
            }

            if (!$this->activationProof(
                $userId,
                (string) $attempt[
                    'mobile_norm'
                ]
            )) {
                return false;
            }

            $this->closeSuccessfulAttempt(
                $attempt,
                $challengeId
            );

            return true;

        } catch (Throwable) {
            /*
             * MyISAM identity writes cannot be rolled
             * back. The user status is intentionally
             * promoted only in the last eligibility step.
             */
            return false;

        } finally {
            $this->releaseLock();
        }
    }

    private function ensureExactlyBaseRole(
        int $userId
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT id
                FROM roles
                WHERE code = 'user'
                  AND is_active = 1
                LIMIT 1
            ");

        $statement->execute();

        $roleId =
            (int) $statement
                ->fetchColumn();

        if ($roleId < 1) {
            return false;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    assignments.id,
                    roles.code
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND assignments.is_active = 1
                ORDER BY assignments.id
            ");

        $statement->execute([
            $userId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        if ($rows === []) {
            $statement =
                $this->db->prepare("
                    INSERT INTO
                        user_role_assignments (
                            user_id,
                            role_id,
                            scope_type,
                            scope_id,
                            include_children,
                            is_active,
                            is_default,
                            assigned_by,
                            created_at,
                            updated_at
                        )
                    VALUES (
                        ?,
                        ?,
                        'global',
                        NULL,
                        0,
                        1,
                        1,
                        NULL,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

            $statement->execute([
                $userId,
                $roleId,
            ]);

            $rows = [
                [
                    'code' => 'user',
                ],
            ];
        }

        return
            count($rows) === 1
            && (string) (
                $rows[0]['code']
                ?? ''
            ) === 'user';
    }

    private function activationProof(
        int $userId,
        string $mobile
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT
                    status,
                    mobile_norm,
                    mobile_verified_at
                FROM users
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $user =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        if (
            !is_array($user)
            || (string) $user['status']
                !== 'active'
            || (string) (
                $user['mobile_norm']
                ?? ''
            ) !== $mobile
            || empty(
                $user[
                    'mobile_verified_at'
                ]
            )
        ) {
            return false;
        }

        $statement =
            $this->db->prepare("
                SELECT
                    roles.code
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND assignments.is_active = 1
                ORDER BY assignments.id
            ");

        $statement->execute([
            $userId,
        ]);

        $roles =
            $statement->fetchAll(
                PDO::FETCH_COLUMN
            );

        return
            count($roles) === 1
            && (string) $roles[0]
                === 'user';
    }

    private function closeSuccessfulAttempt(
        array $attempt,
        int $challengeId
    ): void {
        $attemptId =
            (int) $attempt['id'];

        $userId =
            (int) $attempt[
                'user_id'
            ];

        $statement =
            $this->db->prepare("
                UPDATE public_registration_attempts
                SET status = 'consumed',
                    password_hash = '',
                    consumed_at =
                        CURRENT_TIMESTAMP,
                    updated_at =
                        CURRENT_TIMESTAMP
                WHERE id = ?
                  AND status = 'pending'
            ");

        $statement->execute([
            $attemptId,
        ]);

        $statement =
            $this->db->prepare("
                UPDATE public_registration_attempts
                SET status = 'superseded',
                    password_hash = '',
                    updated_at =
                        CURRENT_TIMESTAMP
                WHERE user_id = ?
                  AND id <> ?
                  AND status = 'pending'
            ");

        $statement->execute([
            $userId,
            $attemptId,
        ]);

        if ($challengeId > 0) {
            $this->verification
                ->consume(
                    $challengeId
                );
        }
    }

    private function activeMobileExists(
        string $mobile,
        int $excludeUserId
    ): bool {
        return
            $this->users
                ->identityValueExists(
                    'mobile',
                    $mobile,
                    $excludeUserId
                );
    }

    private function activeEmailExists(
        string $email,
        int $excludeUserId
    ): bool {
        return
            $this->users
                ->identityValueExists(
                    'email',
                    $email,
                    $excludeUserId
                );
    }

    private function user(
        int $userId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    person_id,
                    status,
                    mobile,
                    mobile_norm,
                    mobile_verified_at
                FROM users
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");

        $statement->execute([
            $userId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    private function incrementAttemptFailure(
        int $attemptId
    ): void {
        $statement =
            $this->db->prepare("
                UPDATE public_registration_attempts
                SET verification_attempts =
                        verification_attempts + 1,
                    updated_at =
                        CURRENT_TIMESTAMP
                WHERE id = ?
                  AND status = 'pending'
            ");

        $statement->execute([
            $attemptId,
        ]);
    }

    private function resendWaitSeconds(
        array $attempt
    ): int {
        $latest =
            $this->verification
                ->latestChallengeRecord(
                    (int) $attempt[
                        'user_id'
                    ],
                    'sms',
                    $this->purpose(
                        (int) $attempt['id']
                    )
                );

        if (!is_array($latest)) {
            return 0;
        }

        $created =
            strtotime(
                (string) (
                    $latest['created_at']
                    ?? ''
                )
            );

        if ($created === false) {
            return 0;
        }

        return max(
            0,
            self::RESEND_COOLDOWN_SECONDS
            - (
                time()
                - $created
            )
        );
    }

    private function attemptForToken(
        int $attemptId,
        string $token
    ): ?array {
        if (
            $attemptId < 1
            || preg_match(
                '/^[a-f0-9]{64}$/D',
                $token
            ) !== 1
        ) {
            return null;
        }

        $statement =
            $this->db->prepare("
                SELECT *
                FROM public_registration_attempts
                WHERE id = ?
                  AND nonce_hash = ?
                LIMIT 1
            ");

        $statement->execute([
            $attemptId,
            hash(
                'sha256',
                $token
            ),
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    private function attemptById(
        int $attemptId
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT *
                FROM public_registration_attempts
                WHERE id = ?
                LIMIT 1
            ");

        $statement->execute([
            $attemptId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    private function expired(
        array $attempt
    ): bool {
        $expires =
            strtotime(
                (string) (
                    $attempt['expires_at']
                    ?? ''
                )
            );

        return
            $expires === false
            || $expires < time();
    }

    private function purpose(
        int $attemptId
    ): string {
        return
            self::PURPOSE_PREFIX
            . $attemptId;
    }

    private function normalizeCode(
        string $code
    ): string {
        $code =
            strtr(
                trim($code),
                [
                    '۰' => '0',
                    '۱' => '1',
                    '۲' => '2',
                    '۳' => '3',
                    '۴' => '4',
                    '۵' => '5',
                    '۶' => '6',
                    '۷' => '7',
                    '۸' => '8',
                    '۹' => '9',
                    '٠' => '0',
                    '١' => '1',
                    '٢' => '2',
                    '٣' => '3',
                    '٤' => '4',
                    '٥' => '5',
                    '٦' => '6',
                    '٧' => '7',
                    '٨' => '8',
                    '٩' => '9',
                ]
            );

        return
            preg_replace(
                '/\D+/',
                '',
                $code
            )
            ?: '';
    }

    private function maskMobile(
        string $mobile
    ): string {
        $mobile =
            trim($mobile);

        if (strlen($mobile) < 8) {
            return $mobile;
        }

        return
            substr($mobile, 0, 4)
            . '***'
            . substr($mobile, -4);
    }

    private function acquireLock(): bool
    {
        $statement =
            $this->db->prepare(
                'SELECT GET_LOCK(?, 5)'
            );

        $statement->execute([
            self::REGISTRATION_LOCK,
        ]);

        return
            (int) $statement
                ->fetchColumn()
            === 1;
    }

    private function releaseLock(): void
    {
        try {
            $statement =
                $this->db->prepare(
                    'SELECT RELEASE_LOCK(?)'
                );

            $statement->execute([
                self::REGISTRATION_LOCK,
            ]);
        } catch (Throwable) {
        }
    }

    private function error(
        string $status
    ): array {
        return [
            'ok' => false,
            'status' => $status,
        ];
    }
}
