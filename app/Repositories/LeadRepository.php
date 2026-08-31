<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Repository;

/**
 * Central lead store. Every public enquiry source (general contact, product,
 * distributor, partnership, CTA) writes here. Prepared statements throughout;
 * LIMIT/OFFSET and INTERVAL are int-cast + inlined (EMULATE_PREPARES=false).
 */
final class LeadRepository extends Repository
{
    protected string $table = 'leads';

    /** Columns bound on insert (exact set — callers may pass a superset safely). */
    private const INSERT_COLS = [
        'reference','name','company','email','phone','whatsapp','country','state','city',
        'business_type','enquiry_type','product_id','product_name_snapshot','message','requirement',
        'preferred_contact','priority','consent','consent_at','privacy_version','source_id','status_id',
        'landing_page','source_url','referrer','utm_source','utm_medium','utm_campaign','utm_term','utm_content',
        'ip','user_agent','is_spam',
    ];

    public function statusIdByKey(string $key): ?int
    {
        $row = $this->db->selectOne("SELECT id FROM `lead_statuses` WHERE `key` = :k LIMIT 1", ['k' => $key]);
        return $row ? (int) $row['id'] : null;
    }

    public function sourceIdByKey(string $key): ?int
    {
        $row = $this->db->selectOne("SELECT id FROM `lead_sources` WHERE `key` = :k LIMIT 1", ['k' => $key]);
        return $row ? (int) $row['id'] : null;
    }

