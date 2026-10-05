<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$auth = file_get_contents(
    $root . '/public_html/app/Services/AuthService.php'
);
$repo = file_get_contents(
    $root . '/public_html/app/Repositories/UserRepository.php'
);

$expect = static function (bool $ok, string $message): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$expect(
    is_string($auth) && is_string($repo),
    'auth/repository source unavailable'
);

$expect(
    str_contains($auth, 'AUTH_LOGIN_LOCKOUT_A5_R2')
    && str_contains($auth, 'AUTH_LOGIN_MAX_FAILURES')
    && str_contains($auth, 'AUTH_LOGIN_LOCK_MINUTES'),
    'dynamic lockout policy missing'
);

$expect(
    str_contains($auth, 'normalizeExpiredLoginLock')
    && str_contains($auth, 'resetLoginFailures'),
    'expired lock normalization missing'
);

$expect(
    str_contains($auth, '$policy[\'max_attempts\']')
    && str_contains($auth, '$policy[\'lock_minutes\']'),
    'auth failure path does not pass policy to repository'
);

$expect(
    str_contains($repo, 'AUTH_LOGIN_LOCKOUT_A5_R2')
    && str_contains($repo, 'COALESCE(failed_login_attempts, 0) + 1')
    && str_contains($repo, 'locked_until =')
    && str_contains($repo, 'DATE_ADD('),
    'atomic lockout persistence missing'
);

$expect(
    str_contains($repo, 'max(1, min(20, $maxAttempts))')
    && str_contains($repo, 'max(1, min(1440, $lockMinutes))'),
    'lockout bounds missing'
);

echo "AUTH_LOGIN_LOCKOUT_POLICY_CONTRACT=PASS\n";

$methodStart = strpos(
    $repo,
    'public function updateLoginFailure('
);

$methodEnd = $methodStart === false
    ? false
    : strpos(
        $repo,
        'public function passwordHashForUser(',
        $methodStart
    );

$expect(
    $methodStart !== false
    && $methodEnd !== false,
    'updateLoginFailure method boundaries missing'
);

$methodBlock = substr(
    $repo,
    $methodStart,
    $methodEnd - $methodStart
);

$lockAssignment = strpos(
    $methodBlock,
    'locked_until ='
);

$failureAssignment = strpos(
    $methodBlock,
    'failed_login_attempts ='
);

$expect(
    $lockAssignment !== false
    && $failureAssignment !== false
    && $lockAssignment < $failureAssignment,
    'MySQL lock decision must be assigned before failure increment'
);

echo "MYSQL_LOCKOUT_ASSIGNMENT_ORDER=PASS\n";
