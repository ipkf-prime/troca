<?php

namespace App\Services\Automation\Correspondence;

use App\Repositories\ExternalOrganizationDirectoryRepository;
use Throwable;

final class ExternalOrganizationDirectoryReferenceResolver
{
    public function __construct(
        private ?ExternalOrganizationDirectoryRepository $repository = null
    ) {
        $this->repository ??=
            new ExternalOrganizationDirectoryRepository();
    }

    public function resolve(
        ?string $organizationReference,
        ?string $contactPointReference
    ): array {
        $organizationReference =
            trim(
                (string) (
                    $organizationReference
                    ?? ''
                )
            );

        $contactPointReference =
            trim(
                (string) (
                    $contactPointReference
                    ?? ''
                )
            );

        /*
         * Legacy/free-text external parties remain valid.
         * V3A only validates references when a binding
         * has explicitly been submitted.
         */
        if (
            $organizationReference === ''
            && $contactPointReference === ''
        ) {
            return [
                'ok' => true,
                'external_organization_public_reference' =>
                    null,
                'external_contact_point_public_reference' =>
                    null,

                'external_organization_name' =>
                    null,

                'external_contact_point_title' =>
                    null,
            ];
        }

        if ($organizationReference === '') {
            return [
                'ok' => false,
                'error' =>
                    'external_directory_organization_required',
            ];
        }

        try {
            $organization =
                $this->repository
                    ->organization(
                        $organizationReference
                    );

            if (
                !is_array($organization)
                || (string) (
                    $organization['status']
                    ?? ''
                ) !== 'active'
            ) {
                return [
                    'ok' => false,
                    'error' =>
                        'invalid_external_organization_reference',
                ];
            }

            /*
             * Organization-only binding is valid at the
             * correspondence-party layer.
             *
             * Dispatch will later require an explicit
             * destination/contact point.
             */
            if ($contactPointReference === '') {
                return [
                    'ok' => true,

                    'external_organization_public_reference' =>
                        (string) $organization[
                            'public_reference'
                        ],

                    'external_contact_point_public_reference' =>
                        null,

                    /*
                     * external-directory-canonical-snapshot-v3b
                     */
                    'external_organization_name' =>
                        $this->organizationName(
                            $organization
                        ),

                    'external_contact_point_title' =>
                        null,
                ];
            }

            $contactPoint =
                $this->repository
                    ->contactPoint(
                        $contactPointReference
                    );

            if (
                !is_array($contactPoint)
                || (string) (
                    $contactPoint['status']
                    ?? ''
                ) !== 'active'
            ) {
                return [
                    'ok' => false,
                    'error' =>
                        'invalid_external_contact_point_reference',
                ];
            }

            if (
                (int) (
                    $contactPoint[
                        'external_organization_id'
                    ]
                    ?? 0
                )
                !==
                (int) (
                    $organization['id']
                    ?? 0
                )
            ) {
                return [
                    'ok' => false,
                    'error' =>
                        'external_contact_point_organization_mismatch',
                ];
            }

            return [
                'ok' => true,

                'external_organization_public_reference' =>
                    (string) $organization[
                        'public_reference'
                    ],

                'external_contact_point_public_reference' =>
                    (string) $contactPoint[
                        'public_reference'
                    ],

                /*
                 * external-directory-canonical-snapshot-v3b
                 */
                'external_organization_name' =>
                    $this->organizationName(
                        $organization
                    ),

                'external_contact_point_title' =>
                    trim(
                        (string) (
                            $contactPoint[
                                'title'
                            ]
                            ?? ''
                        )
                    ),
            ];

        } catch (Throwable) {
            /*
             * Fail closed. A forged or unverifiable Core
             * directory binding must never be persisted.
             */
            return [
                'ok' => false,
                'error' =>
                    'external_directory_unavailable',
            ];
        }
    }

    private function organizationName(
        array $organization
    ): string {
        $title =
            trim(
                (string) (
                    $organization[
                        'title_fa'
                    ]
                    ?? ''
                )
            );

        if ($title !== '') {
            return $title;
        }

        return
            trim(
                (string) (
                    $organization[
                        'short_title'
                    ]
                    ?? ''
                )
            );
    }

}
