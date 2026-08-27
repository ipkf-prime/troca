<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\ParticipantDirectoryRepository;

final class ParticipantDirectoryService
{
    public function __construct(
        private ?ParticipantDirectoryRepository $repository = null
    ) {
        $this->repository ??=
            new ParticipantDirectoryRepository();
    }


    public function page(
        array $filters = []
    ): array {
        $items =
            $this->repository->index(
                $filters
            );

        $items =
            $this->enrichLinkedCoreProfiles(
                $items
            );

        $coreQ =
            trim(
                (string) (
                    $filters['core_q']
                    ?? ''
                )
            );

        $linked =
            array_fill_keys(
                $this->repository
                    ->linkedCoreReferences(),
                true
            );

        $coreCandidates = [];

        foreach (
            $this->repository
                ->activeCoreUsers(
                    $coreQ
                )
            as $user
        ) {
            $reference =
                'user:'
                . (int) $user['id'];

            if (
                isset(
                    $linked[$reference]
                )
            ) {
                continue;
            }

            $user['user_reference'] =
                $reference;

            $user['display_name'] =
                $this->coreDisplayName(
                    $user
                );

            $coreCandidates[] =
                $user;
        }

        return [
            'items' =>
                $items,

            'total' =>
                count($items),

            'q' =>
                trim(
                    (string) (
                        $filters['q']
                        ?? ''
                    )
                ),

            'origin' =>
                trim(
                    (string) (
                        $filters['origin']
                        ?? ''
                    )
                ),

            'state' =>
                trim(
                    (string) (
                        $filters['state']
                        ?? ''
                    )
                ),

            'core_candidates' =>
                $coreCandidates,

            'core_q' =>
                $coreQ,
        ];
    }


    public function addCoreUser(
        int $userId,
        int $actorUserId
    ): array {
        if ($userId <= 0) {
            return [
                'ok' => false,
                'error' =>
                    'کاربر سامانه انتخاب نشده است.',
            ];
        }

        $user =
            $this->repository
                ->activeCoreUser(
                    $userId
                );

        if ($user === null) {
            return [
                'ok' => false,
                'error' =>
                    'کاربر فعال مورد نظر پیدا نشد.',
            ];
        }

        $coreReference =
            'user:' . $userId;

        $existing =
            $this->repository
                ->findByCoreReference(
                    $coreReference
                );

        if ($existing !== null) {
            return [
                'ok' => false,
                'error' =>
                    'این کاربر قبلاً در فهرست مخاطبان تیکتینگ وجود دارد.',
            ];
        }

        $email =
            $this->nullable(
                $user['email']
                ?? null
            );

        $mobile =
            $this->nullable(
                $user['mobile']
                ?? null
            );

        $result =
            $this->repository
                ->createCoreParticipant([
                    'public_reference' =>
                        $this->participantReference(),

                    'core_user_reference' =>
                        $coreReference,

                    'core_person_reference' =>
                        $this->nullable(
                            $user[
                                'person_public_reference'
                            ]
                            ?? null
                        ),

                    'full_name' =>
                        $this->coreDisplayName(
                            $user
                        ),

                    'email' =>
                        $email,

                    'email_normalized' =>
                        $this->normalizeEmail(
                            $email
                        ),

                    'mobile' =>
                        $mobile,

                    'mobile_normalized' =>
                        $this->normalizeMobile(
                            $mobile
                        ),

                    'actor_reference' =>
                        'user:'
                        . $actorUserId,
                ]);

        return [
            'ok' => true,
            'participant' =>
                $result,
        ];
    }


