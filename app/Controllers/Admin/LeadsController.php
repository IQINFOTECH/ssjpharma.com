<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\LeadActivityRepository;
use App\Repositories\LeadRepository;
use App\Support\Csv;
use App\Support\EnquiryType;
use App\Support\LeadVisibility;

/**
 * Admin lead management (§16-21, §28; Phase 4.1 visibility hardening). Every
 * action is authenticated (middleware) and re-checks the leads.* permission here
 * (defence in depth). Additionally, a per-user VISIBILITY SCOPE (view_all vs
 * view_assigned vs none) is enforced in SQL for list, search, filter, pagination,
 * detail, every mutation, export and metrics — a user can only ever see or touch
 * leads within their scope. Lead data is never exposed publicly.
 */
final class LeadsController extends AdminController
{
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    private function leads(): LeadRepository            { return $this->container->get(LeadRepository::class); }
    private function activities(): LeadActivityRepository { return $this->container->get(LeadActivityRepository::class); }

    /** Current user's lead-visibility scope (permission-driven; user id from session). */
    private function scope(): array
    {
        return LeadVisibility::scope(fn (string $p): bool => $this->can($p), $this->currentUserId());
    }

    /**
     * Load a lead the current user is allowed to see, or throw 404. Used by the
     * detail view AND every mutation so an out-of-scope id is indistinguishable
     * from a non-existent one (no existence leak / IDOR).
     */
    private function findVisibleOr404(int $id): array
    {
        $lead = $this->leads()->findByIdInScope($id, $this->scope());
        if ($lead === null) {
            throw new HttpException(404);
        }
        return $lead;
    }

    public function index(Request $request): Response
    {
        $this->requirePermission('leads.view');

        $filters = [
            'q'            => trim((string) $request->query('q', '')),
            'status'       => (int) $request->query('status', 0),
            'priority'     => in_array($request->query('priority'), self::PRIORITIES, true) ? (string) $request->query('priority') : '',
            'source'       => (int) $request->query('source', 0),
            'enquiry_type' => in_array($request->query('enquiry_type'), EnquiryType::all(), true) ? (string) $request->query('enquiry_type') : '',
            'assigned'     => (string) $request->query('assigned', ''),
            'product'      => (int) $request->query('product', 0),
            'from'         => $this->date((string) $request->query('from', '')),
            'to'           => $this->date((string) $request->query('to', '')),
            'sort'         => (string) $request->query('sort', ''),
            'spam'         => $request->query('spam') === '1' ? 'include' : 'exclude',
            'followup'     => in_array($request->query('followup'), ['today', 'overdue', 'due', 'next7', 'none'], true) ? (string) $request->query('followup') : '',
            // Server-side visibility scope — client can never widen it.
            'scope'        => $this->scope(),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;
        $result = $this->leads()->paginate($filters, $perPage, ($page - 1) * $perPage);

        /** @var Database $db */
        $db = $this->container->get(Database::class);

        $scope = $filters['scope'];

        return $this->adminView('admin.leads.index', [
            'title'      => 'Leads',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'filters'    => $filters,
            'page'       => $page,
            'totalPages' => (int) max(1, ceil($result['total'] / $perPage)),
            'statuses'   => $this->leads()->statuses(),
            'sources'    => $this->leads()->sources(),
            'users'      => $this->leads()->assignableUsers(),
            'products'   => $db->select("SELECT id, name FROM products WHERE deleted_at IS NULL ORDER BY name LIMIT 500"),
            'enquiryTypes' => EnquiryType::all(),
            'metrics'    => $this->leads()->metrics($scope),
            'scopeMode'  => $scope['mode'],
            'canSeeAny'  => LeadVisibility::canSeeAny($scope),
            'canEdit'    => $this->can('leads.edit'),
            'canAssign'  => $this->can('leads.assign'),
            'canDelete'  => $this->can('leads.delete'),
            // Export is allowed only if the user both holds the permission AND can see leads.
            'canExport'  => $this->can('leads.export') && LeadVisibility::canSeeAny($scope),
        ], 'leads');
    }

    /** Granular action-permission flags shared by the detail view. */
    private function actionFlags(): array
    {
        return [
            'canEdit'     => $this->can('leads.edit'),
            'canAssign'   => $this->can('leads.assign'),
            'canDelete'   => $this->can('leads.delete'),
            'canStatus'   => $this->can('leads.status'),
            'canPriority' => $this->can('leads.priority'),
            'canNotes'    => $this->can('leads.notes'),
        ];
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('leads.view');
        $id = (int) $request->route('id');
        $lead = $this->findVisibleOr404($id);
        return $this->adminView('admin.leads.show', array_merge([
            'title'      => 'Lead ' . $lead['reference'],
            'lead'       => $lead,
            'activities' => $this->activities()->forLead($id),
            'statuses'   => $this->leads()->statuses(),
            'priorities' => self::PRIORITIES,
            'users'      => $this->leads()->assignableUsers(),
        ], $this->actionFlags()), 'leads');
    }

    public function updateStatus(Request $request): Response
    {
        $this->requirePermission('leads.status');
        $id = (int) $request->route('id');
        $lead = $this->findVisibleOr404($id);
        $statusId = (int) $request->input('status_id', 0);
        $status = $this->statusById($statusId);
        if ($status === null) {
            $this->flash('error', 'Invalid status.');
            return Response::redirect('/admin/leads/' . $id);
        }
        $this->leads()->updateStatus($id, $statusId);
        $this->leads()->touchUpdated($id);
        $this->activities()->add($id, $this->currentUserId(), 'status_changed',
            'Status changed to ' . $status['name'] . '.', ['from' => $lead['status_key'] ?? null, 'to' => $status['key']]);
        $this->audit('LEAD_STATUS_CHANGED', ['entity_type' => 'lead', 'entity_id' => $id, 'meta' => ['status' => $status['key']]]);
        $this->flash('success', 'Status updated.');
        return Response::redirect('/admin/leads/' . $id);
    }

    public function updatePriority(Request $request): Response
    {
        $this->requirePermission('leads.priority');
        $id = (int) $request->route('id');
        $this->findVisibleOr404($id);
        $priority = (string) $request->input('priority', 'medium');
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'medium';
        }
        $this->leads()->updatePriority($id, $priority);
        $this->leads()->touchUpdated($id);
        $this->activities()->add($id, $this->currentUserId(), 'priority_changed', 'Priority set to ' . ucfirst($priority) . '.');
        $this->audit('LEAD_UPDATED', ['entity_type' => 'lead', 'entity_id' => $id, 'meta' => ['priority' => $priority]]);
        $this->flash('success', 'Priority updated.');
        return Response::redirect('/admin/leads/' . $id);
    }

