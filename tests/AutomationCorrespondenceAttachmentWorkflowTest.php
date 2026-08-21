<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$form = file_get_contents(
    $root
    . '/public_html/resources/views/admin/automation-correspondence-form.php'
);

$routes = file_get_contents(
    $root
    . '/public_html/routes/web.php'
);

$service = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/CorrespondenceAttachmentService.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
};

$expect(
    is_string($form)
    && is_string($routes)
    && is_string($service),
    'Attachment workflow sources must be readable.'
);

foreach (
    [
        'correspondence-attachment-wizard-v1',
        'attachment-wizard-single-row-v1',
        'enctype="multipart/form-data"',
        'data-draft-tab="attachments"',
        'data-draft-panel="attachments"',
        'name="attachments[]"',
        'data-attachment-input',
        'data-attachment-list',
        'automation-attachment-remove',
        '$attachmentPolicyClientRules',
        'json_encode(',
        'attachmentRules.maxTotal',
        "set('attachments'",
    ]
    as $marker
) {
    $expect(
        str_contains(
            $form,
            $marker
        ),
        'Missing attachment form marker: '
            . $marker
    );
}

foreach (
    [
        'public function uploadMany(',
        '$this->policy->maxFiles()',
        '$this->policy->maxTotalBytes()',
        "'too_many_files'",
        "'invalid_total_size'",
    ]
    as $marker
) {
    $expect(
        str_contains(
            $service,
            $marker
        ),
        'Missing attachment service marker: '
            . $marker
    );
}

$expect(
    substr_count(
        $routes,
        '->uploadMany('
    ) === 2,
    'Create and update routes must both upload attachments.'
);

$expect(
    substr_count(
        $routes,
        "\$_FILES['attachments']"
    ) === 4,
    'Both routes must normalize the multi-file upload input.'
);

$expect(
    str_contains(
        $routes,
        "'partial'"
    ),
    'Partial attachment result must be represented.'
);

$expect(
    str_contains(
        $routes,
        "'uploaded'"
    )
    && str_contains(
        $routes,
        "'failed'"
    ),
    'Upload result states must be represented.'
);

foreach (
    [
        'attachment-download-utf8-filename-v1',
        "filename*=UTF-8\\'\\'",
        'rawurlencode(',
        '$originalFilename',
        '$contentDisposition',
    ]
    as $marker
) {
    $expect(
        str_contains(
            $routes,
            $marker
        ),
        'Missing UTF-8 download filename marker: '
            . $marker
    );
}

$expect(
    !str_contains(
        $routes,
        <<<'LEGACY'
$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['original_filename']) ?: 'attachment';
LEGACY
    ),
    'Legacy ASCII-only download filename logic must be removed.'
);

$repository = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/CorrespondenceAttachmentRepository.php'
);

$lookups = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/AutomationLookupRepository.php'
);

$viewModel = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/CorrespondenceViewModelBuilder.php'
);

$detail = file_get_contents(
    $root
    . '/public_html/resources/views/admin/automation-correspondence-detail.php'
);

foreach (
    [
        [$repository, 'attachment-soft-delete-v1'],
        [$repository, "status = 'inactive'"],
        [$repository, "'attachment_removed'"],
        [$repository, 'LIMIT 1 FOR UPDATE'],
        [$service, 'public function remove('],
        [$service, "'attachment_not_removable'"],
        [$lookups, "'attachment_removed' => 'پیوست حذف شد'"],
        [$viewModel, "'remove_url'"],
        [$detail, 'حذف پیوست'],
        [$detail, "attachmentStatus==='removed'"],
        [$routes, 'attachment-soft-delete-route-v1'],
        [$routes, '/attachments/{file_reference}/remove'],
    ]
    as [$content, $marker]
) {
    $expect(
        is_string($content)
        && str_contains($content, $marker),
        'Missing attachment removal marker: '
            . $marker
    );
}

$expect(
    !str_contains(
        (string) $repository,
        'DELETE FROM private_files'
    ),
    'Attachment removal must not physically delete private files.'
);

$expect(
    !str_contains(
        (string) $detail,
        'name="_token"value='
    ),
    'Attachment removal token input must be valid HTML.'
);

/**
 * attachment-metadata-contract-test-v1
 */
