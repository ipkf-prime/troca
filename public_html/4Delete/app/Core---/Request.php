<?php

namespace IPKF\Core;

class Request
{
    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
    
        $uri = parse_url($uri, PHP_URL_PATH);
    
        // حذف base folder (خیلی مهم برای هاست)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    
        if ($scriptName && strpos($uri, dirname($scriptName)) === 0) {
            $uri = substr($uri, strlen(dirname($scriptName)));
        }
    
        $uri = '/' . trim($uri, '/');
    
        if ($uri === '//') {
            return '/';
        }
    
        return $uri;
    }
    
    public function host(): string
    {
        return $_SERVER['HTTP_HOST'];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_REQUEST[$key] ?? $default;
    }

    public function all(): array
    {
        return $_REQUEST;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }
}