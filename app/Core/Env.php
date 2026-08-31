<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny .env loader (first-party — no vlucas/phpdotenv dependency, ADR-001).
 *
 * Parses KEY=VALUE lines, supports quotes, comments and `export` prefixes, and
 * populates getenv()/$_ENV/$_SERVER. Does NOT overwrite variables already set
 * in the real environment (so host-level vars win).
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !is_file($path) || !is_readable($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = self::clean($value);

            if ($name === '' || array_key_exists($name, $_ENV) || getenv($name) !== false) {
                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }

        self::$loaded = true;
    }

    /**
     * Read a value with type coercion for common literals.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    private static function clean(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Strip a trailing inline comment only for UNquoted values.
        if ($value[0] !== '"' && $value[0] !== "'") {
            $hash = strpos($value, ' #');
            if ($hash !== false) {
                $value = substr($value, 0, $hash);
            }
            return trim($value);
        }

        // Quoted value: strip the surrounding quotes.
        $quote = $value[0];
        if (str_ends_with($value, $quote) && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }
}
