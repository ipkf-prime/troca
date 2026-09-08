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
                'Unreadable: '
                . $relative
            );
        }

        return $content;
    };

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

$login =
    $read(
        'public_html/resources/views/admin/login.php'
    );

$register =
    $read(
        'public_html/resources/views/site/register.php'
    );

$css =
    $read(
        'public_html/public/assets/admin/css/admin.css'
    );

$js =
    $read(
        'public_html/public/assets/admin/js/admin.js'
    );

$passwordPage =
    $read(
        'public_html/resources/views/admin/password.php'
    );

$expect(
    substr_count(
        $login,
        'data-password-visibility-toggle'
    ) === 1,
    'Login must contain exactly one password toggle.'
);

$expect(
    substr_count(
        $login,
        'data-password-visibility-input'
    ) === 1,
    'Login must contain exactly one visibility input.'
);

$expect(
    substr_count(
        $register,
        'data-password-visibility-toggle'
    ) === 2,
    'Register must contain exactly two password toggles.'
);

$expect(
    substr_count(
        $register,
        'data-password-visibility-input'
    ) === 2,
    'Register must contain exactly two visibility inputs.'
);

foreach ([
    'aria-label="نمایش کلمه عبور"',
    'aria-pressed="false"',
    'viewBox="0 0 24 24"',
] as $marker) {
    $expect(
        str_contains(
            $login,
            $marker
        ),
        'Login marker missing: '
        . $marker
    );

    $expect(
        str_contains(
            $register,
            $marker
        ),
        'Register marker missing: '
        . $marker
    );
}

foreach ([
    'PUBLIC_PASSWORD_VISIBILITY_TOGGLE_V1',
    '.admin-password-visibility',
    '.admin-password-visibility__toggle',
    'aria-pressed="true"',
    ':focus-visible',
] as $marker) {
    $expect(
        str_contains(
            $css,
            $marker
        ),
        'CSS marker missing: '
        . $marker
    );
}

foreach ([
    'PUBLIC_PASSWORD_VISIBILITY_TOGGLE_V1',
    '[data-password-visibility-toggle]',
    '[data-password-visibility-input]',
    "input.type === 'password'",
    "? 'text'",
    ": 'password'",
    "'aria-pressed'",
    "'مخفی کردن کلمه عبور'",
    "'نمایش کلمه عبور'",
] as $marker) {
    $expect(
        str_contains(
            $js,
            $marker
        ),
        'JS marker missing: '
        . $marker
    );
}

/*
 * The existing account password page keeps its
 * legacy/local toggle contract.  The new public
 * component uses a different selector and does
 * not hijack it.
 */
/*
 * REGISTER_PASSWORD_WRAPPER_SCOPE_V2
 *
 * Visibility wrappers must be scoped only to the
 * two real password fields.  The registration
 * honeypot and identity/contact fields must never
 * be captured by the wrapper.
 */
$honeypotStart =
    strpos(
        $register,
        'class="register-honeypot"'
    );

$fullNameStart =
    strpos(
        $register,
        'name="full_name"'
    );

$expect(
    $honeypotStart !== false
    && $fullNameStart !== false
    && $fullNameStart > $honeypotStart,
    'Registration honeypot/full-name structure missing.'
);

$honeypotSlice =
    substr(
        $register,
        (int) $honeypotStart,
        (int) $fullNameStart
        - (int) $honeypotStart
    );

$expect(
    !str_contains(
        $honeypotSlice,
        'data-password-visibility'
    ),
    'Password visibility wrapper leaked into registration honeypot.'
);

$expect(
    preg_match_all(
        '/^[\t ]*data-password-visibility[\t ]*$/m',
        $register
    ) === 2,
    'Register must contain exactly two visibility wrappers.'
);

$expect(
    preg_match(
        '/<span\s+class="admin-password-visibility"\s+'
        . 'data-password-visibility\s*>\s*'
        . '<input\s+name="password"\s+type="password"'
        . '.*?data-password-visibility-input\s*>'
        . '.*?data-password-visibility-toggle'
        . '.*?<\/span>/s',
        $register
    ) === 1,
    'Password visibility wrapper is not scoped to password field.'
);

$expect(
    preg_match(
        '/<span\s+class="admin-password-visibility"\s+'
        . 'data-password-visibility\s*>\s*'
        . '<input\s+name="password_confirmation"\s+type="password"'
        . '.*?data-password-visibility-input\s*>'
        . '.*?data-password-visibility-toggle'
        . '.*?<\/span>/s',
        $register
    ) === 1,
    'Password confirmation visibility wrapper is not scoped correctly.'
);

/*
 * The legacy password page contains:
 *
 * - three data-toggle-password attributes
 *   on the three password buttons;
 * - one occurrence inside its local
 *   querySelectorAll JavaScript selector.
 *
 * The total literal count is therefore four.
 */
$expect(
    substr_count(
        $passwordPage,
        'data-toggle-password'
    ) === 4,
    'Existing account password toggle literal contract changed.'
);

$expect(
    preg_match_all(
        '/^[\t ]*data-toggle-password[\t ]*$/m',
        $passwordPage
    ) === 3,
    'Existing account password button toggle contract changed.'
);

$expect(
    substr_count(
        $passwordPage,
        "document.querySelectorAll('[data-toggle-password]')"
    ) === 1,
    'Existing account password local JavaScript toggle contract changed.'
);

$expect(
    !str_contains(
        $js,
        "querySelectorAll('[data-toggle-password]')"
    ),
    'Global JS must not hijack legacy password toggles.'
);

echo
    "PUBLIC_PASSWORD_VISIBILITY_TOGGLE_PASS\n";