    public function nextReference(): string
    {
        $row = $this->db->selectOne("SELECT COALESCE(MAX(id),0) AS m FROM `leads`");
        $n = (int) ($row['m'] ?? 0) + 1;
        return 'SSJ-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int
    {
        $cols = '`' . implode('`,`', self::INSERT_COLS) . '`';
        $ph = ':' . implode(',:', self::INSERT_COLS);
        $bind = [];
        foreach (self::INSERT_COLS as $c) {
            $bind[$c] = $data[$c] ?? null;
        }
        return (int) $this->db->insert("INSERT INTO `leads` ({$cols}) VALUES ({$ph})", $bind);
    }

    public function findById(int $id): ?array
    {
        return $this->findRow('l.`id` = :id AND l.`deleted_at` IS NULL', ['id' => $id]);
    }

    /**
     * Fetch a single lead ONLY if the caller's visibility scope permits it
     * (Phase 4.1). The scope is enforced in SQL (not by filtering in PHP), so an
     * out-of-scope id returns null exactly like a non-existent id — the caller
     * then responds 404, never leaking whether the lead exists (IDOR-safe).
     */
    public function findByIdInScope(int $id, array $scope): ?array
    {
        return $this->findRow(
            'l.`id` = :id AND l.`deleted_at` IS NULL AND (' . $this->scopeSql($scope) . ')',
            ['id' => $id]
        );
    }

    /** Shared detail SELECT — the WHERE body is trusted, built only from constants. */
    private function findRow(string $whereBody, array $params): ?array
    {
        return $this->db->selectOne(
            "SELECT l.*, st.`name` AS status_name, st.`key` AS status_key, st.`color` AS status_color,
                    sr.`name` AS source_name, sr.`key` AS source_key,
                    u.`name` AS assigned_name, u.`email` AS assigned_email,
                    p.`name` AS product_name, p.`slug` AS product_slug, p.`status` AS product_status
             FROM `leads` l
             LEFT JOIN `lead_statuses` st ON st.id = l.status_id
             LEFT JOIN `lead_sources`  sr ON sr.id = l.source_id
             LEFT JOIN `users` u ON u.id = l.assigned_user_id
             LEFT JOIN `products` p ON p.id = l.product_id
             WHERE {$whereBody} LIMIT 1",
            $params
        );
    }

    /**
     * Visibility constraint as a boolean SQL fragment (Phase 4.1). The assigned
     * user id is an int cast from the SESSION (never client input) and inlined
     * like every other integer in this repo (EMULATE_PREPARES=false), so it is
     * injection-safe. Fails CLOSED: an unknown mode yields "no rows".
     */
    private function scopeSql(array $scope): string
    {
        $mode = (string) ($scope['mode'] ?? 'none');
        if ($mode === 'all') {
            return '1=1';
        }
        if ($mode === 'assigned') {
            $uid = (int) ($scope['user_id'] ?? 0);
            return $uid > 0 ? 'l.`assigned_user_id` = ' . $uid : '1=0';
        }
        return '1=0'; // none / unknown → deny
    }

    public function recordSubmission(?int $leadId, string $formKey, string $payloadJson, ?string $ip, ?string $ua, bool $isSpam): void
    {
        $this->db->statement(
            "INSERT INTO `contact_submissions` (`lead_id`,`form_key`,`payload`,`ip`,`user_agent`,`is_spam`)
             VALUES (:lead,:fk,:payload,:ip,:ua,:spam)",
            ['lead' => $leadId, 'fk' => $formKey, 'payload' => $payloadJson, 'ip' => $ip, 'ua' => $ua, 'spam' => $isSpam ? 1 : 0]
        );
    }

    public function markNotified(int $id, string $status): void
    {
        $notified = $status === 'sent' ? date('Y-m-d H:i:s') : null;
        $this->db->statement(
            "UPDATE `leads` SET `notification_status` = :s, `notified_at` = COALESCE(:n, `notified_at`) WHERE `id` = :id",
            ['s' => $status, 'n' => $notified, 'id' => $id]
        );
    }

    // --- Duplicate detection -------------------------------------------------

    /**
     * An OPEN, non-spam, non-deleted lead with the same email or phone created
     * within the window. Used to link a repeat submission instead of duplicating.
     */
    public function findOpenDuplicate(?string $email, ?string $phone, int $seconds): ?array
    {
        $email = $email !== null ? strtolower(trim($email)) : '';
        $phone = $phone !== null ? trim($phone) : '';
        if ($email === '' && $phone === '') {
            return null;
        }
        $seconds = max(1, $seconds);

        // Exact matches only (portable across MySQL 5.7+/MariaDB — no REGEXP_REPLACE).
        // Email is the primary dedup key; phone is a secondary exact match.
        $conds = [];
        $params = [];
        if ($email !== '') { $conds[] = 'LOWER(l.`email`) = :em'; $params['em'] = $email; }
        if ($phone !== '') { $conds[] = 'l.`phone` = :ph'; $params['ph'] = $phone; }
        $match = '(' . implode(' OR ', $conds) . ')';

        return $this->db->selectOne(
            "SELECT l.* FROM `leads` l
             JOIN `lead_statuses` st ON st.id = l.status_id
             WHERE {$match}
               AND l.`deleted_at` IS NULL AND l.`is_spam` = 0
               AND st.`is_won` = 0 AND st.`is_lost` = 0 AND st.`key` <> 'spam'
               AND l.`created_at` >= (NOW() - INTERVAL {$seconds} SECOND)
             ORDER BY l.`id` DESC LIMIT 1",
            $params
        );
    }

    public function touchUpdated(int $id): void
    {
        $this->db->statement("UPDATE `leads` SET `updated_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    // --- Admin management ----------------------------------------------------

    public function updateStatus(int $id, int $statusId): void
    {
        $this->db->statement("UPDATE `leads` SET `status_id` = :s WHERE `id` = :id", ['s' => $statusId, 'id' => $id]);
    }

    public function updatePriority(int $id, string $priority): void
    {
        $this->db->statement("UPDATE `leads` SET `priority` = :p WHERE `id` = :id", ['p' => $priority, 'id' => $id]);
    }

    public function assign(int $id, ?int $userId): void
    {
        $this->db->statement("UPDATE `leads` SET `assigned_user_id` = :u WHERE `id` = :id", ['u' => $userId, 'id' => $id]);
    }

    public function markContacted(int $id): void
    {
        $this->db->statement("UPDATE `leads` SET `last_contacted_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    /** Set (or clear, with null) the follow-up date. Value is validated by the caller. */
    public function updateFollowUp(int $id, ?string $date): void
    {
        $this->db->statement("UPDATE `leads` SET `follow_up_date` = :d WHERE `id` = :id", ['d' => $date, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->statement("UPDATE `leads` SET `deleted_at` = NOW() WHERE `id` = :id", ['id' => $id]);
    }

    // --- Listing / filtering / export ---------------------------------------

    /**
     * @param array<string,mixed> $f filters: q,status,priority,source,enquiry_type,assigned,product,from,to,sort
     * @return array{where:string,params:array<string,mixed>,order:string}
     */
    private function buildFilter(array $f): array
    {
        // Visibility scope FIRST — every listing/search/export path flows through
        // here, so it can never be bypassed. Defaults to deny (no scope → no rows).
        $where = ['l.`deleted_at` IS NULL', $this->scopeSql($f['scope'] ?? ['mode' => 'none'])];
        $params = [];

        if (($f['q'] ?? '') !== '') {
            $where[] = "LOWER(CONCAT_WS(' ', l.`name`, COALESCE(l.`company`,''), COALESCE(l.`email`,''), COALESCE(l.`phone`,''), l.`reference`)) LIKE :q";
            $params['q'] = '%' . strtolower((string) $f['q']) . '%';
        }
        if (!empty($f['status']))       { $where[] = 'l.`status_id` = :st';  $params['st'] = (int) $f['status']; }
        if (($f['priority'] ?? '') !== ''){ $where[] = 'l.`priority` = :pr'; $params['pr'] = (string) $f['priority']; }
        if (!empty($f['source']))       { $where[] = 'l.`source_id` = :sr';  $params['sr'] = (int) $f['source']; }
        if (($f['enquiry_type'] ?? '') !== ''){ $where[] = 'l.`enquiry_type` = :et'; $params['et'] = (string) $f['enquiry_type']; }
        if (($f['assigned'] ?? '') !== '') {
            if ($f['assigned'] === 'none') { $where[] = 'l.`assigned_user_id` IS NULL'; }
            else { $where[] = 'l.`assigned_user_id` = :au'; $params['au'] = (int) $f['assigned']; }
        }
        if (!empty($f['product']))      { $where[] = 'l.`product_id` = :pid'; $params['pid'] = (int) $f['product']; }
        if (!empty($f['from']))         { $where[] = 'l.`created_at` >= :from'; $params['from'] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to']))           { $where[] = 'l.`created_at` <= :to';   $params['to'] = $f['to'] . ' 23:59:59'; }
        if (($f['spam'] ?? '') === 'exclude') { $where[] = 'l.`is_spam` = 0'; }
        // Follow-up date filter (Phase 5) — dates are computed server-side (CURDATE).
        switch ($f['followup'] ?? '') {
            case 'today':    $where[] = 'l.`follow_up_date` = CURDATE()'; break;
            case 'overdue':  $where[] = 'l.`follow_up_date` < CURDATE()'; break;
            case 'due':      $where[] = 'l.`follow_up_date` <= CURDATE()'; break;
            case 'next7':    $where[] = 'l.`follow_up_date` BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY)'; break;
            case 'none':     $where[] = 'l.`follow_up_date` IS NULL'; break;
        }

        $order = match ($f['sort'] ?? '') {
            'oldest'   => 'l.`created_at` ASC',
            'priority' => "FIELD(l.`priority`,'urgent','high','medium','low'), l.`created_at` DESC",
            'name'     => 'l.`name` ASC',
            default    => 'l.`created_at` DESC',
        };

        return ['where' => 'WHERE ' . implode(' AND ', $where), 'params' => $params, 'order' => $order];
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int} */
    public function paginate(array $f, int $limit, int $offset): array
    {
        $q = $this->buildFilter($f);
        $total = (int) ($this->db->selectOne("SELECT COUNT(*) c FROM `leads` l {$q['where']}", $q['params'])['c'] ?? 0);
        $limit = max(1, $limit); $offset = max(0, $offset);
        $rows = $this->db->select(
            "SELECT l.`id`, l.`reference`, l.`name`, l.`company`, l.`email`, l.`phone`, l.`enquiry_type`,
                    l.`priority`, l.`created_at`, l.`follow_up_date`, l.`is_spam`, l.`product_name_snapshot`,
                    st.`name` AS status_name, st.`key` AS status_key, st.`color` AS status_color,
                    sr.`name` AS source_name, u.`name` AS assigned_name, p.`name` AS product_name
             FROM `leads` l
             LEFT JOIN `lead_statuses` st ON st.id = l.status_id
             LEFT JOIN `lead_sources`  sr ON sr.id = l.source_id
             LEFT JOIN `users` u ON u.id = l.assigned_user_id
             LEFT JOIN `products` p ON p.id = l.product_id
             {$q['where']} ORDER BY {$q['order']} LIMIT {$limit} OFFSET {$offset}",
            $q['params']
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> rows for CSV export (capped). */
    public function exportRows(array $f, int $max = 5000): array
    {
        $q = $this->buildFilter($f);
        $max = max(1, min($max, 20000));
        return $this->db->select(
            "SELECT l.`id`, l.`reference`, l.`created_at`, l.`name`, l.`company`, l.`email`, l.`phone`,
                    l.`country`, l.`state`, l.`city`, l.`enquiry_type`, l.`priority`, l.`follow_up_date`,
                    st.`name` AS status_name, sr.`name` AS source_name, u.`name` AS assigned_name,
                    p.`name` AS product_name, l.`utm_source`, l.`utm_medium`, l.`utm_campaign`
             FROM `leads` l
             LEFT JOIN `lead_statuses` st ON st.id = l.status_id
             LEFT JOIN `lead_sources`  sr ON sr.id = l.source_id
             LEFT JOIN `users` u ON u.id = l.assigned_user_id
             LEFT JOIN `products` p ON p.id = l.product_id
             {$q['where']} ORDER BY {$q['order']} LIMIT {$max}",
            $q['params']
        );
    }

    // --- Metrics (dashboard) -------------------------------------------------

    /**
     * Dashboard/list metrics — computed ONLY over leads the caller may see
     * (Phase 4.1). The same scope fragment that gates the listing gates the
     * counts, so a restricted user never learns company-wide totals.
     * Every count is aliased `l` so the scope fragment (which references `l`)
     * applies uniformly.
     * @return array{total:int,new:int,open:int,today:int,unassigned:int}
     */
    public function metrics(array $scope): array
    {
        $sc = $this->scopeSql($scope);
        // "open" = not converted/lost/spam (semantic flags; COALESCE handles a NULL status).
        $open = "l.is_spam=0 AND COALESCE(s.is_won,0)=0 AND COALESCE(s.is_lost,0)=0 AND COALESCE(s.`key`,'')<>'spam'";

        // Query 1 (of 2): all non-status counts as conditional aggregates (Phase 6 —
        // was ~10 separate COUNT round-trips). LEFT JOIN so NULL-status leads still count.
        $a = $this->db->selectOne(
            "SELECT
                SUM(CASE WHEN l.is_spam=0 THEN 1 ELSE 0 END) total,
                SUM(CASE WHEN l.is_spam=0 AND DATE(l.created_at)=CURDATE() THEN 1 ELSE 0 END) today,
                SUM(CASE WHEN l.is_spam=0 AND YEARWEEK(l.created_at,1)=YEARWEEK(CURDATE(),1) THEN 1 ELSE 0 END) week,
                SUM(CASE WHEN l.is_spam=0 AND l.created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN 1 ELSE 0 END) month,
                SUM(CASE WHEN l.is_spam=0 AND l.enquiry_type='product' THEN 1 ELSE 0 END) product,
                SUM(CASE WHEN {$open} THEN 1 ELSE 0 END) `open`,
                SUM(CASE WHEN {$open} AND l.assigned_user_id IS NULL THEN 1 ELSE 0 END) unassigned,
                SUM(CASE WHEN {$open} AND l.follow_up_date = CURDATE() THEN 1 ELSE 0 END) due_today,
                SUM(CASE WHEN {$open} AND l.follow_up_date < CURDATE() THEN 1 ELSE 0 END) overdue,
                SUM(CASE WHEN {$open} AND l.follow_up_date > CURDATE() AND l.follow_up_date <= (CURDATE() + INTERVAL 7 DAY) THEN 1 ELSE 0 END) upcoming
             FROM leads l LEFT JOIN lead_statuses s ON s.id=l.status_id
             WHERE l.deleted_at IS NULL AND {$sc}"
        ) ?? [];
        $n = static fn (string $k): int => (int) ($a[$k] ?? 0);

        // Query 2 (of 2): per-status counts (one grouped query).
        $byStatus = [];
        foreach ($this->db->select(
            "SELECT s.`key` k, COUNT(*) c FROM leads l JOIN lead_statuses s ON s.id=l.status_id
             WHERE l.deleted_at IS NULL AND l.is_spam=0 AND {$sc} GROUP BY s.`key`"
        ) as $r) {
            $byStatus[(string) $r['k']] = (int) $r['c'];
        }
        $st = static fn (string $k): int => $byStatus[$k] ?? 0;

        return [
            'total' => $n('total'), 'new' => $st('new'), 'contacted' => $st('contacted'),
            'qualified' => $st('qualified'), 'proposal' => $st('proposal'),
            'converted' => $st('converted'), 'lost' => $st('lost'),
            'open' => $n('open'), 'today' => $n('today'), 'week' => $n('week'),
            'month' => $n('month'), 'product' => $n('product'), 'unassigned' => $n('unassigned'),
            'due_today' => $n('due_today'), 'overdue' => $n('overdue'), 'upcoming' => $n('upcoming'),
        ];
    }

    // --- Follow-up digest (Phase 5) -----------------------------------------

    /**
     * Active assignees who have at least one OPEN lead due for follow-up on/before
     * $date. Used by the digest job to iterate recipients. Disabled/deleted users
     * are excluded so a disabled user never receives a digest.
     * @return array<int,array{id:int,name:string,email:string}>
     */
    public function assigneesWithDueFollowUps(string $date): array
    {
        return $this->db->select(
            "SELECT u.id, u.`name`, u.`email`
             FROM `leads` l
             JOIN `users` u ON u.id = l.assigned_user_id
             JOIN `lead_statuses` s ON s.id = l.status_id
             WHERE l.`deleted_at` IS NULL AND l.`is_spam` = 0
               AND s.`is_won` = 0 AND s.`is_lost` = 0 AND s.`key` <> 'spam'
               AND l.`follow_up_date` IS NOT NULL AND l.`follow_up_date` <= :d
               AND u.`is_active` = 1 AND u.`deleted_at` IS NULL
             GROUP BY u.id, u.`name`, u.`email`
             ORDER BY u.id",
            ['d' => $date]
        );
    }

    /**
     * Open leads assigned to $userId that are due for follow-up on/before $date.
     * Inherently scoped to the assignee (assigned_user_id = :u) — a digest can
     * never contain another user's leads.
     * @return array<int,array<string,mixed>>
     */
    public function dueFollowUpsForAssignee(int $userId, string $date): array
    {
        return $this->db->select(
            "SELECT l.`id`, l.`reference`, l.`name`, l.`company`, l.`priority`, l.`follow_up_date`,
                    st.`name` AS status_name, p.`name` AS product_name, l.`product_name_snapshot`
             FROM `leads` l
             JOIN `lead_statuses` st ON st.id = l.status_id
             LEFT JOIN `products` p ON p.id = l.product_id
             WHERE l.`assigned_user_id` = :u AND l.`deleted_at` IS NULL AND l.`is_spam` = 0
               AND st.`is_won` = 0 AND st.`is_lost` = 0 AND st.`key` <> 'spam'
               AND l.`follow_up_date` IS NOT NULL AND l.`follow_up_date` <= :d
             ORDER BY l.`follow_up_date` ASC, FIELD(l.`priority`,'urgent','high','medium','low')",
            ['u' => $userId, 'd' => $date]
        );
    }

    /** Recent non-spam enquiries WITHIN the caller's visibility scope (dashboard). */
    public function recent(array $scope, int $limit = 5): array
    {
        $limit = max(1, min($limit, 50));
        return $this->db->select(
            "SELECT l.`id`, l.`reference`, l.`name`, l.`email`, l.`enquiry_type`, l.`created_at`
             FROM `leads` l
             WHERE l.`deleted_at` IS NULL AND l.`is_spam` = 0 AND (" . $this->scopeSql($scope) . ")
             ORDER BY l.`id` DESC LIMIT {$limit}"
        );
    }

    // --- Lookups for filters/forms ------------------------------------------

    public function statuses(): array { return $this->db->select("SELECT * FROM `lead_statuses` ORDER BY `sort_order`"); }
    public function sources(): array  { return $this->db->select("SELECT * FROM `lead_sources` WHERE `is_active`=1 ORDER BY `name`"); }

    /** Active users that can hold leads (for the assign dropdown). */
    public function assignableUsers(): array
    {
        return $this->db->select(
            "SELECT DISTINCT u.id, u.`name`, u.`email` FROM `users` u
             JOIN `user_roles` ur ON ur.user_id = u.id
             JOIN `role_permissions` rp ON rp.role_id = ur.role_id
             JOIN `permissions` pm ON pm.id = rp.permission_id
             WHERE u.`is_active`=1 AND u.`deleted_at` IS NULL AND pm.`key` IN ('leads.view','leads.edit')
             ORDER BY u.`name`"
        );
    }

    /** Count recent leads from an IP within N seconds — rate-limit backstop. */
    public function countRecentByIp(string $ip, int $seconds): int
    {
        $seconds = max(1, $seconds);
        $row = $this->db->selectOne(
            "SELECT COUNT(*) c FROM `leads` WHERE `ip` = :ip AND `created_at` >= (NOW() - INTERVAL {$seconds} SECOND)",
            ['ip' => $ip]
        );
        return (int) ($row['c'] ?? 0);
    }
}
