<?php

namespace App\Support;

class AdminIcon
{
    private const MAP = [
        'dashboard' => 'gauge-high',
        'users' => 'users',
        'user-group' => 'user-group',
        'user-shield' => 'user-shield',
        'organization' => 'sitemap',
        'sitemap' => 'sitemap',
        'building' => 'building',
        'id-badge' => 'id-badge',
        'system' => 'gears',
        'gears' => 'gears',
        'palette' => 'palette',
        'sliders' => 'sliders',
        'file-lines' => 'file-lines',
        'reports' => 'chart-column',
        'chart-column' => 'chart-column',
        'support' => 'headset',
        'headset' => 'headset',
    ];

    private const SVG_PATHS = [
        'gauge-high' => [
            '<path d="M4 14a8 8 0 1 1 16 0" />',
            '<path d="M12 14l4-5" />',
            '<path d="M8 18h8" />',
        ],
        'users' => [
            '<path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />',
            '<path d="M17 11a3 3 0 1 0 0-6" />',
            '<path d="M3 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2" />',
            '<path d="M16 14.5a4.5 4.5 0 0 1 5 4.5v2" />',
        ],
        'user-group' => [
            '<path d="M8 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />',
            '<path d="M16 10a3.5 3.5 0 1 0 0-7" />',
            '<path d="M3 21v-2.5A4.5 4.5 0 0 1 7.5 14h1A4.5 4.5 0 0 1 13 18.5V21" />',
            '<path d="M14 14h1.5A4.5 4.5 0 0 1 20 18.5V21" />',
        ],
        'user-shield' => [
            '<path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />',
            '<path d="M3 21v-2a5 5 0 0 1 5-5h2" />',
            '<path d="M18 14l3 1v2.5c0 2-1.2 3.8-3 4.5-1.8-.7-3-2.5-3-4.5V15l3-1Z" />',
        ],
        'sitemap' => [
            '<path d="M9 4h6v5H9z" />',
            '<path d="M4 15h6v5H4z" />',
            '<path d="M14 15h6v5h-6z" />',
            '<path d="M12 9v3" />',
            '<path d="M7 12h10" />',
            '<path d="M7 12v3" />',
            '<path d="M17 12v3" />',
        ],
        'building' => [
            '<path d="M5 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" />',
            '<path d="M3 21h18" />',
            '<path d="M9 7h1" />',
            '<path d="M14 7h1" />',
            '<path d="M9 11h1" />',
            '<path d="M14 11h1" />',
            '<path d="M10 21v-4h4v4" />',
        ],
        'id-badge' => [
            '<path d="M8 3h8l1 3H7l1-3Z" />',
            '<path d="M6 6h12v15H6z" />',
            '<path d="M12 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" />',
            '<path d="M9 17a3 3 0 0 1 6 0" />',
        ],
        'gears' => [
            '<path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z" />',
            '<path d="M12 2v3" />',
            '<path d="M12 19v3" />',
            '<path d="M4.9 4.9 7 7" />',
            '<path d="m17 17 2.1 2.1" />',
            '<path d="M2 12h3" />',
            '<path d="M19 12h3" />',
            '<path d="m4.9 19.1 2.1-2.1" />',
            '<path d="m17 7 2.1-2.1" />',
        ],
        'palette' => [
            '<path d="M12 3a9 9 0 0 0 0 18h1.5a2 2 0 0 0 1-3.7 1.7 1.7 0 0 1 1-3.1H18a3 3 0 0 0 3-3A8.2 8.2 0 0 0 12 3Z" />',
            '<path d="M7.5 11.5h.01" />',
            '<path d="M9.5 7.5h.01" />',
            '<path d="M14.5 7.5h.01" />',
        ],
        'sliders' => [
            '<path d="M4 6h10" />',
            '<path d="M18 6h2" />',
            '<path d="M16 4v4" />',
            '<path d="M4 12h3" />',
            '<path d="M11 12h9" />',
            '<path d="M9 10v4" />',
            '<path d="M4 18h12" />',
            '<path d="M20 18h0" />',
        ],
        'file-lines' => [
            '<path d="M7 3h7l4 4v14H7z" />',
            '<path d="M14 3v5h5" />',
            '<path d="M10 12h6" />',
            '<path d="M10 16h6" />',
        ],
        'chart-column' => [
            '<path d="M4 21h16" />',
            '<path d="M7 17V9" />',
            '<path d="M12 17V5" />',
            '<path d="M17 17v-6" />',
        ],
        'headset' => [
            '<path d="M4 13v-1a8 8 0 0 1 16 0v1" />',
            '<path d="M4 13h3v5H4z" />',
            '<path d="M17 13h3v5h-3z" />',
            '<path d="M17 20h-3" />',
        ],
    ];

    public static function html(string $name, string $class = ''): string
    {
        $icon = self::MAP[$name] ?? $name;
        $classes = trim('admin-icon admin-icon--' . $icon . ' ' . $class);
        $paths = self::SVG_PATHS[$icon] ?? self::SVG_PATHS['gauge-high'];
        $svg = '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">'
            . implode('', $paths)
            . '</svg>';

        return '<span class="' . htmlspecialchars($classes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" aria-hidden="true">'
            . $svg
            . '</span>';
    }
}
