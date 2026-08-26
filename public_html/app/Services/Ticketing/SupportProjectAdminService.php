<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Repositories\SupportProjectAdminRepository;
use App\Support\AdminIcon;

final class SupportProjectAdminService
{
    public function __construct(
        private ?SupportProjectAdminRepository $projects = null
    ) {
        $this->projects ??=
            new SupportProjectAdminRepository();
    }


    public function index(
        array $filters = []
    ): array {
        $q =
            $this->limit(
                trim(
                    (string) (
                        $filters['q']
                        ?? ''
                    )
                ),
                120
            );

        $status =
            trim(
                (string) (
                    $filters['status']
                    ?? ''
                )
            );

        if (
            !in_array(
                $status,
                [
                    '',
                    'active',
                    'inactive',
                ],
                true
            )
        ) {
            $status = '';
        }

        $items =
            $this->projects->index([
                'q' => $q,
                'status' => $status,
            ]);

        return [
            'items' => $items,
            'total' => count($items),
            'q' => $q,
            'status' => $status,
        ];
    }


    public function createForm(
        array $form = []
    ): array {
        return [
            'mode' => 'create',

            'project' => null,

            'form' =>
                array_merge(
                    [
                        'code' => '',
                        'title' => '',
                        'description' => '',
                        'icon_code' => 'sitemap',
                        'color_code' => '#258843',
                        'sort_order' => 10,
                        'is_active' => 1,
                    ],
                    $form
                ),

            'icon_options' =>
                AdminIcon::codes(),
        ];
    }


    public function editForm(
        string $publicReference,
        array $form = []
    ): ?array {
        $project =
            $this->projects->findByReference(
                trim($publicReference)
            );

        if ($project === null) {
            return null;
        }

        return [
            'mode' => 'edit',

            'project' => $project,

            'form' =>
                array_merge(
                    [
                        'code' =>
                            $project['code'],

                        'title' =>
                            $project['title'],

                        'description' =>
                            $project['description']
                            ?? '',

                        'icon_code' =>
                            $project['icon_code']
                            ?: 'sitemap',

                        'color_code' =>
                            $project['color_code']
                            ?: '#258843',

                        'sort_order' =>
                            (int) $project[
                                'sort_order'
                            ],

                        'is_active' =>
                            (int) $project[
                                'is_active'
                            ],
                    ],
                    $form
                ),

            'icon_options' =>
                AdminIcon::codes(),
        ];
    }


    public function create(
        array $input,
        int $userId
    ): array {
        $form =
            $this->normalize(
                $input,
                true
            );

        $errors =
            $this->validate(
                $form,
                true
            );

        if (
            $errors === []
            && $this->projects->codeExists(
                $form['code']
            )
        ) {
            $errors['code'] =
                'این کد پروژه قبلاً استفاده شده است.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'form' => $form,
            ];
        }

        $reference =
            $this->reference();

        $project =
            $this->projects->create([
                'public_reference' =>
                    $reference,

                'code' =>
                    $form['code'],

                'title' =>
                    $form['title'],

                'description' =>
                    $form['description']
                    !== ''
                        ? $form['description']
                        : null,

                'icon_code' =>
                    $form['icon_code'],

                'color_code' =>
                    $form['color_code'],

                'sort_order' =>
                    $form['sort_order'],

                'is_active' =>
                    $form['is_active'],

                'actor_reference' =>
                    'user:' . $userId,
            ]);