    public function assign(Request $request): Response
    {
        $this->requirePermission('leads.assign');
        $id = (int) $request->route('id');
        $this->findVisibleOr404($id);
        $userId = (int) $request->input('assigned_user_id', 0);

        // Validate the assignee is an actual assignable user.
        $valid = null;
        foreach ($this->leads()->assignableUsers() as $u) {
            if ((int) $u['id'] === $userId) { $valid = $u; break; }
        }
        if ($userId === 0) {
            $this->leads()->assign($id, null);
            $this->activities()->add($id, $this->currentUserId(), 'unassigned', 'Lead unassigned.');
            $this->audit('LEAD_ASSIGNED', ['entity_type' => 'lead', 'entity_id' => $id, 'meta' => ['assigned_to' => null]]);
            $this->flash('success', 'Lead unassigned.');
        } elseif ($valid !== null) {
            $this->leads()->assign($id, $userId);
            $this->activities()->add($id, $this->currentUserId(), 'assigned', 'Assigned to ' . $valid['name'] . '.', ['assigned_to' => $userId]);
            $this->audit('LEAD_ASSIGNED', ['entity_type' => 'lead', 'entity_id' => $id, 'meta' => ['assigned_to' => $userId]]);
            $this->flash('success', 'Lead assigned.');
        } else {
            $this->flash('error', 'Invalid assignee.');
        }
        $this->leads()->touchUpdated($id);
        return Response::redirect('/admin/leads/' . $id);
    }

    public function addNote(Request $request): Response
    {
        $this->requirePermission('leads.notes');
        $id = (int) $request->route('id');
        $this->findVisibleOr404($id);
        $body = trim((string) $request->input('note', ''));
        if ($body === '') {
            $this->flash('error', 'Note cannot be empty.');
            return Response::redirect('/admin/leads/' . $id);
        }
        // Stored as an activity; rendered escaped (no stored XSS).
        $this->activities()->add($id, $this->currentUserId(), 'note', mb_substr($body, 0, 5000));
        $this->leads()->touchUpdated($id);
        $this->audit('LEAD_NOTE_ADDED', ['entity_type' => 'lead', 'entity_id' => $id]);
        $this->flash('success', 'Note added.');
        return Response::redirect('/admin/leads/' . $id);
    }

