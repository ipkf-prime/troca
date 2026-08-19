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
        'maxFiles: 3',
        'maxEach: 10 * 1024 * 1024',
        'maxTotal: 20 * 1024 * 1024',
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
        'private const MAX_FILES = 3;',
        'private const MAX_TOTAL_BYTES = 20971520;',
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
echo
    "Automation correspondence attachment workflow test passed.\n";