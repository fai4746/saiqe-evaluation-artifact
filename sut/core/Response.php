<?php
class Response {
    public static function json(string $status, string $message, mixed $data = null, array $errors = [], int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors ?: null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(string $message = 'Success', mixed $data = null, int $code = 200): never {
        self::json('success', $message, $data, [], $code);
    }

    public static function error(string $message, array $errors = [], int $code = 400): never {
        self::json('error', $message, null, $errors, $code);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never {
        self::error($message, [], 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never {
        self::error($message, [], 403);
    }

    public static function notFound(string $message = 'Not found'): never {
        self::error($message, [], 404);
    }
}
