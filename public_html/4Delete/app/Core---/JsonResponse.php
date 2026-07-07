<?php

namespace IPKF\Core;

class JsonResponse
{
    public static function success(mixed $data = null): void
    {
        http_response_code(200);

        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    public static function error(string $message, int $code = 400): void
    {
        http_response_code($code);

        echo json_encode([
            'success' => false,
            'error' => $message
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}