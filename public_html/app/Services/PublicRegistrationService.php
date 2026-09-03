<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Database\Connections\ConnectionResolver;
use PDO;
use Throwable;

final class PublicRegistrationService extends BaseService
{
    private const LOCK_NAME =
        'troca.public_registration';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db =
            $db
            ?? (
                new ConnectionResolver()
            )->resolve('core.primary');
    }

    public function register(array $input): array
    {
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
            function_exists('mb_strlen')
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
            function_exists('mb_strlen')
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
                'user_id' => null,
            ];
        }

        $locked =
            $this->acquireLock();

        if (!$locked) {
            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'سامانه در حال پردازش درخواست دیگری است. چند لحظه بعد دوباره تلاش کنید.',
                ],
                'user_id' => null,
            ];
        }

        $personId = null;
        $userId = null;

        try {
            if (
                $this->mobileExists(
                    (string) $mobile
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'mobile' =>
                            'این شماره موبایل قبلاً ثبت شده است.',
                    ],
                    'user_id' => null,
                ];
            }

            if (
                $email !== null
                && $this->emailExists(
                    $email
                )
            ) {
                return [
                    'ok' => false,
                    'errors' => [
                        'email' =>
                            'این ایمیل قبلاً ثبت شده است.',
                    ],
                    'user_id' => null,
                ];
            }

            $roleId =
                $this->baseRoleId();

            if ($roleId === null) {
                return [
                    'ok' => false,
                    'errors' => [
                        'general' =>
                            'نقش پایه کاربر در سامانه فعال نیست.',
                    ],
                    'user_id' => null,
                ];
            }

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
                        ?,
                        ?,
                        ?,
                        'active',
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

            $statement->execute([
                $fullName,
                $mobile,
                $email,
                $mobile,
                $email,
            ]);

            $personId =
                (int) $this->db
                    ->lastInsertId();

            if ($personId < 1) {
                throw new \RuntimeException(
                    'person_insert_failed'
                );
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
                throw new \RuntimeException(
                    'password_hash_failed'
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
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'active',
                        0,
                        0,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

            $statement->execute([
                $personId,
                $email,
                $email,
                $mobile,
                $mobile,
                $passwordHash,
            ]);

            $userId =
                (int) $this->db
                    ->lastInsertId();

            if ($userId < 1) {
                throw new \RuntimeException(
                    'user_insert_failed'
                );
            }

            /*
             * Public registration receives exactly
             * one role: the global base user role.
             */
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

            $proof =
                $this->registrationProof(
                    $userId
                );

            if (
                ($proof['role_code']
                    ?? null) !== 'user'
                || (int) (
                    $proof['is_default']
                    ?? 0
                ) !== 1
            ) {
                throw new \RuntimeException(
                    'base_role_proof_failed'
                );
            }

            return [
                'ok' => true,
                'errors' => [],
                'user_id' => $userId,
            ];

        } catch (Throwable $exception) {
            /*
             * Core identity tables are currently MyISAM.
             * Therefore rollback is compensating, not
             * transactional.
             */
            $this->compensate(
                $userId,
                $personId
            );

            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'ثبت‌نام تکمیل نشد. لطفاً دوباره تلاش کنید.',
                ],
                'user_id' => null,
            ];

        } finally {
            $this->releaseLock();
        }
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
                ->fetchColumn() === 1;
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
            // Lock is connection scoped.
        }
    }

    private function mobileExists(
        string $mobile
    ): bool {
        $variants = [
            $mobile,
            substr($mobile, 1),
            '98' . substr(
                $mobile,
                1
            ),
            '+98' . substr(
                $mobile,
                1
            ),
        ];

        $statement =
            $this->db->prepare("
                SELECT COUNT(
                    DISTINCT users.id
                )
                FROM users
                LEFT JOIN persons
                    ON persons.id =
                        users.person_id
                WHERE users.deleted_at
                    IS NULL
                  AND (
                    users.mobile_norm = ?
                    OR persons.mobile_norm = ?
                    OR users.mobile
                        IN (?, ?, ?, ?)
                    OR persons.mobile
                        IN (?, ?, ?, ?)
                  )
            ");

        $statement->execute([
            $mobile,
            $mobile,
            ...$variants,
            ...$variants,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function emailExists(
        string $email
    ): bool {
        $statement =
            $this->db->prepare("
                SELECT COUNT(
                    DISTINCT users.id
                )
                FROM users
                LEFT JOIN persons
                    ON persons.id =
                        users.person_id
                WHERE users.deleted_at
                    IS NULL
                  AND (
                    users.email_norm = ?
                    OR persons.email_norm = ?
                    OR LOWER(users.email) = ?
                    OR LOWER(persons.email) = ?
                  )
            ");

        $statement->execute([
            $email,
            $email,
            $email,
            $email,
        ]);

        return
            (int) $statement
                ->fetchColumn() > 0;
    }

    private function baseRoleId(): ?int
    {
        $statement =
            $this->db->prepare("
                SELECT id
                FROM roles
                WHERE code = ?
                  AND is_active = 1
                LIMIT 1
            ");

        $statement->execute([
            'user',
        ]);

        $id =
            $statement->fetchColumn();

        return $id === false
            ? null
            : (int) $id;
    }

    private function registrationProof(
        int $userId
    ): array {
        $statement =
            $this->db->prepare("
                SELECT
                    roles.code AS role_code,
                    assignments.is_default
                FROM user_role_assignments
                    AS assignments
                INNER JOIN roles
                    ON roles.id =
                        assignments.role_id
                WHERE assignments.user_id = ?
                  AND assignments.is_active = 1
                ORDER BY assignments.id
                LIMIT 2
            ");

        $statement->execute([
            $userId,
        ]);

        $rows =
            $statement->fetchAll(
                PDO::FETCH_ASSOC
            );

        if (count($rows) !== 1) {
            return [];
        }

        return $rows[0];
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
                        DELETE FROM
                            user_role_assignments
                        WHERE user_id = ?
                    ");

                $statement->execute([
                    $userId,
                ]);

                $statement =
                    $this->db->prepare("
                        DELETE FROM users
                        WHERE id = ?
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
            /*
             * No secondary exception should mask
             * the registration failure.
             */
        }
    }
}
