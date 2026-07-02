<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class Support
{
    public static function uid(string $prefix = ''): string
    {
        return $prefix . bin2hex(random_bytes(16));
    }

    public static function apiKey(string $type): string
    {
        return 'tmp_' . $type . '_' . bin2hex(random_bytes(24));
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function identifier(string $value, string $label = 'identifier'): string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,62}$/', $value)) {
            throw new InvalidArgumentException("Invalid $label. Use letters, numbers and underscores.");
        }
        return $value;
    }

    public static function quoteIdentifier(string $value): string
    {
        self::identifier($value);
        return '"' . $value . '"';
    }

    public static function slug(string $value): string
    {
        $value = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
        return $value !== '' ? $value : 'project';
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
