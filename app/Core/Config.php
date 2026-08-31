<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuration repository. Lazily loads config/<file>.php and exposes values
 * via dot notation, e.g. config('database.connections.mysql.host').
 */
final class Config
{
    /** @var array<string,mixed> */
    private array $items = [];

    public function __construct(private readonly string $configPath)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!array_key_exists($file, $this->items)) {
            $this->loadFile($file);
        }

        $value = $this->items[$file] ?? null;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value ?? $default;
    }

    public function set(string $file, array $values): void
    {
        $this->items[$file] = $values;
    }

    private function loadFile(string $file): void
    {
        // Guard against path traversal in the config key.
        if (!preg_match('/^[a-z0-9_]+$/i', $file)) {
            $this->items[$file] = [];
            return;
        }

        $path = $this->configPath . DIRECTORY_SEPARATOR . $file . '.php';
        $this->items[$file] = is_file($path) ? (array) require $path : [];
    }
}
