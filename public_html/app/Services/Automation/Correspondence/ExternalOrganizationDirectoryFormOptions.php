<?php

namespace App\Services\Automation\Correspondence;

use App\Repositories\ExternalOrganizationDirectoryRepository;
use Throwable;

final class ExternalOrganizationDirectoryFormOptions
{
    public function __construct(
        private ?ExternalOrganizationDirectoryRepository $repository = null
    ) {
    }

    public function options(): array
    {
        try {
            $repository =
                $this->repository ??=
                    new ExternalOrganizationDirectoryRepository();

            $result = [];

            foreach (
                $repository->organizations(
                    '',
                    false
                )
                as $organization
            ) {
                if (
                    (string) (
                        $organization['status']
                        ?? ''
                    ) !== 'active'
                ) {
                    continue;
                }

                $id =
                    (int) (
                        $organization['id']
                        ?? 0
                    );

                $reference =
                    trim(
                        (string) (
                            $organization[
                                'public_reference'
                            ]
                            ?? ''
                        )
                    );

                $title =
                    trim(
                        (string) (
                            $organization['title_fa']
                            ?? ''
                        )
                    );

                if ($title === '') {
                    $title =
                        trim(
                            (string) (
                                $organization[
                                    'short_title'
                                ]
                                ?? ''
                            )
                        );
                }

                if (
                    $id < 1
                    || $reference === ''
                    || $title === ''
                ) {
                    continue;
                }

                $points = [];

                foreach (
                    $repository->contactPoints(
                        $id
                    )
                    as $point
                ) {
                    if (
                        (string) (
                            $point['status']
                            ?? ''
                        ) !== 'active'
                    ) {
                        continue;
                    }

                    $pointReference =
                        trim(
                            (string) (
                                $point[
                                    'public_reference'
                                ]
                                ?? ''
                            )
                        );

                    $pointTitle =
                        trim(
                            (string) (
                                $point['title']
                                ?? ''
                            )
                        );

                    if (
                        $pointReference === ''
                        || $pointTitle === ''
                    ) {
                        continue;
                    }

                    $points[] = [
                        'public_reference' =>
                            $pointReference,

                        'title' =>
                            $pointTitle,

                        'is_primary' =>
                            (int) (
                                $point[
                                    'is_primary'
                                ]
                                ?? 0
                            ) === 1,
                    ];
                }

                $result[] = [
                    'public_reference' =>
                        $reference,

                    'title' =>
                        $title,

                    'contact_points' =>
                        $points,
                ];
            }

            return $result;

        } catch (Throwable) {
            return [];
        }
    }
}
