<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        throw new RuntimeException('Unable to read ' . $path);
    }

    return $content;
};

$service = $read(
    'public_html/app/Services/ProfileAvatarService.php'
);
$repository = $read(
    'public_html/app/Repositories/ProfileAvatarRepository.php'
);
$routes = $read(
    'public_html/routes/profile-avatar.php'
);
$profile = $read(
    'public_html/resources/views/admin/profile.php'
);
$layout = $read(
    'public_html/resources/views/admin/layout.php'
);
$routeLoader = $read(
    'public_html/system/Routing/RouteLoader.php'
);

$expect(
    str_contains($service, '2_097_152')
    && str_contains($service, "'image/webp' => 'webp'")
    && str_contains($service, 'is_uploaded_file')
    && str_contains($service, 'getimagesize'),
    'Secure avatar validation is incomplete.'
);
$expect(
    str_contains($repository, 'persons.avatar')
    && str_contains($repository, 'UPDATE persons'),
    'Avatar persistence is missing.'
);
$expect(
    str_contains($routes, '/admin/profile/avatar')
    && str_contains($routes, '/admin/profile/avatar/remove'),
    'Avatar routes are missing.'
);
$expect(
    str_contains($profile, 'multipart/form-data')
    && str_contains($profile, 'data-avatar-input'),
    'Avatar profile UI is missing.'
);
$expect(
    str_contains($layout, 'ProfileAvatarService')
    && str_contains($layout, '$profileAvatarUrl'),
    'Header avatar resolution is missing.'
);
$expect(
    str_contains($routeLoader, '/routes/profile-avatar.php'),
    'Avatar route file is not loaded.'
);

echo "Profile avatar R7 checks passed.\n";
