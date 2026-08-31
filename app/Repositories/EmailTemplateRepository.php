<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/** CMS-managed email templates (rendered with safe placeholders at enqueue time). */
final class EmailTemplateRepository extends Repository
{
    protected string $table = 'email_templates';

    public function findByKey(string $key): ?array
    {
        return $this->db->selectOne("SELECT * FROM `email_templates` WHERE `key`=:k LIMIT 1", ['k' => $key]);
    }

    /** Active template by key (used when actually sending). */
    public function activeByKey(string $key): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `email_templates` WHERE `key`=:k AND `is_active`=1 LIMIT 1",
            ['k' => $key]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->selectOne("SELECT * FROM `email_templates` WHERE `id`=:id LIMIT 1", ['id' => $id]);
    }

    public function allOrdered(): array
    {
        return $this->db->select("SELECT * FROM `email_templates` ORDER BY `name`");
    }

    public function update(int $id, array $data, ?int $userId): void
    {
        $this->db->statement(
            "UPDATE `email_templates` SET `name`=:n, `subject`=:s, `body_html`=:h, `body_text`=:t,
                    `is_active`=:a, `updated_by`=:u WHERE `id`=:id",
            [
                'n' => $data['name'], 's' => $data['subject'], 'h' => $data['body_html'],
                't' => $data['body_text'], 'a' => !empty($data['is_active']) ? 1 : 0,
                'u' => $userId, 'id' => $id,
            ]
        );
    }
}
