<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verify(?string $token): void
    {
        if (!$token || !hash_equals(self::token(), $token)) {
            throw new \RuntimeException('The security token expired. Refresh the page and try again.');
        }
    }
}
