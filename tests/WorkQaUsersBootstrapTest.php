<?php

$root = dirname(__DIR__);
$script = file_get_contents(
    $root . '/public_html/scripts/create-work-qa-users.php'
);

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(
    str_contains($script, "PHP_SAPI !== 'cli'"),
    'QA user bootstrap must be CLI-only.'
);

$expect(
    str_contains(
        $script,
        '--confirm=CREATE-WORK-QA-USERS'
    ),
    'Explicit execution confirmation is missing.'
);

$expect(
    substr_count($script, "'role_code' => 'qa_work_") === 5,
    'Exactly five QA roles must be defined.'
);

$expect(
    str_contains($script, 'createOrUpdateAdminFromEnv'),
    'Canonical user creation method is not used.'
);

$expect(
    str_contains($script, 'password_hash') === false,
    'Password hashing must remain inside UserRepository.'
);

$expect(
    str_contains(
        $script,
        "in_array('work.project.admin'"
    ),
    'Global Work admin permission guard is missing.'
);

$expect(
    str_contains($script, 'chmod($credentialPath, 0600)'),
    'Credential file protection is missing.'
);

$expect(
    str_contains($script, 'random_int('),
    'Cryptographically secure temporary passwords are missing.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $script
    ),
    'Destructive schema SQL is present.'
);

echo "Work QA user bootstrap checks passed.\n";
