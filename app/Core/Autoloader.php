<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal PSR-4 autoloader.
 *
 * Used as a FALLBACK when the Composer autoloader is not present, guaranteeing
 * the application boots on hosting where `vendor/` was not uploaded (ADR-001).
 * When Composer's autoloader exists it is preferred and this is skipped.
 */
final class Autoloader
{
    /** @var array<string,string> namespace prefix => base directory */
    private array $prefixes = [];

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $this->prefixes[$prefix] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function load(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;
                return true;
            }
        }

        return false;
    }
}
