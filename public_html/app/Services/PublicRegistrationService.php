<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

final class PublicRegistrationService extends BaseService
{
    private const LOCK_NAME =
        'troca.public_registration';

    private PDO $db;

    private PublicRegistrationOtpService $otp;

    private UserRepository $users;

    public function __construct(
        ?PDO $db = null,
        ?PublicRegistrationOtpService $otp = null,
        ?UserRepository $users = null
    ) {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve('core.primary');

        $this->otp =
            $otp
            ?? new PublicRegistrationOtpService(
                $this->db
            );

        $this->users =
            $users
            ?? new UserRepository();
    }

    public function register(
        array $input
    ): array {
        $normalizer =
            new IdentityNormalizer();

        $fullName =
            $this->normalizeName(
                (string) (
                    $input['full_name']
                    ?? ''
                )
            );

        $mobileInput =
            trim(
                (string) (
                    $input['mobile']
                    ?? ''
                )
            );

        $emailInput =
            trim(
                (string) (
                    $input['email']
                    ?? ''
                )
            );

        $password =
            (string) (
                $input['password']
                ?? ''
            );

        $passwordConfirmation =
            (string) (
                $input[
                    'password_confirmation'
                ]
                ?? ''
            );

        $ip =
            trim(
                (string) (
                    $input['created_ip']
                    ?? ''
                )
            );

        $userAgent =
            trim(
                (string) (
                    $input[
                        'created_user_agent'
                    ]
                    ?? ''
                )
            );

        $mobile =
            $normalizer->mobile(
                $mobileInput
            );

        $email =
            $emailInput === ''
                ? null
                : $normalizer->email(
                    $emailInput
                );

        $errors = [];

        $nameLength =
            function_exists(
                'mb_strlen'
            )
                ? mb_strlen(
                    $fullName,
                    'UTF-8'
                )
                : strlen($fullName);

        if (
            $nameLength < 3
            || $nameLength > 150
        ) {
            $errors['full_name'] =
                'نام و نام خانوادگی معتبر را وارد کنید.';
        }

        if ($mobile === null) {
            $errors['mobile'] =
                'شماره موبایل معتبر وارد کنید.';
        }

        if (
            $emailInput !== ''
            && $email === null
        ) {
            $errors['email'] =
                'نشانی ایمیل معتبر وارد کنید.';
        }

        $passwordLength =
            function_exists(
                'mb_strlen'
            )
                ? mb_strlen(
                    $password,
                    'UTF-8'
                )
                : strlen($password);

        if (
            $passwordLength < 8
            || $passwordLength > 128
        ) {
            $errors['password'] =
                'کلمه عبور باید حداقل ۸ نویسه و حداکثر ۱۲۸ نویسه باشد.';
        } elseif (
            preg_match(
                '/\p{L}/u',
                $password
            ) !== 1
            || preg_match(
                '/\p{N}/u',
                $password
            ) !== 1
        ) {
            $errors['password'] =
                'کلمه عبور باید حداقل یک حرف و یک عدد داشته باشد.';
        }

        if (
            $password
            !== $passwordConfirmation
        ) {
            $errors[
                'password_confirmation'
            ] =
                'تکرار کلمه عبور یکسان نیست.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
            ];
        }

