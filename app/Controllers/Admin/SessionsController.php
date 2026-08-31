<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserSessionRepository;

/**
 * Active-session management (Phase 2 §15). Any authenticated user can view and
 * revoke THEIR OWN sessions; users with users.view see all sessions, and users
 * with users.edit may revoke others'. Revoked sessions are logged out on their
 * next request by the TrackSession middleware. Session ids are never exposed.
 */
final class SessionsController extends AdminController
{
    private function sessions(): UserSessionRepository { return $this->container->get(UserSessionRepository::class); }

    public function index(Request $request): Response
    {
        $uid = (int) $this->currentUserId();
        $seeAll = $this->can('users.view');
        $currentSid = hash('sha256', $this->container->get(\App\Core\Session::class)->id());

        if ($seeAll) {
            $page = max(1, (int) $request->query('page', 1));
            $perPage = 30;
            $result = $this->sessions()->paginateAll($perPage, ($page - 1) * $perPage);
            $rows = $result['rows'];
            $total = $result['total'];
            $totalPages = (int) max(1, ceil($total / $perPage));
        } else {
            $rows = $this->sessions()->activeForUser($uid);
            $total = count($rows);
            $page = 1; $totalPages = 1;
        }

        return $this->adminView('admin.sessions.index', [
            'title'       => 'Active Sessions',
            'rows'        => $rows,
            'total'       => $total,
            'seeAll'      => $seeAll,
            'currentSid'  => $currentSid,
            'canRevokeOthers' => $this->can('users.edit'),
            'page'        => $page,
            'totalPages'  => $totalPages,
        ], '');
    }

    public function revoke(Request $request): Response
    {
        $id = (int) $request->route('id');
        $row = $this->sessions()->findById($id);
        if ($row === null) {
            $this->flash('error', 'Session not found.');
            return Response::redirect('/admin/sessions');
        }

        $isOwn = (int) $row['user_id'] === (int) $this->currentUserId();
        if (!$isOwn && !$this->can('users.edit')) {
            // Authorization: cannot revoke another user's session without users.edit.
            $this->flash('error', 'You are not allowed to revoke that session.');
            return Response::redirect('/admin/sessions');
        }

        $this->sessions()->revokeById($id);
        $this->audit('SESSION_REVOKED', ['entity_type' => 'user_session', 'entity_id' => $id, 'meta' => ['own' => $isOwn]]);
        $this->flash('success', 'Session revoked.');
        return Response::redirect('/admin/sessions');
    }
}
