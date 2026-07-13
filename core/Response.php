<?php
namespace Core;

// =========================================================
// RESPONSE — consistent JSON envelope for every API endpoint
// =========================================================
class Response
{
    public static function json($data, int $status = HTTP_OK): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($data = null, string $message = 'OK', int $status = HTTP_OK): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message = 'Error', int $status = HTTP_BAD_REQUEST, $errors = null): void
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }
}