        return [
            'ok' => true,

            'public_reference' =>
                $project[
                    'public_reference'
                ],
        ];
    }


    public function update(
        string $publicReference,
        array $input
    ): array {
        $project =
            $this->projects->findByReference(
                trim($publicReference)
            );

        if (
            $project === null
            || !empty(
                $project['archived_at']
            )
        ) {
            return [
                'ok' => false,
                'not_found' => true,
            ];
        }

        $form =
            $this->normalize(
                array_merge(
                    $input,
                    [
                        'code' =>
                            $project['code'],
                    ]
                ),
                false
            );

        $errors =
            $this->validate(
                $form,
                false
            );

        if ($errors !== []) {
            return [
                'ok' => false,
                'errors' => $errors,
                'form' => $form,
            ];
        }

        $this->projects->update(
            (int) $project['id'],
            [
                'title' =>
                    $form['title'],

                'description' =>
                    $form['description']
                    !== ''
                        ? $form['description']
                        : null,

                'icon_code' =>
                    $form['icon_code'],

                'color_code' =>
                    $form['color_code'],

                'sort_order' =>
                    $form['sort_order'],

                'is_active' =>
                    $form['is_active'],
            ]
        );

        return [
            'ok' => true,

            'public_reference' =>
                $project[
                    'public_reference'
                ],
        ];
    }


    private function normalize(
        array $input,
        bool $creating
    ): array {
        $code =
            strtolower(
                trim(
                    (string) (
                        $input['code']
                        ?? ''
                    )
                )
            );

        if (!$creating) {
            $code =
                trim($code);
        }

        return [
            'code' =>
                $this->limit(
                    $code,
                    80
                ),

            'title' =>
                $this->limit(
                    trim(
                        (string) (
                            $input['title']
                            ?? ''
                        )
                    ),
                    255
                ),

            'description' =>
                $this->limit(
                    trim(
                        (string) (
                            $input['description']
                            ?? ''
                        )
                    ),
                    5000
                ),

            'icon_code' =>
                $this->limit(
                    strtolower(
                        trim(
                            (string) (
                                $input['icon_code']
                                ?? 'sitemap'
                            )
                        )
                    ),
                    60
                ),

            'color_code' =>
                strtolower(
                    trim(
                        (string) (
                            $input['color_code']
                            ?? '#258843'
                        )
                    )
                ),

            'sort_order' =>
                max(
                    0,
                    min(
                        100000,
                        (int) (
                            $input['sort_order']
                            ?? 0
                        )
                    )
                ),

            'is_active' =>
                !empty(
                    $input['is_active']
                )
                    ? 1
                    : 0,
        ];
    }


    private function validate(
        array $form,
        bool $creating
    ): array {
        $errors = [];

        if (
            $creating
            && preg_match(
                '/^[a-z][a-z0-9_-]{1,79}$/',
                $form['code']
            ) !== 1
        ) {
            $errors['code'] =
                'کد پروژه باید با حرف انگلیسی شروع شود و فقط شامل حروف کوچک، عدد، خط تیره یا زیرخط باشد.';
        }

        $titleLength =
            $this->length(
                $form['title']
            );

        if ($titleLength < 2) {
            $errors['title'] =
                'عنوان پروژه باید حداقل ۲ نویسه باشد.';
        }

        if (
            $form['description'] !== ''
            && $this->length(
                $form['description']
            ) > 5000
        ) {
            $errors['description'] =
                'توضیحات پروژه بیش از حد مجاز است.';
        }

        if (
            !AdminIcon::supports(
                $form['icon_code']
            )
        ) {
            $errors['icon_code'] =
                'آیکون انتخاب‌شده معتبر نیست.';
        }

        if (
            preg_match(
                '/^#[0-9a-f]{6}$/i',
                $form['color_code']
            ) !== 1
        ) {
            $errors['color_code'] =
                'رنگ پروژه معتبر نیست.';
        }

        return $errors;
    }


    private function reference(): string
    {
        return
            'TSP-'
            . strtoupper(
                bin2hex(
                    random_bytes(10)
                )
            );
    }


    private function length(
        string $value
    ): int {
        return
            function_exists('mb_strlen')
                ? mb_strlen(
                    $value,
                    'UTF-8'
                )
                : strlen($value);
    }


    private function limit(
        string $value,
        int $length
    ): string {
        if (
            $this->length($value)
            <= $length
        ) {
            return $value;
        }

        return
            function_exists('mb_substr')
                ? mb_substr(
                    $value,
                    0,
                    $length,
                    'UTF-8'
                )
                : substr(
                    $value,
                    0,
                    $length
                );
    }
}
