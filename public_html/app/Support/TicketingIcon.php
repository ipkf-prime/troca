<?php

declare(strict_types=1);

namespace App\Support;

final class TicketingIcon
{
    public static function svg(
        string $name
    ): string {
        $path =
            match ($name) {

                'view' =>
                    '<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>'
                    . '<circle cx="12" cy="12" r="2.75"/>',

                'takeover' =>
                    '<path d="M12 3v10"/>'
                    . '<path d="m8 9 4 4 4-4"/>'
                    . '<path d="M5 17v2h14v-2"/>',

                'transfer' =>
                    '<path d="M7 7h11"/>'
                    . '<path d="m15 4 3 3-3 3"/>'
                    . '<path d="M17 17H6"/>'
                    . '<path d="m9 14-3 3 3 3"/>',

                'escalate' =>
                    '<path d="M6 18 18 6"/>'
                    . '<path d="M10 6h8v8"/>',

                'search' =>
                    '<circle cx="10.5" cy="10.5" r="5.5"/>'
                    . '<path d="m15 15 4.5 4.5"/>',

                'reset' =>
                    '<path d="M5 8a7 7 0 1 1-1 7"/>'
                    . '<path d="M5 3v5h5"/>',

                'confirm' =>
                    '<path d="m5 12.5 4.25 4.25L19 7"/>',

                'more' =>
                    '<circle cx="5" cy="12" r="1"/>'
                    . '<circle cx="12" cy="12" r="1"/>'
                    . '<circle cx="19" cy="12" r="1"/>',

                default =>
                    '<circle cx="12" cy="12" r="8"/>',
            };


        return
            '<svg'
            . ' class="ticketing-action-icon"'
            . ' viewBox="0 0 24 24"'
            . ' width="20"'
            . ' height="20"'
            . ' fill="none"'
            . ' stroke="currentColor"'
            . ' stroke-width="1.8"'
            . ' stroke-linecap="round"'
            . ' stroke-linejoin="round"'
            . ' aria-hidden="true"'
            . ' focusable="false"'
            . '>'
            . $path
            . '</svg>';
    }
}
