<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditRepository;

/**
 * Audit log viewer (Phase 2 §12). READ-ONLY — records are immutable to the app
 * (no update/delete endpoints exist). Gated by audit.view.
 */
final class AuditController extends AdminController
{
    private function audits(): AuditRepository { return $this->container->get(AuditRepository::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('audit.view');

        $filters = [
            'q'       => trim((string) $request->query('q', '')),
            'user_id' => (int) $request->query('user_id', 0),
            'event'   => (string) $request->query('event', ''),
            'entity'  => (string) $request->query('entity', ''),
            'from'    => $this->date((string) $request->query('from', '')),
            'to'      => $this->date((string) $request->query('to', '')),
        ];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;

        $result = $this->audits()->paginate($filters, $perPage, ($page - 1) * $perPage);

        /** @var Database $db */
        $db = $this->container->get(Database::class);
        $users = $db->select("SELECT id, name FROM users WHERE deleted_at IS NULL ORDER BY name");

        return $this->adminView('admin.audit.index', [
            'title'      => 'Audit Log',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'filters'    => $filters,
            'page'       => $page,
            'totalPages' => (int) max(1, ceil($result['total'] / $perPage)),
            'events'     => $this->audits()->distinctEvents(),
            'users'      => $users,
        ], 'audit');
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('audit.view');
        $id = (int) $request->route('id');
        $row = $this->audits()->find($id);
        if ($row === null) {
            throw new HttpException(404);
        }
        return $this->adminView('admin.audit.show', ['title' => 'Audit event #' . $id, 'row' => $row], 'audit');
    }

    private function date(string $v): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }
}
