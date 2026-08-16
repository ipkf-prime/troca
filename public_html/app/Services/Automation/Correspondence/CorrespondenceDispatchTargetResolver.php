<?php

namespace App\Services\Automation\Correspondence;

use App\Repositories\ExternalOrganizationDirectoryRepository;
use Throwable;

final class CorrespondenceDispatchTargetResolver
{
    private const DIRECT_CHANNELS = [
        'fax',
        'email',
        'system',
    ];

    private const PHYSICAL_CHANNELS = [
        'postal',
        'courier',
        'hand_delivery',
    ];

    public function __construct(
        private ?ExternalOrganizationDirectoryRepository $directory = null
    ) {
        $this->directory ??=
            new ExternalOrganizationDirectoryRepository();
    }

    public function resolve(
        array $party,
        string $channelCode
    ): array {
        $channelCode =
            strtolower(
                trim(
                    $channelCode
                )
            );

        if (
            (string) (
                $party['target_kind_code']
                ?? ''
            ) !== 'external'
        ) {
            return $this->failure(
                'dispatch_target_must_be_external'
            );
        }

        $organizationReference =
            trim(
                (string) (
                    $party[
                        'external_organization_public_reference'
                    ]
                    ?? ''
                )
            );

        $pointReference =
            trim(
                (string) (
                    $party[
                        'external_contact_point_public_reference'
                    ]
                    ?? ''
                )
            );

        if (
            $organizationReference === ''
            || $pointReference === ''
        ) {
            return $this->failure(
                'external_directory_binding_required'
            );
        }

        try {
            $organization =
                $this->directory
                    ->organization(
                        $organizationReference
                    );

            $point =
                $this->directory
                    ->contactPoint(
                        $pointReference
                    );

            if (
                !is_array($organization)
                ||
                (string) (
                    $organization['status']
                    ?? ''
                ) !== 'active'
            ) {
                return $this->failure(
                    'external_directory_reference_invalid'
                );
            }

            if (
                !is_array($point)
                ||
                (string) (
                    $point['status']
                    ?? ''
                ) !== 'active'
                ||
                (int) (
                    $point[
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
                return $this->failure(
                    'external_directory_reference_invalid'
                );
            }

            $destination = null;

            if (
                in_array(
                    $channelCode,
                    self::DIRECT_CHANNELS,
                    true
                )
            ) {
                $destination =
                    $this->directDestination(
                        (int) $point['id'],
                        $channelCode
                    );
            }

            if (
                in_array(
                    $channelCode,
                    self::PHYSICAL_CHANNELS,
                    true
                )
            ) {
                $destination =
                    $this->physicalDestination(
                        (int) $point['id'],
                        $channelCode
                    );
            }

            if (!is_array($destination)) {
                return $this->failure(
                    'dispatch_destination_unavailable'
                );
            }

            $organizationTitle =
                trim(
                    (string) (
                        $organization['title_fa']
                        ?? ''
                    )
                );

            if ($organizationTitle === '') {
                $organizationTitle =
                    trim(
                        (string) (
                            $organization[
                                'short_title'
                            ]
                            ?? ''
                        )
                    );
            }

            return [
                'ok' => true,

                'external_organization_id' =>
                    (int) $organization['id'],

                'external_organization_public_reference' =>
                    (string) $organization[
                        'public_reference'
                    ],

                'external_contact_point_id' =>
                    (int) $point['id'],

                'external_contact_point_public_reference' =>
                    (string) $point[
                        'public_reference'
                    ],

                'target_snapshot' => [
                    'correspondence_party_id' =>
                        (int) (
                            $party['id']
                            ?? 0
                        ),

                    'party_role_code' =>
                        (string) (
                            $party[
                                'party_role_code'
                            ]
                            ?? ''
                        ),

                    'target_kind_code' =>
                        'external',

                    'external_display_name' =>
                        $this->nullable(
                            $party[
                                'external_display_name'
                            ]
                            ?? null
                        ),

                    'external_organization_public_reference' =>
                        (string) $organization[
                            'public_reference'
                        ],

                    'external_organization_name' =>
                        $organizationTitle,

                    'external_contact_point_public_reference' =>
                        (string) $point[
                            'public_reference'
                        ],

                    'external_contact_point_title' =>
                        (string) (
                            $point['title']
                            ?? ''
                        ),

                    'contact_person_name' =>
                        $this->nullable(
                            $point[
                                'contact_person_name'
                            ]
                            ?? null
                        ),

                    'contact_person_title' =>
                        $this->nullable(
                            $point[
                                'contact_person_title'
                            ]
                            ?? null
                        ),
                ],

                'destination_snapshot' =>
                    $destination,
            ];

        } catch (Throwable) {
            return $this->failure(
                'external_directory_unavailable'
            );
        }
    }

    private function directDestination(
        int $contactPointId,
        string $channelCode
    ): ?array {
        foreach (
            $this->directory
                ->contactMethods(
                    $contactPointId
                )
            as $method
        ) {
            if (
                (string) (
                    $method['status']
                    ?? ''
                ) !== 'active'
                ||
                (int) (
                    $method[
                        'supports_dispatch'
                    ]
                    ?? 0
                ) !== 1
            ) {
                continue;
            }

            $typeCode =
                strtolower(
                    trim(
                        (string) (
                            $method[
                                'contact_type_code'
                            ]
                            ?? ''
                        )
                    )
                );

            $typeChannel =
                strtolower(
                    trim(
                        (string) (
                            $method[
                                'contact_type_channel'
                            ]
                            ?? ''
                        )
                    )
                );

            if (
                $typeCode !== $channelCode
                && $typeChannel !== $channelCode
            ) {
                continue;
            }

            $value =
                trim(
                    (string) (
                        $method[
                            'normalized_value'
                        ]
                        ?? ''
                    )
                );

            if ($value === '') {
                $value =
                    trim(
                        (string) (
                            $method['value']
                            ?? ''
                        )
                    );
            }

            if ($value === '') {
                continue;
            }

            return [
                'destination_kind' =>
                    'contact_method',

                'channel_code' =>
                    $channelCode,

                'public_reference' =>
                    (string) (
                        $method[
                            'public_reference'
                        ]
                        ?? ''
                    ),

                'contact_type_code' =>
                    $typeCode,

                'contact_type_channel' =>
                    $typeChannel,

                'value' =>
                    (string) (
                        $method['value']
                        ?? ''
                    ),

                'normalized_value' =>
                    $value,

                'label' =>
                    $this->nullable(
                        $method['label']
                        ?? null
                    ),

                'is_primary' =>
                    (int) (
                        $method[
                            'is_primary'
                        ]
                        ?? 0
                    ) === 1,

                'is_verified' =>
                    (int) (
                        $method[
                            'is_verified'
                        ]
                        ?? 0
                    ) === 1,
            ];
        }

        return null;
    }

    private function physicalDestination(
        int $contactPointId,
        string $channelCode
    ): ?array {
        foreach (
            $this->directory
                ->addresses(
                    $contactPointId
                )
            as $address
        ) {
            if (
                (string) (
                    $address['status']
                    ?? ''
                ) !== 'active'
                ||
                (int) (
                    $address[
                        'supports_dispatch'
                    ]
                    ?? 0
                ) !== 1
            ) {
                continue;
            }

            $addressLine =
                trim(
                    (string) (
                        $address[
                            'address_line'
                        ]
                        ?? ''
                    )
                );

            if ($addressLine === '') {
                continue;
            }

            return [
                'destination_kind' =>
                    'postal_address',

                'channel_code' =>
                    $channelCode,

                'public_reference' =>
                    (string) (
                        $address[
                            'public_reference'
                        ]
                        ?? ''
                    ),

                'address_type_code' =>
                    (string) (
                        $address[
                            'address_type_code'
                        ]
                        ?? ''
                    ),

                'district' =>
                    $this->nullable(
                        $address['district']
                        ?? null
                    ),

                'address_line' =>
                    $addressLine,

                'postal_code' =>
                    $this->nullable(
                        $address[
                            'postal_code'
                        ]
                        ?? null
                    ),

                'is_primary' =>
                    (int) (
                        $address[
                            'is_primary'
                        ]
                        ?? 0
                    ) === 1,
            ];
        }

        return null;
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

    private function failure(
        string $code
    ): array {
        return [
            'ok' => false,
            'error' => $code,
        ];
    }
}
