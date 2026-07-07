<?php

namespace IPKF\Core;

class View
{
    private static string $basePath = BASE_PATH . '/resources/views/';

    public static function make(string $view, array $data = []): void
    {
        extract($data);

        $file = self::$basePath . $view . '.php';

        if (!file_exists($file)) {
            die("View not found: " . $view);
        }

        $viewPath = $view;

        require self::$basePath . 'layouts/main.php';
    }

    public static function include(string $view, array $data = []): void
    {
        $file = self::$basePath . $view . '.php';

        if (file_exists($file)) {
            extract($data);
            require $file;
        }
    }
}