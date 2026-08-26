<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$web =
    file_get_contents(
        $root
        . '/public_html/routes/web.php'
    );

$urls =
    file_get_contents(
        $root
        . '/public_html/system/Support/'
        . 'ApplicationUrlRegistry.php'
    );

$sso =
    file_get_contents(
        $root
        . '/public_html/app/Services/'
        . 'ModuleSsoService.php'
    );

$expect =
    static function (
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new RuntimeException(
                $message
            );
        }
    };


foreach ([
    'applicationModuleKeyForHost(',
    'isApplicationModuleHost(',
    'allActive()',
] as $needle) {
    $expect(
        str_contains(
            $urls,
            $needle
        ),
        'Generic module host lookup missing: '
        . $needle
    );
}


foreach ([
    'isApplicationModuleHost(',
    "\$_SERVER['REQUEST_URI']",
    'rawurlencode(',
    '->redirect($safePath)',
] as $needle) {
    $expect(
        str_contains(
            $web,
            $needle
        ),
        'Generic SSO resume contract missing: '
        . $needle
    );
}


$expect(
    !str_contains(
        $web,
        "str_starts_with(\$safePath, '/admin/work')"
    ),
    'SSO callback still hardcodes Work/Automation.'
);


foreach ([
    'remember(',
    'pendingResumeUrl(',
    'resumeFor(',
    "isset(\$parsed['query'])",
    ". \$parsed['query']",
] as $needle) {
    $expect(
        str_contains(
            $sso,
            $needle
        ),
        'Module SSO return-path contract missing: '
        . $needle
    );
}


echo "MODULE_SSO_SESSION_RESUME_PASS\n";