$metadataContracts = [
    [
        $repository,
        'attachment-metadata-edit-v1',
    ],
    [
        $repository,
        'public function updateMetadata(',
    ],
    [
        $repository,
        'UPDATE correspondence_attachments',
    ],
    [
        $repository,
        "'attachment_metadata_updated'",
    ],
    [
        $repository,
        'LIMIT 1 FOR UPDATE',
    ],
    [
        $service,
        'public function updateMetadata(',
    ],
    [
        $service,
        "'invalid_attachment_role'",
    ],
    [
        $service,
        "'attachment_not_editable'",
    ],
    [
        $service,
        "'metadata_updated'",
    ],
    [
        $lookups,
        "'attachment_metadata_updated' => "
        . "'مشخصات پیوست ویرایش شد'",
    ],
    [
        $viewModel,
        "'role_code'",
    ],
    [
        $viewModel,
        "'title_raw'",
    ],
    [
        $viewModel,
        "'edit_url'",
    ],
    [
        $detail,
        'ذخیره مشخصات',
    ],
    [
        $detail,
        "attachmentStatus==='metadata_updated'",
    ],
    [
        $detail,
        "attachmentStatus==='invalid_attachment_role'",
    ],
    [
        $detail,
        "attachmentStatus==='attachment_not_editable'",
    ],
    [
        $routes,
        'attachment-metadata-edit-route-v1',
    ],
    [
        $routes,
        '/attachments/{file_reference}/metadata',
    ],
    [
        $routes,
        '->updateMetadata(',
    ],
];

foreach ($metadataContracts as [$content, $requiredMarker]) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $requiredMarker
        ),
        'Missing attachment metadata marker: '
            . $requiredMarker
    );
}

foreach (
    [
        'موفقیتحذف',
        'فایلرا',
        'مدرکپشتیبان',
        'method="post"action=',
        '<divclass=',
        'name="_token"value=',
    ]
    as $malformedMarker
) {
    $expect(
        !str_contains(
            (string) $detail,
            $malformedMarker
        ),
        'Malformed attachment UI marker remains: '
            . $malformedMarker
    );
}

$expect(
    substr_count(
        (string) $routes,
        'attachment-metadata-edit-route-v1'
    ) === 1,
    'Attachment metadata route must be declared exactly once.'
);

$expect(
    !str_contains(
        (string) $repository,
        'UPDATE private_files'
        . "\n"
        . '                SET original_filename'
    ),
    'Metadata edit must not change the original filename.'
);

/**
 * attachment-per-file-metadata-contract-test-v1
 */
$perFileMetadataContracts = [
    [
        $form,
        'attachment-per-file-metadata-v1',
    ],
    [
        $form,
        "titleInput.name =\n"
        . "                    'attachment_titles[]'",
    ],
    [
        $form,
        "roleSelect.name =\n"
        . "                    'attachment_role_codes[]'",
    ],
    [
        $form,
        'const attachmentMetadata =',
    ],
    [
        $form,
        'automation-attachment-item__fields',
    ],
    [
        $form,
        'عنوان و نوع هر فایل',
    ],
    [
        $service,
        'attachment-per-file-metadata-backend-v1',
    ],
    [
        $service,
        'array $titles',
    ],
    [
        $service,
        'array $roles',
    ],
    [
        $service,
        "\$file['_metadata_index']",
    ],
    [
        $service,
        '$metadataIndex',
    ],
    [
        $routes,
        "'attachment_titles'",
    ],
    [
        $routes,
        "'attachment_role_codes'",
    ],
];

foreach (
    $perFileMetadataContracts
    as [$content, $requiredMarker]
) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $requiredMarker
        ),
        'Missing per-file attachment metadata marker: '
            . $requiredMarker
    );
}

$expect(
    !str_contains(
        (string) $form,
        'name="attachment_role_code"'
    ),
    'Draft wizard must not retain the global attachment role.'
);

$expect(
    !str_contains(
        (string) $form,
        'admin-alertadmin-alert--danger'
    ),
    'Attachment error alert classes must be separated.'
);

$expect(
    substr_count(
        (string) $routes,
        "'attachment_titles'"
    ) === 2,
    'Create and update routes must both accept per-file titles.'
);

$expect(
    substr_count(
        (string) $routes,
        "'attachment_role_codes'"
    ) === 2,
    'Create and update routes must both accept per-file roles.'
);

$uploadManyStart = strpos(
    (string) $service,
    'public function uploadMany('
);

