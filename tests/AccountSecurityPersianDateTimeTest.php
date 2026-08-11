<?php

$root = dirname(__DIR__);

$read = static fn (string $path): string =>
    file_get_contents($root . '/' . $path);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$security = $read(
    'public_html/app/Services/AccountSecurityService.php'
);

$history = $read(
    'public_html/app/Services/LoginHistoryService.php'
);

$expect(
    str_contains($security, 'AdminFormat::jalaliDateTime')
    && !str_contains($security, 'Clock::formatDateTime'),
    'Current session login time is not Tehran/Jalali formatted.'
);

$expect(
    str_contains($history, 'AdminFormat::jalaliDateTime')
    && !str_contains($history, 'Clock::formatDateTime'),
    'Login history timestamps are not Tehran/Jalali formatted.'
);

$expect(
    str_contains(
        $security,
        "'login_at' => \$this->displayDateTime("
    ),
    'Current session still exposes raw auth_login_at.'
);

$expect(
    str_contains(
        $history,
        "'logged_in_at' => \$this->displayDateTime("
    ),
    'Login history still exposes raw logged_in_at.'
);

echo "Account security Persian datetime checks passed.\n";
