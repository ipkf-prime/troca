<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$routes = file_get_contents(
    $root . '/public_html/routes/web.php'
);

$rbac = file_get_contents(
    $root
    . '/public_html/app/Services/'
    . 'AdminNavigationRbacService.php'
);

$repository = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/'
    . 'Correspondence/CorrespondenceRepository.php'
);

$viewModel = file_get_contents(
    $root
    . '/public_html/app/Services/Automation/'
    . 'Correspondence/CorrespondenceViewModelBuilder.php'
);

$view = file_get_contents(
    $root
    . '/public_html/resources/views/admin/'
    . 'automation-correspondence-detail.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(
            STDERR,
            "FAIL: {$message}\n"
        );

        exit(1);
    }
};

foreach (
    [
        'routes' => $routes,
        'rbac' => $rbac,
        'repository' => $repository,
        'view-model' => $viewModel,
        'view' => $view,
    ]
    as $name => $content
) {
    $expect(
        is_string($content),
        "{$name} source must be readable."
    );
}

$route =
    '/admin/automation/correspondences/'
    . '{public_reference}/register';

$expect(
    str_contains(
        $routes,
        $route
    ),
    'Official registration route is missing.'
);

$expect(
    str_contains(
        $rbac,
        "'{$route}' => "
        . "'automation.correspondence.register'"
    ),
    'Registration route must require register permission.'
);

$expect(
    str_contains(
        $routes,
        'CorrespondenceRegistrationService'
    )
    && str_contains(
        $routes,
        'registration_status'
    )
    && str_contains(
        $routes,
        '\\IPKF\\Security\\Csrf'
    ),
    'Registration route must invoke service, expose status, and enforce CSRF.'
);

$expect(
    str_contains(
        $repository,
        'official_registration_number'
    )
    && str_contains(
        $repository,
        'official_registered_at'
    )
    && str_contains(
        $repository,
        'correspondence_registrations'
    ),
    'Detail query must load active official registration.'
);

$expect(
    str_contains(
        $viewModel,
        "'official_number'"
    )
    && str_contains(
        $viewModel,
        "'official_registered_at'"
    )
    && str_contains(
        $viewModel,
        'AdminFormat::digits'
    ),
    'Official number/date must be presentation formatted.'
);

$expect(
    str_contains(
        $view,
        'ثبت رسمی در دبیرخانه'
    )
    && str_contains(
        $view,
        'شماره ثبت رسمی'
    )
    && str_contains(
        $view,
        'تاریخ ثبت رسمی'
    ),
    'Detail UI must expose official registration action and fields.'
);

echo "Automation official registration UI checks passed.\n";
