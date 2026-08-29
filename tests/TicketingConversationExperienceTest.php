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
                $relative
            );
        }

        return $content;
    };

$repository =
    $read(
        'public_html/app/Repositories/'
        . 'TicketRepository.php'
    );

$service =
    $read(
        'public_html/app/Services/Ticketing/'
        . 'TicketService.php'
    );

$view =
    $read(
        'public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );

$css =
    $read(
        'public_html/public/assets/admin/css/'
        . 'ticketing.css'
    );

$assert =
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

$assert(
    str_contains(
        $repository,
        'public function attachments('
    ),
    'repository attachments missing'
);

$assert(
    substr_count(
        $service,
        '->attachments('
    ) >= 2,
    'detail attachment calls missing'
);

foreach ([
    'data-ticketing-message-body',
    'data-ticketing-message-toggle',
    'مشاهده بیشتر',
    'مشاهده کمتر',
    'data-ticketing-attachment',
] as $marker) {
    $assert(
        str_contains(
            $view,
            $marker
        ),
        $marker
    );
}

$assert(
    !str_contains(
        $view,
        'storage_key'
    ),
    'storage key leaked'
);

$assert(
    str_contains(
        $css,
        '-webkit-line-clamp: 6'
    ),
    'line clamp missing'
);

echo
    "TICKETING_CONVERSATION_EXPERIENCE_PASS\n";


/*
 * A8D5-R6:
 * Message height must be evaluated after the hidden
 * conversation panel becomes visible.
 */
$viewR6 =
    file_get_contents(
        dirname(__DIR__)
        . '/public_html/resources/views/admin/'
        . 'ticketing-ticket-detail.php'
    );

if (
    !is_string($viewR6)
    ||
    !str_contains(
        $viewR6,
        'new MutationObserver('
    )
    ||
    !str_contains(
        $viewR6,
        'conversationPanel.hidden'
    )
    ||
    !str_contains(
        $viewR6,
        "attributeFilter: [\n                    'hidden',"
    )
    ||
    !str_contains(
        $viewR6,
        'scheduleInitialization'
    )
) {
    throw new RuntimeException(
        'Visible conversation-tab collapse contract failed.'
    );
}

echo
    "TICKETING_CONVERSATION_VISIBLE_TAB_COLLAPSE_PASS\n";
