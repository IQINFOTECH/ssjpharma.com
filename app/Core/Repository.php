<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base repository — the single data-access path for an aggregate (SECURITY_PLAN §4).
 *
 * Concrete repositories set $table and add intent-revealing query methods, always
 * using prepared statements. Column/identifier values are NEVER taken from user
 * input; only bound *values* are. Full CRUD helpers arrive with the first real
 * repository in Phase 2 — this base establishes the contract.
 */
abstract class Repository
{
    /** @var string the table this repository owns */
    protected string $table = '';

    public function __construct(protected Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->qualifiedTable()} WHERE id = :id LIMIT 1",
            ['id' => $id],
        );
    }

    public function all(int $limit = 100, int $offset = 0): array
    {
        // LIMIT/OFFSET are cast to int and inlined — binding them as params fails
        // under PDO::ATTR_EMULATE_PREPARES=false (MySQL rejects quoted LIMIT).
        $limit  = max(0, $limit);
        $offset = max(0, $offset);
        return $this->db->select(
            "SELECT * FROM {$this->qualifiedTable()} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}",
        );
    }

    public function count(): int
    {
        $row = $this->db->selectOne("SELECT COUNT(*) AS c FROM {$this->qualifiedTable()}");
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Backtick-quote the configured table name. The name is developer-defined
     * (never user input); this guards against accidental reserved-word clashes.
     */
    protected function qualifiedTable(): string
    {
        return '`' . str_replace('`', '', $this->table) . '`';
    }
}
