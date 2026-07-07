<?php

if (!function_exists('url')) {

    function url(string $path = ''): string
    {
        return rtrim($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'], '/') . '/' . ltrim($path, '/');
    }
}