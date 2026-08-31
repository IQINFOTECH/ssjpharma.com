<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * WhatsApp CTA click tracking (§15). A click is NOT a lead — this only records
 * that a visitor tapped a wa.me button. Best-effort attribution.
 */
final class WhatsappClickRepository extends Repository
{
    protected string $table = 'whatsapp_clicks';

    public function record(array $data): void
    {
        $this->db->statement(
            "INSERT INTO `whatsapp_clicks` (`context`,`page`,`product_id`,`utm_source`,`utm_medium`,`utm_campaign`,`ip`,`user_agent`)
             VALUES (:context,:page,:product_id,:utm_source,:utm_medium,:utm_campaign,:ip,:user_agent)",
            [
                'context'      => mb_substr((string) ($data['context'] ?? 'general'), 0, 40),
                'page'         => isset($data['page']) ? mb_substr((string) $data['page'], 0, 255) : null,
                'product_id'   => !empty($data['product_id']) ? (int) $data['product_id'] : null,
                'utm_source'   => isset($data['utm_source']) ? mb_substr((string) $data['utm_source'], 0, 120) : null,
                'utm_medium'   => isset($data['utm_medium']) ? mb_substr((string) $data['utm_medium'], 0, 120) : null,
                'utm_campaign' => isset($data['utm_campaign']) ? mb_substr((string) $data['utm_campaign'], 0, 120) : null,
                'ip'           => isset($data['ip']) ? mb_substr((string) $data['ip'], 0, 45) : null,
                'user_agent'   => isset($data['user_agent']) ? mb_substr((string) $data['user_agent'], 0, 255) : null,
            ]
        );
    }

    public function totalClicks(): int
    {
        return (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `whatsapp_clicks`")['c'] ?? 0);
    }
}