    public function addManual(
        array $input,
        int $actorUserId
    ): array {
        $form = [
            'full_name' =>
                trim(
                    (string) (
                        $input['full_name']
                        ?? ''
                    )
                ),

            'email' =>
                trim(
                    (string) (
                        $input['email']
                        ?? ''
                    )
                ),

            'mobile' =>
                trim(
                    (string) (
                        $input['mobile']
                        ?? ''
                    )
                ),

            'organization_name' =>
                trim(
                    (string) (
                        $input[
                            'organization_name'
                        ]
                        ?? ''
                    )
                ),

            'external_reference' =>
                trim(
                    (string) (
                        $input[
                            'external_reference'
                        ]
                        ?? ''
                    )
                ),
        ];


        $errors = [];

        if (
            $this->length(
                $form['full_name']
            ) < 2
        ) {
            $errors['full_name'] =
                'نام مخاطب باید حداقل ۲ نویسه باشد.';
        }

        if (
            $this->length(
                $form['full_name']
            ) > 255
        ) {
            $errors['full_name'] =
                'نام مخاطب بیش از حد مجاز است.';
        }

        if (
            $form['email'] !== ''
            && filter_var(
                $form['email'],
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $errors['email'] =
                'ایمیل واردشده معتبر نیست.';
        }

        if (
            $this->length(
                $form['mobile']
            ) > 50
        ) {
            $errors['mobile'] =
                'شماره همراه بیش از حد مجاز است.';
        }

        if (
            $this->length(
                $form['organization_name']
            ) > 255
        ) {
            $errors['organization_name'] =
                'نام سازمان بیش از حد مجاز است.';
        }

        if (
            $this->length(
                $form['external_reference']
            ) > 190
        ) {
            $errors['external_reference'] =
                'شناسه خارجی بیش از حد مجاز است.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'form' => $form,
            ];
        }


        $email =
            $this->nullable(
                $form['email']
            );

        $mobile =
            $this->nullable(
                $form['mobile']
            );

        $emailNormalized =
            $this->normalizeEmail(
                $email
            );

        $mobileNormalized =
            $this->normalizeMobile(
                $mobile
            );


        $duplicate =
            $this->repository
                ->duplicateContact(
                    $emailNormalized,
                    $mobileNormalized
                );

        if ($duplicate !== null) {
            return [
                'ok' => false,

                'errors' => [
                    'duplicate' =>
                        'مخاطبی با همین ایمیل یا شماره همراه قبلاً ثبت شده است: '
                        . (string) $duplicate[
                            'full_name'
                        ],
                ],

                'form' =>
                    $form,
            ];
        }


        $result =
            $this->repository
                ->createManualParticipant([
                    'public_reference' =>
                        $this->participantReference(),

                    'full_name' =>
                        $form['full_name'],

                    'email' =>
                        $email,

                    'email_normalized' =>
                        $emailNormalized,

                    'mobile' =>
                        $mobile,

                    'mobile_normalized' =>
                        $mobileNormalized,

                    'organization_name' =>
                        $this->nullable(
                            $form[
                                'organization_name'
                            ]
                        ),

                    'external_reference' =>
                        $this->nullable(
                            $form[
                                'external_reference'
                            ]
                        ),

                    'actor_reference' =>
                        'user:'
                        . $actorUserId,
                ]);

        return [
            'ok' => true,
            'participant' =>
                $result,
        ];
    }


    private function enrichLinkedCoreProfiles(
        array $items
    ): array {
        $userIds = [];

        foreach ($items as $item) {

            $reference =
                trim(
                    (string) (
                        $item[
                            'core_user_reference'
                        ]
                        ?? ''
                    )
                );

            if (
                preg_match(
                    '/^user:([1-9][0-9]*)$/',
                    $reference,
                    $matches
                ) !== 1
            ) {
                continue;
            }

            $userIds[] =
                (int) $matches[1];
        }

        $profiles =
            $this->repository
                ->coreProfilesByUserIds(
                    $userIds
                );

        foreach ($items as &$item) {

            $reference =
                trim(
                    (string) (
                        $item[
                            'core_user_reference'
                        ]
                        ?? ''
                    )
                );

            if (
                preg_match(
                    '/^user:([1-9][0-9]*)$/',
                    $reference,
                    $matches
                ) !== 1
            ) {
                continue;
            }

            $userId =
                (int) $matches[1];

            $profile =
                $profiles[$userId]
                ?? null;

            if (!is_array($profile)) {
                continue;
            }

            $item['full_name'] =
                $this->coreDisplayName(
                    $profile
                );

            $email =
                $this->nullable(
                    $profile['email']
                    ?? null
                );

            $mobile =
                $this->nullable(
                    $profile['mobile']
                    ?? null
                );

            if ($email !== null) {
                $item['email'] =
                    $email;
            }

            if ($mobile !== null) {
                $item['mobile'] =
                    $mobile;
            }
        }

        unset($item);

        return $items;
    }


    private function coreDisplayName(
        array $user
    ): string {
        foreach ([
            'full_name',
            'username',
            'email',
        ] as $key) {

            $value =
                trim(
                    (string) (
                        $user[$key]
                        ?? ''
                    )
                );

            if ($value !== '') {
                return $value;
            }
        }

        return
            'کاربر '
            . (int) (
                $user['id']
                ?? 0
            );
    }


    private function participantReference(): string
    {
        return
            'TPR-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }


    private function normalizeEmail(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            strtolower(
                trim($value)
            );

        return
            $value !== ''
                ? $value
                : null;
    }


    private function normalizeMobile(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            strtr(
                trim($value),
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

        $value =
            preg_replace(
                '/[^0-9+]/',
                '',
                $value
            );

        if (
            !is_string($value)
            || $value === ''
        ) {
            return null;
        }

        if (
            str_starts_with(
                $value,
                '0098'
            )
        ) {
            $value =
                '+98'
                . substr(
                    $value,
                    4
                );
        }

        if (
            preg_match(
                '/^09[0-9]{9}$/',
                $value
            ) === 1
        ) {
            $value =
                '+98'
                . substr(
                    $value,
                    1
                );
        }

        return $value;
    }


    private function nullable(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) (
                    $value
                    ?? ''
                )
            );

        return
            $value !== ''
                ? $value
                : null;
    }


    private function length(
        string $value
    ): int {
        return
            function_exists(
                'mb_strlen'
            )
                ? mb_strlen(
                    $value,
                    'UTF-8'
                )
                : strlen($value);
    }
}
