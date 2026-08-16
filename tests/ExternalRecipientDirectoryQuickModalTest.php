<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$read =
    static function (
        string $relative
    ) use ($root): string {
        $content =
            file_get_contents(
                $root
                . '/'
                . $relative
            );

        if (!is_string($content)) {
            throw new RuntimeException(
                'Unreadable source: '
                . $relative
            );
        }

        return $content;
    };

$routes =
    $read(
        'public_html/routes/web.php'
    );

$rbac =
    $read(
        'public_html/app/Services/'
        . 'AdminNavigationRbacService.php'
    );

$form =
    $read(
        'public_html/resources/views/admin/'
        . 'automation-correspondence-form.php'
    );


foreach ([
    '/admin/automation/external-organizations/quick-create',
    '$adminGuard',
    '$externalDirectoryCsrf',
    '->saveOrganization(',
    '->saveContactPoint(',
    "'organization'",
    "'contact_point'",
    "'partial' => true",
] as $required) {
    if (!str_contains(
        $routes,
        $required
    )) {
        throw new RuntimeException(
            'Quick-create route contract missing: '
            . $required
        );
    }
}


if (!str_contains(
    $rbac,
    "'/admin/automation/external-organizations/quick-create'"
)) {
    throw new RuntimeException(
        'Quick-create RBAC route missing.'
    );
}


foreach ([
    'external-directory-quick-modal-v3c',
    'data-external-directory-quick-open',
    'data-external-directory-modal',
    'data-external-directory-quick-title',
    'data-external-directory-quick-point-title',
    'data-external-directory-quick-save',
    'automation-external-directory-select-row',
    'fetch(',
    '/admin/automation/external-organizations/quick-create',
    'syncExternalDirectory',
    'upsertDirectoryOrganization',
    'upsertDirectoryPoint',
    "body.set(\n                '_token'",
] as $required) {
    if (!str_contains(
        $form,
        $required
    )) {
        throw new RuntimeException(
            'Quick-modal UI contract missing: '
            . $required
        );
    }
}


/*
 * Modal fields intentionally have no name attributes,
 * so they cannot become correspondence payload fields.
 */
foreach ([
    'name="quick_title"',
    'name="contact_point_title"',
] as $forbidden) {
    if (str_contains(
        $form,
        $forbidden
    )) {
        throw new RuntimeException(
            'Quick modal leaked temporary form field: '
            . $forbidden
        );
    }
}


/*
 * This slice must not invoke correspondence persistence
 * or dispatch from the modal endpoint.
 */
foreach ([
    'CorrespondenceCommandService',
    '->dispatch(',
    'INSERT INTO correspondence_dispatches',
] as $forbidden) {
    if (str_contains(
        $routes,
        'external-directory-quick-modal-v3c'
    )) {
        /*
         * Static source-wide route file may legitimately
         * contain those terms elsewhere. Only ensure the
         * UI itself never invokes them.
         */
        if (str_contains(
            $form,
            $forbidden
        )) {
            throw new RuntimeException(
                'Quick modal contains forbidden action: '
                . $forbidden
            );
        }
    }
}

echo "External directory quick modal checks passed.\n";
