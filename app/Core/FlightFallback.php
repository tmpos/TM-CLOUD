<?php

declare(strict_types=1);

/**
 * Minimal Flight-compatible router used when Composer dependencies are absent.
 *
 * TMPBase only relies on route(), json(), redirect() and start(). Keeping this
 * fallback local allows installation on shared hosting without SSH access.
 */
final class Flight
{
    /** @var array<int, array{method: string, pattern: string, callback: callable}> */
    private static array $routes = [];

    public static function route(string $definition, callable $callback): void
    {
        $parts = preg_split('/\s+/', trim($definition), 2);
        if (count($parts) === 1) {
            $method = 'GET';
            $pattern = $parts[0];
        } else {
            [$method, $pattern] = $parts;
        }

        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'callback' => $callback,
        ];
    }

    public static function json(mixed $data, int $status = 200, int $options = 0): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(
            $data,
            $options | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
    }

    public static function start(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = self::compile($route['pattern']);
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            array_shift($matches);
            ($route['callback'])(...array_values($matches));
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Route not found.'], JSON_UNESCAPED_SLASHES);
    }

    private static function compile(string $pattern): string
    {
        if ($pattern === '/') {
            return '#^/$#';
        }

        $segments = explode('/', trim($pattern, '/'));
        $compiled = array_map(
            static fn (string $segment): string => str_starts_with($segment, '@')
                ? '([^/]+)'
                : preg_quote($segment, '#'),
            $segments
        );

        return '#^/' . implode('/', $compiled) . '$#';
    }
}
