<?php

declare(strict_types=1);

namespace App\Support;

final class JsonResponse
{
    public static function send(int $status, mixed $data = null, ?string $error = null): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['status' => $status, 'data' => $data, 'error' => $error],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    public static function ok(mixed $data = null, int $status = 200): never
    {
        self::send($status, $data, null);
    }

    public static function error(string $error, int $status = 400, mixed $data = null): never
    {
        self::send($status, $data, $error);
    }
}
