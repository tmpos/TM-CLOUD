<?php

declare(strict_types=1);

namespace App\Core;

final class Http
{
    public static function requestId(): string
    {
        return (string) ($_SERVER['TMPBASE_REQUEST_ID'] ?? 'unknown');
    }

    public static function clientIp(): string
    {
        return (string) ($_SERVER['TMPBASE_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    public static function error(string|\Throwable $error, int $status = 400, ?string $code = null, array $extra = []): void
    {
        $message = $error instanceof \Throwable ? $error->getMessage() : $error;
        $code ??= match (true) {
            $status === 401 => 'unauthenticated',
            $status === 403 => 'forbidden',
            $status === 404 => 'not_found',
            $status === 409 => 'conflict',
            $status === 413 => 'quota_exceeded',
            $status === 422 => 'validation_error',
            $status === 429 => 'rate_limited',
            $status >= 500 => 'internal_error',
            default => 'request_failed',
        };
        if ($error instanceof \Throwable) {
            error_log(Support::json([
                'level' => 'error',
                'event' => 'http.request_failed',
                'request_id' => self::requestId(),
                'status' => $status,
                'error_class' => $error::class,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'timestamp' => gmdate(DATE_ATOM),
            ]));
        }
        if ($status >= 500) $message = 'Internal server error.';
        \Flight::json(array_merge($extra, [
            'error' => $message,
            'code' => $code,
            'message' => $message,
            'request_id' => self::requestId(),
        ]), $status);
    }

    public static function input(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $data = json_decode((string) file_get_contents('php://input'), true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function flashes(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
}
