<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class MenuRepository extends Repository
{
    protected string $table = 'menus';

    public function findByKey(string $key): ?array
    {
        return $this->db->selectOne("SELECT * FROM `menus` WHERE `key` = :k LIMIT 1", ['k' => $key]);
    }

    /** @return array<int,array<string,mixed>> */
    public function allMenus(): array
    {
        return $this->db->select("SELECT * FROM `menus` ORDER BY `key` ASC");
    }
}
