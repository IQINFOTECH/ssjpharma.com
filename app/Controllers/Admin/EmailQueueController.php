<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\EmailQueueRepository;
use App\Services\MailService;

/**
 * Email-queue monitoring (Phase 5, §17). Administrators can watch delivery, and
 * retry/cancel messages. Recipients are NEVER editable here (no recipient
 * manipulation). Every action re-checks its communications.* permission.
 */
final class EmailQueueController extends AdminController
{
    private const STATUSES = ['pending', 'processing', 'sent', 'failed', 'cancelled'];

    private function queue(): EmailQueueRepository { return $this->container->get(EmailQueueRepository::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('communications.view');
        $status = (string) $request->query('status', '');
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 30;
        $result = $this->queue()->paginate($status ?: null, $perPage, ($page - 1) * $perPage);

        return $this->adminView('admin.email_queue.index', [
            'title'       => 'Email Queue',
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'status'      => $status,
            'statuses'    => self::STATUSES,
            'counts'      => $this->queue()->statusCounts(),
            'page'        => $page,
            'totalPages'  => (int) max(1, ceil($result['total'] / $perPage)),
            'deliveryMode' => $this->container->get(MailService::class)->deliveryMode(),
            'canRetry'    => $this->can('communications.retry'),
        ], 'email_queue');
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('communications.view');
        $row = $this->queue()->findById((int) $request->route('id'));
        if ($row === null) {
            throw new HttpException(404);
        }
        return $this->adminView('admin.email_queue.show', [
            'title'    => 'Email #' . (int) $row['id'],
            'row'      => $row,
            'canRetry' => $this->can('communications.retry'),
        ], 'email_queue');
    }

    public function retry(Request $request): Response
    {
        $this->requirePermission('communications.retry');
        $id = (int) $request->route('id');
        if ($this->queue()->findById($id) === null) {
            throw new HttpException(404);
        }
        $this->queue()->requeue($id);
        $this->audit('COMM_EMAIL_RETRIED', ['entity_type' => 'email_queue', 'entity_id' => $id]);
        $this->flash('success', 'Message requeued.');
        return Response::redirect('/admin/email-queue/' . $id);
    }

    public function cancel(Request $request): Response
    {
        $this->requirePermission('communications.retry');
        $id = (int) $request->route('id');
        if ($this->queue()->findById($id) === null) {
            throw new HttpException(404);
        }
        $this->queue()->cancel($id);
        $this->audit('COMM_EMAIL_CANCELLED', ['entity_type' => 'email_queue', 'entity_id' => $id]);
        $this->flash('success', 'Message cancelled.');
        return Response::redirect('/admin/email-queue/' . $id);
    }
}
