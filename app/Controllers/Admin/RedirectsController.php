<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\RedirectRepository;
use App\Services\RedirectService;

/**
 * CMS Redirects (301/302) with loop + open-redirect protection (RedirectService).
 */
final class RedirectsController extends AdminController
{
    private function redirects(): RedirectRepository { return $this->container->get(RedirectRepository::class); }
    private function service(): RedirectService       { return $this->container->get(RedirectService::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('redirects.view');
        return $this->adminView('admin.redirects.index', [
            'title' => 'Redirects',
            'rows'  => $this->redirects()->allForAdmin(),
        ], 'redirects');
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('redirects.create');

        $from = '/' . trim((string) $request->input('from_path', ''), '/');
        $to   = trim((string) $request->input('to_url', ''));
        $code = (int) $request->input('code', 301) === 302 ? 302 : 301;

        $error = $this->service()->validate($from, $to);
        if ($error !== null) {
            $this->flash('error', $error);
            return Response::redirect('/admin/redirects');
        }
        if ($this->redirects()->findByPath($from) !== null) {
            $this->flash('error', 'A redirect for that source path already exists.');
            return Response::redirect('/admin/redirects');
        }

        $this->redirects()->create([
            'from_path'  => mb_substr($from, 0, 255),
            'to_url'     => mb_substr($to, 0, 500),
            'code'       => $code,
            'is_active'  => $request->input('is_active') ? 1 : 1,
            'created_by' => $this->currentUserId(),
        ]);
        $this->flash('success', 'Redirect created.');
        return Response::redirect('/admin/redirects');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('redirects.edit');
        $id = (int) $request->route('id');
        $existing = $this->redirects()->find($id);
        if ($existing === null) {
            $this->flash('error', 'Redirect not found.');
            return Response::redirect('/admin/redirects');
        }

        $from = '/' . trim((string) $request->input('from_path', $existing['from_path']), '/');
        $to   = trim((string) $request->input('to_url', $existing['to_url']));
        $code = (int) $request->input('code', $existing['code']) === 302 ? 302 : 301;

        $error = $this->service()->validate($from, $to);
        if ($error !== null) {
            $this->flash('error', $error);
            return Response::redirect('/admin/redirects');
        }
        if ($this->redirects()->findByPath($from, $id) !== null) {
            $this->flash('error', 'Another redirect already uses that source path.');
            return Response::redirect('/admin/redirects');
        }

        $this->redirects()->update($id, [
            'from_path' => mb_substr($from, 0, 255),
            'to_url'    => mb_substr($to, 0, 500),
            'code'      => $code,
            'is_active' => $request->input('is_active') ? 1 : 0,
        ]);
        $this->flash('success', 'Redirect updated.');
        return Response::redirect('/admin/redirects');
    }

    public function delete(Request $request): Response
    {
        $this->requirePermission('redirects.delete');
        $this->redirects()->delete((int) $request->route('id'));
        $this->flash('success', 'Redirect deleted.');
        return Response::redirect('/admin/redirects');
    }
}
