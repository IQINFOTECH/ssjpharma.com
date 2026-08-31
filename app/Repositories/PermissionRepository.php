<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class PermissionRepository extends Repository
{
    protected string $table = 'permissions';

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->select("SELECT * FROM `permissions` ORDER BY `group` ASC, `key` ASC");
    }

    /** @return array<string,array<int,array<string,mixed>>> grouped by `group` */
    public function grouped(): array
    {
        $out = [];
        foreach ($this->all() as $row) {
            $out[$row['group'] ?: 'other'][] = $row;
        }
        return $out;
    }

    /** @return array<int,string> all permission keys */
    public function allKeys(): array
    {
        return array_map(static fn (array $r): string => (string) $r['key'], $this->all());
    }
}
