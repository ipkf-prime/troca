<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);


$catalog =
    json_decode(
        (string) file_get_contents(
            $root
            . '/public_html/resources/ui-content/platform-guides.json'
        ),
        true
    );


if (!is_array($catalog)) {
    throw new RuntimeException(
        'Catalog invalid.'
    );
}


$guideCount = 0;
$bodyOnly = 0;
$titleBody = 0;


foreach ($catalog as $item) {

    if (
        !is_array($item)
        ||
        (
            $item[
                'content_type'
            ]
            ?? null
        )
        !== 'guide'
    ) {
        continue;
    }


    $guideCount++;


    $mode =
        (string) (
            $item[
                'render_mode'
            ]
            ?? ''
        );


    if ($mode === 'body') {

        $bodyOnly++;


        if (
            trim(
                (string) (
                    $item['body']
                    ?? ''
                )
            )
            === ''
        ) {
            throw new RuntimeException(
                'Empty body-only guide: '
                . (
                    $item['key']
                    ?? ''
                )
            );
        }


        if (
            array_key_exists(
                'legacy_body',
                $item
            )
        ) {
            throw new RuntimeException(
                'Body-only guide unexpectedly has legacy_body: '
                . (
                    $item['key']
                    ?? ''
                )
            );
        }


        continue;
    }


    if ($mode !== 'title_body') {
        throw new RuntimeException(
            'Invalid render mode: '
            . (
                $item['key']
                ?? ''
            )
        );
    }


    $titleBody++;


    foreach (
        [
            'title',
            'body',
            'legacy_body',
        ]
        as $field
    ) {

        if (
            trim(
                (string) (
                    $item[$field]
                    ?? ''
                )
            )
            === ''
        ) {
            throw new RuntimeException(
                'Missing '
                . $field
                . ': '
                . (
                    $item['key']
                    ?? ''
                )
            );
        }
    }
}


if ($guideCount !== 47) {
    throw new RuntimeException(
        'Guide count mismatch.'
    );
}


if ($bodyOnly !== 25) {
    throw new RuntimeException(
        'Body-only count mismatch.'
    );
}


if ($titleBody !== 22) {
    throw new RuntimeException(
        'Title-body count mismatch.'
    );
}


$seed =
    (string) file_get_contents(
        $root
        . '/public_html/system/Database/Migrations/SeedPlatformGuideCatalog.php'
    );


foreach (
    [
        'itemTitle(',
        "'render_mode'",
    ]
    as $marker
) {

    if (
        !str_contains(
            $seed,
            $marker
        )
    ) {
        throw new RuntimeException(
            'Seed marker missing: '
            . $marker
        );
    }
}


$registry =
    (string) file_get_contents(
        $root
        . '/public_html/system/Database/Application/ApplicationMigrationRegistry.php'
    );


if (
    !str_contains(
        $registry,
        'NormalizeStaticGuideRenderContract::class'
    )
) {
    throw new RuntimeException(
        'Normalization migration not registered.'
    );
}


if (
    !is_file(
        $root
        . '/public_html/system/Database/Migrations/NormalizeStaticGuideRenderContract.php'
    )
) {
    throw new RuntimeException(
        'Normalization migration missing.'
    );
}


echo "STATIC_GUIDE_RENDER_CONTRACT=PASS\n";
echo "STATIC_GUIDES=47\n";
echo "BODY_ONLY=25\n";
echo "TITLE_BODY=22\n";
echo "CONSUMER_BOUND=NO_NOT_YET\n";
