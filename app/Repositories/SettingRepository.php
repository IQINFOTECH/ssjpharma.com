<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

final class SettingRepository extends Repository
{
    protected string $table = 'settings';

    /** @return array<string,string> key => value */
    public function allKeyed(): array
    {
        $out = [];
        foreach ($this->db->select("SELECT `key`,`value` FROM `settings`") as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function allForAdmin(): array
    {
        return $this->db->select(
            "SELECT * FROM `settings` ORDER BY `group` ASC, `sort_order` ASC, `id` ASC"
        );
    }

    /** @return array<string,array<int,array<string,mixed>>> grouped by `group` */
    public function grouped(): array
    {
        $groups = [];
        foreach ($this->allForAdmin() as $row) {
            $groups[$row['group']][] = $row;
        }
        return $groups;
    }

    public function updateValue(string $key, string $value, ?int $userId = null): void
    {
        $this->db->statement(
            "UPDATE `settings` SET `value` = :v, `updated_by` = :u WHERE `key` = :k",
            ['v' => $value, 'u' => $userId, 'k' => $key]
        );
    }
}