$uploadManyEnd = strpos(
    (string) $service,
    'public function updateMetadata(',
    $uploadManyStart === false
        ? 0
        : $uploadManyStart
);

$expect(
    $uploadManyStart !== false
    && $uploadManyEnd !== false
    && $uploadManyEnd > $uploadManyStart,
    'Upload-many service boundary must exist.'
);

$uploadManySource = substr(
    (string) $service,
    (int) $uploadManyStart,
    (int) $uploadManyEnd
        - (int) $uploadManyStart
);

$expect(
    preg_match(
        '/\$role,\s*null,\s*\$userId/s',
        $uploadManySource
    ) !== 1,
    'Upload-many must not discard the attachment title.'
);

/**
 * attachment-incremental-picker-contract-test-v1
 */
foreach (
    [
        'attachment-incremental-picker-v1',
        'attachmentFilesBeforeDialog',
        'mergeAttachmentFiles',
        'attachmentFileIdentity',
        'openAttachmentPicker',
        "'افزودن فایل دیگر'",
        'attachmentRules.maxFiles',
    ]
    as $marker
) {
    $expect(
        str_contains(
            (string) $form,
            $marker
        ),
        'Missing incremental attachment picker marker: '
            . $marker
    );
}

/**
 * attachment-primary-uniqueness-contract-test-v1
 */
$primaryAttachmentContracts = [
    [
        $repository,
        'attachment-primary-uniqueness-v1',
    ],
    [
        $repository,
        "a.attachment_role_code = 'main'",
    ],
    [
        $repository,
        'SELECT status_code',
    ],
    [
        $repository,
        'FOR UPDATE',
    ],
    [
        $repository,
        "'primary_attachment_exists'",
    ],
    [
        $service,
        "'primary_attachment_exists'",
    ],
    [
        $form,
        'attachment-primary-ui-guard-v1',
    ],
    [
        $form,
        'برای هر مکاتبه فقط یک فایل اصلی قابل ثبت است.',
    ],
    [
        $detail,
        "attachmentStatus==='primary_attachment_exists'",
    ],
    [
        $detail,
        'برای هر مکاتبه فقط یک فایل اصلی قابل ثبت است.',
    ],
];

foreach (
    $primaryAttachmentContracts
    as [$content, $marker]
) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $marker
        ),
        'Missing primary attachment uniqueness marker: '
            . $marker
    );
}

$expect(
    substr_count(
        (string) $repository,
        'attachment-primary-uniqueness-v1'
    ) === 2,
    'Primary uniqueness must guard upload and metadata edit.'
);

$expect(
    substr_count(
        (string) $detail,
        "attachmentStatus==='primary_attachment_exists'"
    ) === 1,
    'Primary conflict must have one detail message.'
);

/**
 * attachment-total-limit-contract-test-v1
 */
$totalAttachmentLimitContracts = [
    [
        $repository,
        'attachment-total-limit-v1',
    ],
    [
        $repository,
        "'attachment_limit_reached'",
    ],
    [
        $repository,
        '$this->policy->maxFiles()',
    ],
    [
        $service,
        "'attachment_limit_reached'",
    ],
    [
        $detail,
        "attachmentStatus==='attachment_limit_reached'",
    ],
    [
        $detail,
        '$activeAttachmentCount<$attachmentPolicyMaxFiles',
    ],
    [
        $detail,
        '$activeAttachmentCount>=$attachmentPolicyMaxFiles',
    ],
    [
        $detail,
        'attachmentPolicyMaxFilesFa',
    ],
    [
        $detail,
        'attachmentPolicyMaxFilesFa',
    ],
];

foreach (
    $totalAttachmentLimitContracts
    as [$content, $marker]
) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $marker
        ),
        'Missing total attachment limit marker: '
            . $marker
    );
}

/**
 * attachment-dynamic-policy-contract-test-v1
 */
$policy = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/CorrespondenceAttachmentPolicy.php'
);

$envExample = file_get_contents(
    $root
    . '/public_html/.env.example'
);

