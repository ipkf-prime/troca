<?php

declare(strict_types=1);

$root =
    dirname(__DIR__);

$path =
    $root
    . '/public_html/resources/views/admin/'
    . 'ticketing-ticket-form.php';

$text =
    file_get_contents(
        $path
    );

if (!is_string($text)) {
    throw new RuntimeException(
        'Cannot read ticket create form.'
    );
}


$position =
    static function (
        string $needle
    ) use ($text): int {

        $value =
            strpos(
                $text,
                $needle
            );

        if ($value === false) {
            throw new RuntimeException(
                'Missing marker: '
                . $needle
            );
        }

        return $value;
    };


$formStart =
    $position('<form');

$formClose =
    $position('</form>');

$infoMarker =
    $position(
        'data-admin-tab-panel="ticket-info"'
    );

$detailMarker =
    $position(
        'data-admin-tab-panel="ticket-detail"'
    );


$infoStart =
    strrpos(
        substr(
            $text,
            0,
            $infoMarker
        ),
        '<section'
    );

$detailStart =
    strrpos(
        substr(
            $text,
            0,
            $detailMarker
        ),
        '<section'
    );


if (
    $infoStart === false
    || $detailStart === false
) {
    throw new RuntimeException(
        'Panel section start missing.'
    );
}


$infoClose =
    strpos(
        $text,
        '</section>',
        $infoMarker
    );

$detailClose =
    strpos(
        $text,
        '</section>',
        $detailMarker
    );


if (
    $infoClose === false
    || $detailClose === false
) {
    throw new RuntimeException(
        'Panel section close missing.'
    );
}


if (!(
    $formStart
    < $infoStart
    && $infoStart
        < $infoClose
    && $infoClose
        < $detailStart
    && $detailStart
        < $detailClose
    && $detailClose
        < $formClose
)) {
    throw new RuntimeException(
        'Ticket form tabs are not sibling panels inside one form.'
    );
}


$infoBlock =
    substr(
        $text,
        $infoStart,
        $infoClose
        - $infoStart
    );


$detailBlock =
    substr(
        $text,
        $detailStart,
        $detailClose
        - $detailStart
    );


if (
    str_contains(
        $infoBlock,
        'data-admin-tab-panel="ticket-detail"'
    )
) {
    throw new RuntimeException(
        'Detail tab is nested inside info tab.'
    );
}


if (!str_contains(
    $detailBlock,
    'name="body"'
)) {
    throw new RuntimeException(
        'Body is not inside detail tab.'
    );
}


if (!str_contains(
    $detailBlock,
    'name="attachments[]"'
)) {
    throw new RuntimeException(
        'Attachments are not inside detail tab.'
    );
}


if (
    substr_count(
        $text,
        '<form'
    ) !== 1
    || substr_count(
        $text,
        '</form>'
    ) !== 1
) {
    throw new RuntimeException(
        'Unexpected form tag count.'
    );
}


echo
    "TICKETING_CREATE_FORM_TAB_STRUCTURE_PASS\n";
