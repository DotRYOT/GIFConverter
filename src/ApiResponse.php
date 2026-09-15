<?php

declare(strict_types=1);

final class ApiResponse
{
    public static function json(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    public static function ok(array $data = []): void
    {
        self::json(200, ['ok' => true, 'data' => $data]);
    }

    public static function fail(int $statusCode, string $code, string $message): void
    {
        self::json($statusCode, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
    }
}
