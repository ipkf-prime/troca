<?php

namespace IPKF\Core;

class Response
{
    public function status(int $code): void
    {
        http_response_code($code);
    }

    public function json(array $data): never
    {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_PRETTY_PRINT
        );

        exit;
    }

    public function redirect(string $url): never
    {
        header("Location: {$url}");
        exit;
    }
}