    public function markContacted(Request $request): Response
    {
        $this->requirePermission('leads.edit');
        $id = (int) $request->route('id');
        $this->findVisibleOr404($id);
        $this->leads()->markContacted($id);
        $this->activities()->add($id, $this->currentUserId(), 'contacted', 'Marked as contacted.');
        $this->flash('success', 'Marked as contacted.');
        return Response::redirect('/admin/leads/' . $id);
    }

    public function updateFollowUp(Request $request): Response
    {
        $this->requirePermission('leads.edit');
        $id = (int) $request->route('id');
        $this->findVisibleOr404($id);
        $raw = trim((string) $request->input('follow_up_date', ''));
        // Accept a valid calendar date or blank (to clear). Reject anything else.
        $date = null;
        if ($raw !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', $raw);
            if ($d === false || $d->format('Y-m-d') !== $raw) {
                $this->flash('error', 'Invalid follow-up date.');
                return Response::redirect('/admin/leads/' . $id);
            }
            $date = $raw;
        }
        $this->leads()->updateFollowUp($id, $date);
        $this->leads()->touchUpdated($id);
        $this->activities()->add($id, $this->currentUserId(), 'followup_updated',
            $date !== null ? 'Follow-up set for ' . $date . '.' : 'Follow-up cleared.');
        $this->audit('LEAD_FOLLOWUP_UPDATED', ['entity_type' => 'lead', 'entity_id' => $id, 'meta' => ['follow_up_date' => $date]]);
        $this->flash('success', 'Follow-up updated.');
        return Response::redirect('/admin/leads/' . $id);
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('leads.delete');
        $id = (int) $request->route('id');
        $this->findVisibleOr404($id);
        $this->leads()->softDelete($id);
        $this->audit('LEAD_DELETED', ['entity_type' => 'lead', 'entity_id' => $id]);
        $this->flash('success', 'Lead deleted.');
        return Response::redirect('/admin/leads');
    }

    public function export(Request $request): Response
    {
        $this->requirePermission('leads.export');
        // Export can never reveal more than the user may view: same scope as the list.
        $scope = $this->scope();
        if (!LeadVisibility::canSeeAny($scope)) {
            throw new HttpException(403, 'You do not have access to any leads to export.');
        }
        $filters = [
            'q'      => trim((string) $request->query('q', '')),
            'status' => (int) $request->query('status', 0),
            'priority' => in_array($request->query('priority'), self::PRIORITIES, true) ? (string) $request->query('priority') : '',
            'source' => (int) $request->query('source', 0),
            'enquiry_type' => in_array($request->query('enquiry_type'), EnquiryType::all(), true) ? (string) $request->query('enquiry_type') : '',
            'assigned' => (string) $request->query('assigned', ''),
            'product' => (int) $request->query('product', 0),
            'from'   => $this->date((string) $request->query('from', '')),
            'to'     => $this->date((string) $request->query('to', '')),
            'spam'   => 'exclude',
            'scope'  => $scope,
        ];
        $rows = $this->leads()->exportRows($filters);

        $headers = ['Lead ID','Date','Name','Company','Email','Phone','Country','State','City','Enquiry Type','Product','Source','Status','Priority','Assigned User','Follow-up','UTM Source','UTM Medium','UTM Campaign'];
        $out = Csv::bom();
        $out .= Csv::line($headers);
        foreach ($rows as $r) {
            $out .= Csv::line([
                $r['reference'] ?? '', substr((string) ($r['created_at'] ?? ''), 0, 19),
                $r['name'] ?? '', $r['company'] ?? '', $r['email'] ?? '', $r['phone'] ?? '',
                $r['country'] ?? '', $r['state'] ?? '', $r['city'] ?? '', $r['enquiry_type'] ?? '',
                $r['product_name'] ?? '', $r['source_name'] ?? '', $r['status_name'] ?? '',
                $r['priority'] ?? '', $r['assigned_name'] ?? '', $r['follow_up_date'] ?? '',
                $r['utm_source'] ?? '', $r['utm_medium'] ?? '', $r['utm_campaign'] ?? '',
            ]);
        }

        $this->audit('LEADS_EXPORTED', ['entity_type' => 'lead', 'meta' => ['count' => count($rows)]]);

        return Response::make($out, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="leads-' . date('Ymd-His') . '.csv"',
        ]);
    }

    // --- helpers -------------------------------------------------------------

    private function statusById(int $id): ?array
    {
        foreach ($this->leads()->statuses() as $s) {
            if ((int) $s['id'] === $id) { return $s; }
        }
        return null;
    }

    private function date(string $v): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }
}