foreach (
    [
        [$policy, 'attachment-dynamic-policy-v1'],
        [$policy, 'AUTOMATION_ATTACHMENT_MAX_FILES'],
        [$policy, 'AUTOMATION_ATTACHMENT_MAX_FILE_MB'],
        [$policy, 'AUTOMATION_ATTACHMENT_MAX_TOTAL_MB'],
        [$policy, 'AUTOMATION_ATTACHMENT_ALLOWED_EXTENSIONS'],
        [$policy, 'AUTOMATION_ATTACHMENT_ALLOWED_MIME_TYPES'],
        [$policy, 'DEFAULT_MAX_FILES = 3'],
        [$policy, 'DEFAULT_MAX_FILE_MB = 10'],
        [$policy, 'DEFAULT_MAX_TOTAL_MB = 20'],
        [$policy, 'allowedExtensions()'],
        [$policy, 'allowedMimeTypes()'],
        [$policy, 'accepts('],
        [$policy, 'clientRules()'],
        [$policy, 'allowedTypeLabel()'],
        [$policy, 'persianNumber('],
        [$repository, '$this->policy->maxFiles()'],
        [$service, '$this->policy->maxFileBytes()'],
        [$service, '$this->policy->maxTotalBytes()'],
        [$service, '$this->policy->accepts('],
        [$detail, 'attachment-dynamic-policy-ui-v1'],
        [$detail, '$attachmentPolicyMaxFiles'],
        [$detail, '$attachmentPolicyAccept'],
        [$form, '$attachmentPolicyClientRules'],
        [$form, 'json_encode('],
        [$form, 'attachmentRules.maxFiles'],
        [$form, 'attachmentRules.maxFileMb'],
        [$form, 'attachmentRules.maxTotalMb'],
        [$envExample, 'AUTOMATION_ATTACHMENT_MAX_FILES=3'],
        [$envExample, 'AUTOMATION_ATTACHMENT_MAX_FILE_MB=10'],
        [$envExample, 'AUTOMATION_ATTACHMENT_MAX_TOTAL_MB=20'],
    ]
    as [$content, $marker]
) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $marker
        ),
        'Missing dynamic attachment policy marker: '
            . $marker
    );
}

foreach (
    [
        'private const MAX_FILES',
        'private const MAX_TOTAL_BYTES',
        'private const MIME_TYPES',
    ]
    as $legacyServiceMarker
) {
    $expect(
        !str_contains(
            (string) $service,
            $legacyServiceMarker
        ),
        'Legacy attachment service policy remains: '
            . $legacyServiceMarker
    );
}

foreach (
    [
        'maxFiles: 3',
        'maxEach: 10 * 1024 * 1024',
        'maxTotal: 20 * 1024 * 1024',
        'accept=".pdf,.docx,.jpg,.jpeg,.png"',
        'حداکثر ۳ فایل قابل انتخاب است.',
        'حجم هر فایل باید حداکثر ۱۰ مگابایت باشد.',
        'مجموع حجم فایل‌ها باید حداکثر ۲۰ مگابایت باشد.',
    ]
    as $legacyUiMarker
) {
    $expect(
        !str_contains(
            (string) $form,
            $legacyUiMarker
        ),
        'Legacy attachment UI policy remains: '
            . $legacyUiMarker
    );
}

/**
 * attachment-content-deduplication-contract-test-v1
 */
$deduplicationContracts = [
    [
        $repository,
        'attachment-content-deduplication-v1',
    ],
    [
        $repository,
        "f.sha256_checksum = ?",
    ],
    [
        $repository,
        "'duplicate_attachment'",
    ],
    [
        $repository,
        "'invalid_attachment_checksum'",
    ],
    [
        $repository,
        "f.status = 'active'",
    ],
    [
        $service,
        "'sha256_checksum' =>",
    ],
    [
        $service,
        "hash_file(",
    ],
    [
        $service,
        "'duplicate_attachment'",
    ],
    [
        $service,
        "'invalid_attachment_checksum'",
    ],
    [
        $service,
        '@unlink($path)',
    ],
    [
        $detail,
        "attachmentStatus==='duplicate_attachment'",
    ],
    [
        $detail,
        'این فایل قبلاً به همین مکاتبه پیوست شده است.',
    ],
    [
        $detail,
        "attachmentStatus==='invalid_attachment_checksum'",
    ],
];

foreach (
    $deduplicationContracts
    as [$content, $marker]
) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $marker
        ),
        'Missing attachment deduplication marker: '
            . $marker
    );
}

$expect(
    substr_count(
        (string) $repository,
        'attachment-content-deduplication-v1'
    ) === 1,
    'Attachment deduplication guard must occur exactly once.'
);

