<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Container;
use App\Core\Request;
use App\Repositories\AuditRepository;

/**
 * Centralised, append-only audit logging (Phase 2 §11). Records who/what/when.
 * NEVER records passwords, reset tokens, SMTP passwords, API secrets, or auth
 * tokens — meta is filtered before persistence.
 */
final class AuditService
{
    private const REDACT = '/pass|secret|token|api[_-]?key|authorization|hash|otp/i';

    public function __construct(
        private readonly AuditRepository $repo,
        private readonly Container $container,
    ) {
    }

    /**
     * @param array{entity_type?:string,entity_id?:int,meta?:array<string,mixed>,actor_id?:int,ip?:string,user_agent?:string} $opts
     */
    public function log(string $event, array $opts = []): void
    {
        $actorId = $opts['actor_id'] ?? $this->currentUserId();
        $ip = $opts['ip'] ?? $this->requestValue('ip');
        $ua = $opts['user_agent'] ?? $this->requestValue('userAgent');

        $meta = $this->sanitizeMeta($opts['meta'] ?? []);

        try {
            $this->repo->record([
                'user_id'     => $actorId,
                'event'       => mb_substr($event, 0, 60),
                'entity_type' => isset($opts['entity_type']) ? mb_substr((string) $opts['entity_type'], 0, 60) : null,
                'entity_id'   => isset($opts['entity_id']) ? (int) $opts['entity_id'] : null,
                'ip'          => $ip !== null ? mb_substr($ip, 0, 45) : null,
                'user_agent'  => $ua !== null ? mb_substr($ua, 0, 255) : null,
                'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
        } catch (\Throwable) {
            // Auditing must never break the primary operation.
        }
    }

    private function currentUserId(): ?int
    {
        try {
            return $this->container->get(Auth::class)->id();
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestValue(string $method): ?string
    {
        try {
            if ($this->container->has(Request::class)) {
                /** @var Request $r */
                $r = $this->container->get(Request::class);
                return (string) $r->{$method}();
            }
        } catch (\Throwable) {
        }
        return null;
    }

    /** Drop any key that looks sensitive, and cap scalar sizes. */
    private function sanitizeMeta(array $meta): array
    {
        $out = [];
        foreach ($meta as $k => $v) {
            if (preg_match(self::REDACT, (string) $k)) {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $out[$k] = is_string($v) ? mb_substr($v, 0, 500) : $v;
            } elseif (is_array($v)) {
                $out[$k] = $this->sanitizeMeta($v);
            }
        }
        return $out;
    }
}
