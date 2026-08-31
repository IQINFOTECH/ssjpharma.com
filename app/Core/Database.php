<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connection manager. Lazily connects on first use so the app (and the
 * health check) can boot and report DB status without a hard failure.
 *
 * ALL data access goes through prepared statements (SECURITY_PLAN §4).
 */
final class Database
{
    private ?PDO $pdo = null;

    /**
     * @param array{driver:string,host:string,port:int,database:string,username:string,password:string,charset:string,collation:string,options:array} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['driver'],
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset'],
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options'],
            );
        } catch (PDOException $e) {
            // Never leak DSN/credentials upward.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return $this->pdo;
    }

    /** Lightweight connectivity probe used by the health check. */
    public function isConnected(): bool
    {
        try {
            $this->pdo()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->statement($sql, $bindings)->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->statement($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    public function insert(string $sql, array $bindings = []): string
    {
        $this->statement($sql, $bindings);
        return $this->pdo()->lastInsertId();
    }

    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    public function beginTransaction(): void { $this->pdo()->beginTransaction(); }
    public function commit(): void           { $this->pdo()->commit(); }
    public function rollBack(): void         { if ($this->pdo?->inTransaction()) { $this->pdo->rollBack(); } }
}