        if (
            !$this->otp
                ->canStartFromIp(
                    $ip
                )
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
                ],
            ];
        }

        $passwordHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );

        if (
            !is_string($passwordHash)
            || $passwordHash === ''
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'ثبت‌نام تکمیل نشد. لطفاً دوباره تلاش کنید.',
                ],
            ];
        }

        if (!$this->acquireLock()) {
            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'سامانه در حال پردازش درخواست دیگری است. چند لحظه بعد دوباره تلاش کنید.',
                ],
            ];
        }

        $personId = null;
        $userId = null;
        $newIdentity = false;

        try {
            if (
                $this->activeMobileExists(
                    (string) $mobile
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'mobile' =>
                            'این شماره موبایل قبلاً ثبت شده است.',
                    ],
                ];
            }

            if (
                $email !== null
                && $this->activeEmailExists(
                    $email
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'email' =>
                            'این ایمیل قبلاً ثبت شده است.',
                    ],
                ];
            }

            $pending =
                $this->pendingUserByMobile(
                    (string) $mobile
                );

            if (is_array($pending)) {
                $userId =
                    (int) $pending['id'];

                $personId =
                    (int) $pending[
                        'person_id'
                    ];
            } else {
                $newIdentity = true;

                $statement =
                    $this->db->prepare("
                        INSERT INTO persons (
                            person_type,
                            full_name,
                            mobile,
                            email,
                            mobile_norm,
                            email_norm,
                            status,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            'individual',
                            ?,
                            ?,
                            NULL,
                            ?,
                            NULL,
                            'active',
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $statement->execute([
                    $fullName,
                    $mobile,
                    $mobile,
                ]);

                $personId =
                    (int) $this->db
                        ->lastInsertId();

                if ($personId < 1) {
                    throw new \RuntimeException(
                        'person_insert_failed'
                    );
                }

                /*
                 * Submitted password is NOT stored on the
                 * pending user. It lives only in the
                 * token-bound registration attempt.
                 */
                $placeholder =
                    password_hash(
                        bin2hex(
                            random_bytes(32)
                        ),
                        PASSWORD_DEFAULT
                    );

                if (
                    !is_string($placeholder)
                    || $placeholder === ''
                ) {
                    throw new \RuntimeException(
                        'placeholder_hash_failed'
                    );
                }

                $statement =
                    $this->db->prepare("
                        INSERT INTO users (
                            person_id,
                            username,
                            username_norm,
                            email,
                            email_norm,
                            mobile,
                            mobile_norm,
                            password_hash,
                            status,
                            force_password_change,
                            failed_login_attempts,
                            created_at,
                            updated_at
                        )
                        VALUES (
                            ?,
                            NULL,
                            NULL,
                            NULL,
                            NULL,
                            ?,
                            ?,
                            ?,
                            'pending_verification',
                            0,
                            0,
                            CURRENT_TIMESTAMP,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $statement->execute([
                    $personId,
                    $mobile,
                    $mobile,
                    $placeholder,
                ]);

                $userId =
                    (int) $this->db
                        ->lastInsertId();

                if ($userId < 1) {
                    throw new \RuntimeException(
                        'user_insert_failed'
                    );
                }
            }

        } catch (Throwable) {
            if ($newIdentity) {
                $this->compensate(
                    $userId,
                    $personId
                );
            }

            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'ثبت‌نام تکمیل نشد. لطفاً دوباره تلاش کنید.',
                ],
            ];

        } finally {
            $this->releaseLock();
        }

        $attempt =
            $this->otp
                ->startAttempt([
                    'user_id' =>
                        $userId,
                    'full_name' =>
                        $fullName,
                    'mobile' =>
                        $mobile,
                    'email' =>
                        $email,
                    'password_hash' =>
                        $passwordHash,
                    'created_ip' =>
                        $ip,
                    'created_user_agent' =>
                        $userAgent,
                ]);

        if (
            ($attempt['ok'] ?? false)
            !== true
        ) {
            $status =
                (string) (
                    $attempt['status']
                    ?? ''
                );

            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        $status === 'rate_limited'
                            ? 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.'
                            : 'امکان شروع تأیید شماره همراه فراهم نشد. لطفاً دوباره تلاش کنید.',
                ],
            ];
        }

        return [
            'ok' => true,
            'errors' => [],
            'user_id' =>
                $userId,
            'attempt_id' =>
                (int) $attempt[
                    'attempt_id'
                ],
            'attempt_token' =>
                (string) $attempt[
                    'attempt_token'
                ],
            'masked_mobile' =>
                (string) (
                    $attempt[
                        'masked_mobile'
                    ]
                    ?? ''
                ),
            'delivery_status' =>
                (string) (
                    $attempt['status']
                    ?? ''
                ),
            'delivery_ok' =>
                (bool) (
                    $attempt[
                        'delivery_ok'
                    ]
                    ?? false
                ),
            'dev_token' =>
                $attempt['dev_token']
                ?? null,
        ];
    }

    private function normalizeName(
        string $value
    ): string {
        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                trim($value)
            );

        return is_string($value)
            ? $value
            : '';
    }

    private function pendingUserByMobile(
        string $mobile
    ): ?array {
        $statement =
            $this->db->prepare("
                SELECT
                    id,
                    person_id
                FROM users
                WHERE deleted_at IS NULL
                  AND status =
                      'pending_verification'
                  AND mobile_norm = ?
                ORDER BY id
                LIMIT 1
            ");

        $statement->execute([
            $mobile,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return is_array($row)
            ? $row
            : null;
    }

    private function activeMobileExists(
        string $mobile
    ): bool {
        return
            $this->users
                ->identityValueExists(
                    'mobile',
                    $mobile,
                    0
                );
    }

    private function activeEmailExists(
        string $email
    ): bool {
        return
            $this->users
                ->verifiedEmailExists(
                    $email,
                    0
                );
    }

    private function acquireLock(): bool
    {
        $statement =
            $this->db->prepare(
                'SELECT GET_LOCK(?, 5)'
            );

        $statement->execute([
            self::LOCK_NAME,
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
                self::LOCK_NAME,
            ]);
        } catch (Throwable) {
        }
    }

    private function compensate(
        ?int $userId,
        ?int $personId
    ): void {
        try {
            if (
                $userId !== null
                && $userId > 0
            ) {
                $statement =
                    $this->db->prepare("
                        DELETE FROM users
                        WHERE id = ?
                          AND status =
                              'pending_verification'
                    ");

                $statement->execute([
                    $userId,
                ]);
            }

            if (
                $personId !== null
                && $personId > 0
            ) {
                $statement =
                    $this->db->prepare("
                        DELETE FROM persons
                        WHERE id = ?
                    ");

                $statement->execute([
                    $personId,
                ]);
            }
        } catch (Throwable) {
        }
    }
}
