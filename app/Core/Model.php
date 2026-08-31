<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight entity/DTO base. Models are plain data holders hydrated from
 * repository rows — no active-record magic, no DB access inside models.
 */
abstract class Model
{
    /** @param array<string,mixed> $attributes */
    public function __construct(protected array $attributes = [])
    {
    }

    public static function fromRow(array $row): static
    {
        return new static($row);
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,static> */
    public static function collection(array $rows): array
    {
        return array_map(static fn (array $row): static => new static($row), $rows);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
