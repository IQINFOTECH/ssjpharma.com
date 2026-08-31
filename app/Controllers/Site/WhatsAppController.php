<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\WhatsappClickRepository;
use App\Support\RateLimiter;

/**
 * WhatsApp CTA click tracking (§15). Best-effort, first-party. A recorded click
 * is explicitly NOT a lead. CSRF-protected (token from the page) + rate-limited.
 */
final class WhatsAppController extends Controller
{
    public function track(Request $request): Response
    {
        $ip = $request->ip();
        // Lightweight abuse guard (filesystem, GoDaddy-friendly).
        if (!(new RateLimiter())->attempt('waclick:' . $ip, 60, 600)) {
            return Response::make('', 204);
        }

        $context = (string) $request->input('context', 'general');
        $context = preg_match('/^[a-z_]{1,40}$/', $context) ? $context : 'general';

        // Resolve the product from the PAGE path (no DB id in the DOM, §14).
        $productId = 0;
        $page = (string) $request->input('page', '');
        if ($context === 'product' && preg_match('#^/products/([a-z0-9\-]{1,220})$#', $page, $m)) {
            $p = $this->container->get(\App\Repositories\ProductRepository::class)->findPublishedBySlug($m[1]);
            $productId = $p !== null ? (int) $p['id'] : 0;
        }

        try {
            $this->container->get(WhatsappClickRepository::class)->record([
                'context'      => $context,
                'page'         => (string) $request->input('page', ''),
                'product_id'   => $productId ?: null,
                'utm_source'   => (string) $request->input('utm_source', ''),
                'utm_medium'   => (string) $request->input('utm_medium', ''),
                'utm_campaign' => (string) $request->input('utm_campaign', ''),
                'ip'           => $ip,
                'user_agent'   => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Tracking must never break the user's WhatsApp flow.
        }

        return Response::make('', 204);
    }
}
