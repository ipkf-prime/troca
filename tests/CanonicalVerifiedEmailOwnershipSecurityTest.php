<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$file =
    $root
    . '/public_html/app/Repositories/UserRepository.php';

if (!is_file($file)) {
    throw new RuntimeException(
        'UserRepository.php missing'
    );
}

$source =
    (string) file_get_contents(
        $file
    );

$extract =
    static function (
        string $source,
        string $start,
        ?string $end = null
    ): string {
        $startPos =
            strpos(
                $source,
                $start
            );

        if ($startPos === false) {
            throw new RuntimeException(
                'method start missing: '
                . $start
            );
        }

        if ($end === null) {
            return substr(
                $source,
                $startPos
            );
        }

        $endPos =
            strpos(
                $source,
                $end,
                $startPos
            );

        if ($endPos === false) {
            throw new RuntimeException(
                'method end missing: '
                . $end
            );
        }

        return substr(
            $source,
            $startPos,
            $endPos - $startPos
        );
    };

$login =
    $extract(
        $source,
        'public function findByLoginIdentifier',
        'public function resetLoginFailures'
    );

$verified =
    $extract(
        $source,
        'public function verifiedEmailExists',
        'public function applyIdentityChange'
    );

$bind =
    $extract(
        $source,
        'private function bindLoginIdentifier'
    );

if (
    substr_count(
        $login,
        'users.email_verified_at IS NOT NULL'
    ) !== 2
) {
    throw new RuntimeException(
        'canonical verified email login count invalid'
    );
}

$requiredLogin = [
    'users.email_norm = :email_norm_user',
    'LOWER(users.email) = :email_user',
];

foreach ($requiredLogin as $needle) {
    if (
        strpos(
            $login,
            $needle
        ) === false
    ) {
        throw new RuntimeException(
            'canonical login clause missing: '
            . $needle
        );
    }
}

$forbiddenLogin = [
    'persons.email_norm = :email_norm_person',
    'LOWER(persons.email) = :email_person',
];

foreach ($forbiddenLogin as $needle) {
    if (
        strpos(
            $login,
            $needle
        ) !== false
    ) {
        throw new RuntimeException(
            'stale person login mirror remains: '
            . $needle
        );
    }
}

$requiredVerified = [
    'users.email_verified_at',
    'users.email_norm = ?',
    'LOWER(users.email) = ?',
];

foreach ($requiredVerified as $needle) {
    if (
        strpos(
            $verified,
            $needle
        ) === false
    ) {
        throw new RuntimeException(
            'canonical verified ownership clause missing: '
            . $needle
        );
    }
}

$forbiddenVerified = [
    'persons.email_norm',
    'LOWER(persons.email)',
    'LEFT JOIN persons',
];

foreach ($forbiddenVerified as $needle) {
    if (
        strpos(
            $verified,
            $needle
        ) !== false
    ) {
        throw new RuntimeException(
            'stale person verified ownership remains: '
            . $needle
        );
    }
}

foreach (
    [
        ':email_norm_person',
        ':email_person',
    ]
    as $needle
) {
    if (
        strpos(
            $bind,
            $needle
        ) !== false
    ) {
        throw new RuntimeException(
            'stale person email binding remains: '
            . $needle
        );
    }
}

echo "CANONICAL_EMAIL_LOGIN_USERS_ONLY=PASS\n";
echo "CANONICAL_VERIFIED_EMAIL_OWNERSHIP_USERS_ONLY=PASS\n";
echo "STALE_PERSON_EMAIL_LOGIN_CONTRACT=DENY\n";
echo "STALE_PERSON_EMAIL_VERIFIED_OWNERSHIP_CONTRACT=DENY\n";
echo "CANONICAL_VERIFIED_EMAIL_SECURITY_CONTRACT=PASS\n";
