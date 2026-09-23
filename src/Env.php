<?php

declare(strict_types=1);

namespace Maryam;

/**
 * .env faylini o'qiydi (KALIT=qiymat qatorlari).
 * API kalit kodga yozilmasligi uchun shu yerdan olinadi.
 */
final class Env
{
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            self::$vars[$key] = trim($value, "\"'");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Tizim environment'i .env dan ustun turadi (server sozlamalari uchun qulay)
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return self::$vars[$key] ?? $default;
    }
}
