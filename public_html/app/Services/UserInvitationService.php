<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserInvitationRepository;
use IPKF\Support\ApplicationUrlRegistry;
use Throwable;

final class UserInvitationService extends BaseService
{
    public function __construct(
        private ?UserInvitationRepository $invitations = null,
        private ?IdentityNormalizer $normalizer = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->invitations ??=
            new UserInvitationRepository();

        $this->normalizer ??=
            new IdentityNormalizer();

        $this->authorization ??=
            new AuthorizationService();
    }

    public function canInvite(
        int $actorUserId
    ): bool {
        return
            $this->authorization
                ->hasPermission(
                    $actorUserId,
                    'users.create'
                )
            || $this->authorization
                ->hasPermission(
                    $actorUserId,
                    'users.manage'
                );
    }

    public function create(
        int $actorUserId,
        array $input
    ): array {
        if (!$this->canInvite(
            $actorUserId
        )) {
            return [
                'ok' => false,
                'forbidden' => true,
                'errors' => [],
            ];
        }

        $fullName =
            preg_replace(
                '/\s+/u',
                ' ',
                trim(
                    (string) (
                        $input['full_name']
                        ?? ''
                    )
                )
            );

        $fullName =
            is_string($fullName)
                ? $fullName
                : '';

        $mobileRaw =
            trim(
                (string) (
                    $input['mobile']
                    ?? ''
                )
            );

        $emailRaw =
            trim(
                (string) (
                    $input['email']
                    ?? ''
                )
            );

        $mobile =
            $this->normalizer
                ->mobile(
                    $mobileRaw
                );

        $email =
            $emailRaw === ''
                ? null
                : $this->normalizer
                    ->email(
                        $emailRaw
                    );

        $expiresDays =
            max(
                1,
                min(
                    30,
                    (int) (
                        $input[
                            'expires_days'
                        ]
                        ?? 7
                    )
                )
            );

        $errors = [];

        if (
            $fullName !== ''
            && (
                mb_strlen(
                    $fullName,
                    'UTF-8'
                ) > 150
            )
        ) {
            $errors['full_name'] =
                'نام و نام خانوادگی بیش از حد مجاز است.';
        }

        if ($mobile === null) {
            $errors['mobile'] =
                'شماره موبایل معتبر الزامی است.';
        }

        if (
            $emailRaw !== ''
            && $email === null
        ) {
            $errors['email'] =
                'نشانی ایمیل معتبر نیست.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'form' => [
                    'full_name' => $fullName,
                    'mobile' => $mobileRaw,
                    'email' => $emailRaw,
                    'expires_days' =>
                        $expiresDays,
                ],
            ];
        }

        try {
            $token =
                bin2hex(
                    random_bytes(32)
                );

            $tokenHash =
                hash(
                    'sha256',
                    $token
                );

            $publicReference =
                bin2hex(
                    random_bytes(16)
                );

            $expiresAt =
                gmdate(
                    'Y-m-d H:i:s',
                    time()
                    + (
                        $expiresDays
                        * 86400
                    )
                );

            $stored =
                $this->invitations
                    ->create([
                        'public_reference' =>
                            $publicReference,
                        'token_hash' =>
                            $tokenHash,
                        'full_name' =>
                            $fullName !== ''
                                ? $fullName
                                : null,
                        'mobile' =>
                            $mobile,
                        'mobile_norm' =>
                            $mobile,
                        'email' =>
                            $email,
                        'email_norm' =>
                            $email,
                        'expires_at' =>
                            $expiresAt,
                        'created_by_user_id' =>
                            $actorUserId,
                        'created_ip' =>
                            trim(
                                (string) (
                                    $input[
                                        'created_ip'
                                    ]
                                    ?? ''
                                )
                            )
                                ?: null,
                        'created_user_agent' =>
                            trim(
                                (string) (
                                    $input[
                                        'created_user_agent'
                                    ]
                                    ?? ''
                                )
                            )
                                ?: null,
                    ]);

            $relativeUrl =
                '/register?invite='
                . rawurlencode(
                    $token
                );

            $absoluteUrl =
                (
                    new ApplicationUrlRegistry()
                )->core(
                    $relativeUrl
                );

            return [
                'ok' => true,
                'errors' => [],
                'invitation' => [
                    'id' =>
                        (int) $stored['id'],
                    'public_reference' =>
                        (string) $stored[
                            'public_reference'
                        ],
                    'full_name' =>
                        $fullName,
                    'mobile' =>
                        $mobile,
                    'email' =>
                        $email ?? '',
                    'expires_at' =>
                        $expiresAt,
                    'url' =>
                        $absoluteUrl,
                ],
            ];

        } catch (Throwable) {
            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'ایجاد لینک دعوت انجام نشد.',
                ],
                'form' => [
                    'full_name' =>
                        $fullName,
                    'mobile' =>
                        $mobileRaw,
                    'email' =>
                        $emailRaw,
                    'expires_days' =>
                        $expiresDays,
                ],
            ];
        }
    }

    public function publicInvitation(
        string $token
    ): array {
        $token =
            strtolower(
                trim($token)
            );

        if (
            preg_match(
                '/^[a-f0-9]{64}$/D',
                $token
            ) !== 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'invitation_invalid',
            ];
        }

        try {
            $row =
                $this->invitations
                    ->pendingByTokenHash(
                        hash(
                            'sha256',
                            $token
                        )
                    );
        } catch (Throwable) {
            return [
                'ok' => false,
                'status' =>
                    'invitation_unavailable',
            ];
        }

        if (!is_array($row)) {
            return [
                'ok' => false,
                'status' =>
                    'invitation_invalid_or_expired',
            ];
        }

        return [
            'ok' => true,
            'status' => 'pending',
            'token' => $token,
            'invitation' => [
                'public_reference' =>
                    (string) (
                        $row[
                            'public_reference'
                        ]
                        ?? ''
                    ),
                'full_name' =>
                    (string) (
                        $row['full_name']
                        ?? ''
                    ),
                'mobile' =>
                    (string) (
                        $row['mobile']
                        ?? ''
                    ),
                'mobile_norm' =>
                    (string) (
                        $row[
                            'mobile_norm'
                        ]
                        ?? ''
                    ),
                'email' =>
                    (string) (
                        $row['email']
                        ?? ''
                    ),
                'email_norm' =>
                    (string) (
                        $row[
                            'email_norm'
                        ]
                        ?? ''
                    ),
                'expires_at' =>
                    (string) (
                        $row[
                            'expires_at'
                        ]
                        ?? ''
                    ),
            ],
        ];
    }

    public function validateSubmission(
        string $token,
        array $input
    ): array {
        $state =
            $this->publicInvitation(
                $token
            );

        if (($state['ok'] ?? false)
            !== true
        ) {
            return [
                'ok' => false,
                'errors' => [
                    'general' =>
                        'لینک دعوت معتبر نیست یا منقضی شده است.',
                ],
                'invitation' => null,
            ];
        }

        $invitation =
            $state['invitation'];

        $mobile =
            $this->normalizer
                ->mobile(
                    trim(
                        (string) (
                            $input['mobile']
                            ?? ''
                        )
                    )
                );

        $emailRaw =
            trim(
                (string) (
                    $input['email']
                    ?? ''
                )
            );

        $email =
            $emailRaw === ''
                ? null
                : $this->normalizer
                    ->email(
                        $emailRaw
                    );

        $errors = [];

        if (
            $mobile === null
            || $mobile
                !== (
                    $invitation[
                        'mobile_norm'
                    ]
                    ?? ''
                )
        ) {
            $errors['mobile'] =
                'شماره موبایل باید با دعوت ارسال‌شده یکسان باشد.';
        }

        $invitedEmail =
            trim(
                (string) (
                    $invitation[
                        'email_norm'
                    ]
                    ?? ''
                )
            );

        if (
            $invitedEmail !== ''
            && (
                $email === null
                || $email
                    !== $invitedEmail
            )
        ) {
            $errors['email'] =
                'ایمیل باید با دعوت ارسال‌شده یکسان باشد.';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'invitation' =>
                $invitation,
        ];
    }

    public function accept(
        string $token,
        int $userId
    ): bool {
        $state =
            $this->publicInvitation(
                $token
            );

        if (($state['ok'] ?? false)
            !== true
        ) {
            return false;
        }

        try {
            return
                $this->invitations
                    ->markAccepted(
                        hash(
                            'sha256',
                            strtolower(
                                trim($token)
                            )
                        ),
                        $userId
                    );
        } catch (Throwable) {
            return false;
        }
    }
}
