<?php

namespace IPKF\Http;

class Response
{
    public function send(string $content): void
    {
        echo $content;
    }

    public function json(array $data): void
    {
        header('Content-Type: application/json');

        echo json_encode($data);
    }

    public function redirect(string $url): void
    {
        header("Location: $url");

        exit;
    }

    public function status(int $code): void
    {
        http_response_code($code);
    }
}