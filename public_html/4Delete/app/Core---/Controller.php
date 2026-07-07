<?php

namespace IPKF\Core;

class Controller
{
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'status' => $status,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    protected function view(string $view, array $data = []): void
    {
        View::make($view, $data);
    }
}