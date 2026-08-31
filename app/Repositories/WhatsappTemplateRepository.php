<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/** CMS-managed WhatsApp message templates — used ONLY to build wa.me URLs. */
final class WhatsappTemplateRepository extends Repository
{
    protected string $table = 'whatsapp_templates';

    public function activeByKey(string $key): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM `whatsapp_templates` WHERE `key`=:k AND `is_active`=1 LIMIT 1",
            ['k' => $key]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->selectOne("SELECT * FROM `whatsapp_templates` WHERE `id`=:id LIMIT 1", ['id' => $id]);
    }

    public function allOrdered(): array
    {
        return $this->db->select("SELECT * FROM `whatsapp_templates` ORDER BY `name`");
    }

    public function update(int $id, array $data, ?int $userId): void
    {
        $this->db->statement(
            "UPDATE `whatsapp_templates` SET `name`=:n, `message`=:m, `is_active`=:a, `updated_by`=:u WHERE `id`=:id",
            ['n' => $data['name'], 'm' => $data['message'], 'a' => !empty($data['is_active']) ? 1 : 0, 'u' => $userId, 'id' => $id]
        );
    }
}
