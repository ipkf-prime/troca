<?php

declare(strict_types=1);


$root =
    dirname(__DIR__);


require_once
    __DIR__
    . '/Support/PlatformFoundationDebtScanner.php';


$viewRoot =
    $root
    . '/public_html/resources/views';


$baselinePath =
    __DIR__
    . '/fixtures/platform-foundation-debt-baseline.json';


$fail =
    static function (
        string $message
    ): never {
        fwrite(
            STDERR,
            $message
            . PHP_EOL
        );

        exit(1);
    };


$assert =
    static function (
        bool $condition,
        string $message
    ) use ($fail): void {
        if (!$condition) {
            $fail(
                $message
            );
        }
    };


/*
 * Scanner self-proof:
 * a genuinely Foundation-compliant new page must have zero debt.
 */
$compliant =
<<<'HTML'
<!doctype html>
<html lang="fa-IR" dir="rtl">
<body>
<form>
<input type="hidden" name="_token" value="x">
<input type="text" class="ui-input" name="title">
<input type="checkbox" class="ui-checkbox" name="enabled">
<select class="ui-select" name="kind">
<option value="a">الف</option>
</select>
<textarea class="ui-textarea" name="description"></textarea>
<button type="submit" class="ui-button">ذخیره</button>
</form>
</body>
</html>
HTML;


$compliantCounts =
    PlatformFoundationDebtScanner::scanText(
        $compliant
    );


$assert(
    array_sum(
        $compliantCounts
    ) === 0,
    'Foundation-compliant new page was incorrectly classified as debt: '
    . json_encode(
        $compliantCounts,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    )
);


/*
 * Scanner self-proof:
 * legacy/raw UI must be detected.
 */
$invalid =
<<<'HTML'
<html>
<body>
<input type="text" name="title">
<button
    type="button"
    style="width: 100px; color: #fff"
    onclick="run()"
>ثبت</button>
</body>
</html>
HTML;


$invalidCounts =
    PlatformFoundationDebtScanner::scanText(
        $invalid
    );


$assert(
    ($invalidCounts['legacy_control'] ?? 0) >= 2,
    'Legacy-control detection self-proof failed.'
);

$assert(
    ($invalidCounts['html_locale_missing'] ?? 0) === 1,
    'HTML locale detection self-proof failed.'
);

$assert(
    ($invalidCounts['inline_style_attribute'] ?? 0) >= 1,
    'Inline-style detection self-proof failed.'
);

$assert(
    ($invalidCounts['inline_event_handler'] ?? 0) >= 1,
    'Inline-event detection self-proof failed.'
);


/*
 * Load committed legacy baseline.
 */
$baseline =
    json_decode(
        (string) file_get_contents(
            $baselinePath
        ),
        true
    );


$assert(
    is_array(
        $baseline
    ),
    'Invalid Foundation debt baseline.'
);

$assert(
    ($baseline['schema_version'] ?? null)
        === PlatformFoundationDebtScanner::SCHEMA_VERSION,
    'Foundation debt baseline schema mismatch.'
);

$assert(
    ($baseline['policy'] ?? null)
        === 'ratchet',
    'Foundation debt baseline policy must be ratchet.'
);


$allowedFiles =
    is_array(
        $baseline['files']
        ?? null
    )
        ? $baseline['files']
        : [];


$failures = [];

$currentFiles = 0;

$newFilesWithDebt = [];


$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $viewRoot,
            FilesystemIterator::SKIP_DOTS
        )
    );


foreach (
    $iterator
    as $file
) {
    if (
        !$file->isFile()
        ||
        strtolower(
            $file->getExtension()
        ) !== 'php'
    ) {
        continue;
    }


    $currentFiles++;


    $text =
        file_get_contents(
            $file->getPathname()
        );


    if (!is_string($text)) {
        continue;
    }


    $relative =
        str_replace(
            '\\',
            '/',
            substr(
                $file->getPathname(),
                strlen($root) + 1
            )
        );


    $counts =
        PlatformFoundationDebtScanner::scanText(
            $text
        );


    $isBaselineFile =
        array_key_exists(
            $relative,
            $allowedFiles
        );


    $allowed =
        $isBaselineFile
        && is_array(
            $allowedFiles[$relative]
            ?? null
        )
            ? $allowedFiles[$relative]
            : [];


    foreach (
        PlatformFoundationDebtScanner::categories()
        as $category
    ) {
        $current =
            (int) (
                $counts[$category]
                ?? 0
            );

        $maximum =
            (int) (
                $allowed[$category]
                ?? 0
            );


        if (
            $current
            > $maximum
        ) {
            if (!$isBaselineFile) {
                $newFilesWithDebt[$relative] =
                    true;
            }


            $failures[] =
                $relative
                . ': '
                . $category
                . ' current='
                . $current
                . ' baseline='
                . $maximum;
        }
    }
}


if ($failures !== []) {
    fwrite(
        STDERR,
        "PLATFORM FOUNDATION DEBT RATCHET FAILED"
        . PHP_EOL
    );


    foreach (
        $failures
        as $failure
    ) {
        fwrite(
            STDERR,
            $failure
            . PHP_EOL
        );
    }


    exit(1);
}


echo
    'FOUNDATION_VIEW_FILE_COUNT='
    . $currentFiles
    . PHP_EOL;

echo
    'NEW_FILE_DEBT_FAILURE_COUNT='
    . count(
        $newFilesWithDebt
    )
    . PHP_EOL;

echo
    "PLATFORM_FOUNDATION_DEBT_GUARD_PASS"
    . PHP_EOL;
