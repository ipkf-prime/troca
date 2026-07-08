<?php

namespace IPKF\Http;

class Request
{
    public static function capture(): self
    {
        return new self();
    }

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    public function all(): array
    {
        $json = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode(file_get_contents('php://input') ?: '', true);
            $json = is_array($payload) ? $payload : [];
        }

        return array_merge($_GET, $_POST, $json);
    }

    public function input(string $key, $default = null)
    {
        return $this->all()[$key] ?? $default;
    }

    public function host(): string
    {
        return $_SERVER['HTTP_HOST'] ?? '';
    }
}
