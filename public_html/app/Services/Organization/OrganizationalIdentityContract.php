<?php

namespace App\Services\Organization;

final class OrganizationalIdentityContract
{
    public function __construct(private readonly UserOrganizationalContextResolver $resolver = new UserOrganizationalContextResolver())
    {
    }

    public function forUser(int $userId): ?array
    {
        $context = $this->resolver->current($userId);
        if ($context === null) {
            return null;
        }

        return [
            'person_reference' => $context['person_reference'],
            'appointment_reference' => $context['appointment_reference'],
            'organization_reference' => $context['organization_reference'],
            'org_unit_reference' => $context['org_unit_reference'],
            'position_reference' => $context['position_reference'],
            'display' => [
                'fa' => [
                    'person' => $context['person_name_fa'],
                    'position' => $context['position_title_fa'],
                    'unit' => $context['org_unit_title_fa'],
                    'organization' => $context['organization_title_fa'],
                ],
                'en' => [
                    'person' => $context['person_name_en'],
                    'position' => $context['position_title_en'],
                    'unit' => $context['org_unit_title_en'],
                    'organization' => $context['organization_title_en'],
                ],
            ],
        ];
    }
}
