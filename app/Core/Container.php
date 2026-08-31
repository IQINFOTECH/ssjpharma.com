<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Minimal service container / registry.
 *
 * Deliberately small: supports shared singletons via bind()/instance() and
 * resolution via get(). No autowiring magic — dependencies are wired explicitly
 * in the bootstrap, keeping behaviour predictable on shared hosting.
 */
final class Container
{
    /** @var array<string,Closure> */
    private array $bindings = [];

    /** @var array<string,mixed> */
    private array $instances = [];

    private static ?Container $instance = null;

    public static function getInstance(): Container
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(Container $container): void
    {
        self::$instance = $container;
    }

    /** Register a lazily-instantiated shared service. */
    public function bind(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    /** Register an already-built instance. */
    public function instance(string $id, mixed $object): void
    {
        $this->instances[$id] = $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            return $this->instances[$id] = ($this->bindings[$id])($this);
        }

        throw new RuntimeException("Service '{$id}' is not registered in the container.");
    }
}
