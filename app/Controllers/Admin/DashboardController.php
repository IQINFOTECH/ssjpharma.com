<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\LeadRepository;
use App\Support\LeadVisibility;

/**
 * Admin dashboard: at-a-glance counts. Honest empty states — no invented data.
 */
final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->requirePermission('dashboard.view');
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        // Note: the company-wide lead COUNT is intentionally NOT included here —
        // it would leak a total to users outside their lead-visibility scope. The
        // scoped `leadMetrics['total']` below is the only lead figure shown.
        $counts = [
            'pages_published' => (int) ($db->selectOne("SELECT COUNT(*) c FROM pages WHERE status='published' AND deleted_at IS NULL")['c'] ?? 0),
            'pages_draft'     => (int) ($db->selectOne("SELECT COUNT(*) c FROM pages WHERE status='draft' AND deleted_at IS NULL")['c'] ?? 0),
            'media'           => (int) ($db->selectOne("SELECT COUNT(*) c FROM media WHERE deleted_at IS NULL")['c'] ?? 0),
            'redirects'       => (int) ($db->selectOne("SELECT COUNT(*) c FROM redirects WHERE is_active=1")['c'] ?? 0),
        ];

        // Lead metrics + recent enquiries respect the SAME visibility scope as
        // /admin/leads (Phase 4.1): a restricted user never sees company-wide
        // counts, and an assigned-only user sees only their own leads.
        $leadMetrics = null;
        $recentLeads = [];
        $scope = LeadVisibility::scope(fn (string $p): bool => $this->can($p), $this->auth()->id());
        $canSeeLeads = $this->can('leads.view') && LeadVisibility::canSeeAny($scope);
        if ($canSeeLeads) {
            $leads = $this->container->get(LeadRepository::class);
            $leadMetrics = $leads->metrics($scope);
            $recentLeads = $leads->recent($scope, 5);
        }

        return $this->adminView('admin.dashboard', [
            'title'       => 'Dashboard',
            'counts'      => $counts,
            'leadMetrics' => $leadMetrics,
            'recentLeads' => $recentLeads,
            'canViewLeads' => $canSeeLeads,
        ], 'dashboard');
    }
}
