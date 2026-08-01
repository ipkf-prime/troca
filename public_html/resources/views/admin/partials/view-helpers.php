<?php

if (!function_exists('admin_fa')) {
    function admin_fa(string $entities): string
    {
        return html_entity_decode(
            $entities,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    }
}

if (!function_exists('admin_h')) {
    function admin_h($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
            false
        );
    }
}

if (!function_exists('admin_nav_is_active')) {
    function admin_nav_is_active(
        array $item,
        string $currentPath
    ): bool {
        $paths = $item['active_paths']
            ?? [$item['url'] ?? '#'];

        foreach ($paths as $path) {
            if ($currentPath === (string) $path) {
                return true;
            }

            if (
                str_ends_with((string) $path, '/*')
                && str_starts_with(
                    $currentPath,
                    rtrim((string) $path, '/*') . '/'
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