$expect(
    substr_count(
        (string) $detail,
        "attachmentStatus==='duplicate_attachment'"
    ) === 1,
    'Duplicate attachment must have exactly one detail message.'
);

/*
 * attachment-lifecycle-foundation-contract-test-v1
 */
$lifecyclePolicySource = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/'
    . 'CorrespondenceAttachmentLifecyclePolicy.php'
);

$lifecycleRepositorySource = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/'
    . 'CorrespondenceAttachmentRepository.php'
);

$lifecycleEnvironmentSource = file_get_contents(
    $root
    . '/public_html/.env.example'
);

foreach (
    [
        [
            $lifecyclePolicySource,
            'attachment-lifecycle-policy-v1',
        ],
        [
            $lifecyclePolicySource,
            'AUTOMATION_ATTACHMENT_RETENTION_DAYS',
        ],
        [
            $lifecyclePolicySource,
            'AUTOMATION_ATTACHMENT_PURGE_BATCH_SIZE',
        ],
        [
            $lifecycleRepositorySource,
            'attachment-lifecycle-repository-v1',
        ],
        [
            $lifecycleRepositorySource,
            'public function purgeCandidates(',
        ],
        [
            $lifecycleRepositorySource,
            "WHERE status = 'inactive'",
        ],
        [
            $lifecycleRepositorySource,
            "status = 'purged'",
        ],
        [
            $lifecycleRepositorySource,
            'public function markPurged(',
        ],
        [
            $lifecycleEnvironmentSource,
            'AUTOMATION_ATTACHMENT_RETENTION_DAYS=30',
        ],
        [
            $lifecycleEnvironmentSource,
            'AUTOMATION_ATTACHMENT_PURGE_BATCH_SIZE=100',
        ],
    ]
    as [$content, $marker]
) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $marker
        ),
        'Missing attachment lifecycle marker: '
            . $marker
    );
}

$expect(
    !str_contains(
        (string) $lifecycleRepositorySource,
        'DELETE FROM private_files'
    ),
    'Lifecycle processing must preserve private-file records.'
);

/*
 * attachment-lifecycle-purge-contract-test-v1
 */
$lifecycleServiceSourceV1 = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/Correspondence/'
    . 'CorrespondenceAttachmentLifecycleService.php'
);

$lifecycleCliSourceV1 = file_get_contents(
    $root
    . '/public_html/scripts/'
    . 'purge-inactive-correspondence-attachments.php'
);

foreach (
    [
        [
            $lifecycleServiceSourceV1,
            'attachment-lifecycle-purge-service-v1',
        ],
        [
            $lifecycleServiceSourceV1,
            'attachment-lifecycle-missing-file-safety-v1',
        ],
        [
            $lifecycleServiceSourceV1,
            'public function run(',
        ],
        [
            $lifecycleServiceSourceV1,
            'realpath($configured)',
        ],
        [
            $lifecycleServiceSourceV1,
            'DIRECTORY_SEPARATOR',
        ],
        [
            $lifecycleServiceSourceV1,
            '@unlink(',
        ],
        [
            $lifecycleServiceSourceV1,
            '->markPurged(',
        ],
        [
            $lifecycleCliSourceV1,
            'attachment-lifecycle-purge-cli-v1',
        ],
        [
            $lifecycleCliSourceV1,
            "PHP_SAPI !== 'cli'",
        ],
        [
            $lifecycleCliSourceV1,
            "'execute'",
        ],
        [
            $lifecycleCliSourceV1,
            'PURGE-INACTIVE-CORRESPONDENCE-ATTACHMENTS',
        ],
    ]
    as [$content, $marker]
) {
    $expect(
        is_string($content)
        && str_contains(
            $content,
            $marker
        ),
        'Missing lifecycle purge marker: '
            . $marker
    );
}

$expect(
    !str_contains(
        (string) $lifecycleServiceSourceV1,
        'original_filename'
    ),
    'Lifecycle purge output must not expose original filenames.'
);

$expect(
    !str_contains(
        (string) $lifecycleCliSourceV1,
        'storage_key'
    ),
    'Lifecycle CLI must not print storage keys.'
);

$expect(
    !str_contains(
        (string) $lifecycleCliSourceV1,
        'public_reference'
    ),
    'Lifecycle CLI must not print public references.'
);
echo
    "Automation correspondence attachment workflow test passed.\n";