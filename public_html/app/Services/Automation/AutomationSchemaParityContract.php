<?php

namespace App\Services\Automation;

class AutomationSchemaParityContract
{
    public const TABLES = [
        'lookup_domains',
        'lookup_values',
        'correspondences',
        'correspondence_versions',
        'correspondence_document_templates',
        'correspondence_document_template_versions',
        'correspondence_parties',
        'registry_books',
        'correspondence_registrations',
        'correspondence_relations',
        'correspondence_referrals',
        'correspondence_events',
        'private_files',
        'correspondence_attachments',
    ];

    public const INTERNAL_FOREIGN_KEYS = [
        'lookup_values_domain_fk',
        'corr_versions_corr_fk',
        'corr_doc_template_versions_template_fk',
        'corr_current_version_fk',
        'corr_parties_corr_fk',
        'corr_reg_corr_fk',
        'corr_reg_book_fk',
        'corr_rel_source_fk',
        'corr_rel_target_fk',
        'corr_ref_corr_fk',
        'corr_ref_parent_fk',
        'corr_events_corr_fk',
        'corr_events_referral_fk',
        'corr_attach_corr_fk',
        'corr_attach_version_fk',
        'corr_attach_file_fk',
    ];

    public const CORE_REFERENCE_TABLES = [
        'persons',
        'users',
        'organizations',
        'org_units',
        'positions',
        'appointments',
        'roles',
        'permissions',
        'fiscal_years',
        'geographic_locations',
    ];

    public const OPERATIONAL_TABLES = [
        'correspondences',
        'correspondence_versions',
        'correspondence_document_templates',
        'correspondence_document_template_versions',
        'correspondence_parties',
        'registry_books',
        'correspondence_registrations',
        'correspondence_relations',
        'correspondence_referrals',
        'correspondence_events',
        'private_files',
        'correspondence_attachments',
    ];
}
