<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\MediaRepository;
use App\Services\MediaService;

/**
 * Media library: secure uploads, listing, alt-text, delete. Upload validation
 * (MIME/extension/size, safe names, no execution) lives in MediaService.
 */
final class MediaController extends AdminController
{
    private function media(): MediaRepository { return $this->container->get(MediaRepository::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('media.view');

        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 24;
        $result = $this->media()->paginate($search, $perPage, ($page - 1) * $perPage);
        $totalPages = (int) max(1, ceil($result['total'] / $perPage));

        return $this->adminView('admin.media.index', [
            'title'      => 'Media',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'search'     => $search,
            'page'       => $page,
            'totalPages' => $totalPages,
        ], 'media');
    }

    public function upload(Request $request): Response
    {
        $this->requirePermission('media.upload');

        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            $this->flash('error', 'No file received.');
            return Response::redirect('/admin/media');
        }

        /** @var MediaService $service */
        $service = $this->container->get(MediaService::class);
        $result = $service->handleUpload($file, $this->currentUserId(), $this->str($request->input('alt_text')));

        if (!($result['ok'] ?? false)) {
            $this->flash('error', $result['error'] ?? 'Upload failed.');
        } else {
            $this->audit('MEDIA_UPLOADED', ['entity_type' => 'media', 'entity_id' => (int) ($result['id'] ?? 0)]);
            $this->flash('success', 'File uploaded.');
        }
        return Response::redirect('/admin/media');
    }

    public function updateMeta(Request $request): Response
    {
        $this->requirePermission('media.upload');
        $id = (int) $request->route('id');
        if ($this->media()->findActive($id) === null) {
            $this->flash('error', 'Media not found.');
            return Response::redirect('/admin/media');
        }
        $this->media()->updateMeta(
            $id,
            $this->str($request->input('alt_text'), 255),
            $this->str($request->input('title'), 255),
        );
        $this->flash('success', 'Media details saved.');
        return Response::redirect('/admin/media');
    }

    public function delete(Request $request): Response
    {
        $this->requirePermission('media.delete');
        $id = (int) $request->route('id');
        $this->container->get(MediaService::class)->delete($id);
        $this->audit('MEDIA_DELETED', ['entity_type' => 'media', 'entity_id' => $id]);
        $this->flash('success', 'Media deleted.');
        return Response::redirect('/admin/media');
    }

    private function str(mixed $v, int $max = 255): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
