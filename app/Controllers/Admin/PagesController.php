<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PageRepository;
use App\Repositories\PageSectionRepository;
use App\Services\SectionRegistry;
use App\Support\HtmlSanitizer;
use App\Support\Str;

/**
 * CMS Pages: list / create / edit / status / delete, plus modular section
 * management. Every action re-checks the pages.manage permission (defence in
 * depth). All writes are CSRF-protected by global middleware.
 */
final class PagesController extends AdminController
{
    private const STATUSES = ['draft', 'published', 'archived'];

    private function pages(): PageRepository            { return $this->container->get(PageRepository::class); }
    private function sections(): PageSectionRepository  { return $this->container->get(PageSectionRepository::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('pages.view');

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $status = in_array($status, self::STATUSES, true) ? $status : '';
        $page   = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $result = $this->pages()->paginateForAdmin($search, $status, $perPage, ($page - 1) * $perPage);
        $totalPages = (int) max(1, ceil($result['total'] / $perPage));

        return $this->adminView('admin.pages.index', [
            'title'      => 'Pages',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'search'     => $search,
            'status'     => $status,
            'page'       => $page,
            'totalPages' => $totalPages,
        ], 'pages');
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('pages.create');
        return $this->adminView('admin.pages.create', ['title' => 'New Page'], 'pages');
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('pages.create');

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $this->flash('error', 'Title is required.');
            return Response::redirect('/admin/pages/create');
        }

        $slug = $this->uniqueSlug((string) $request->input('slug', '') ?: $title, null);

        $id = $this->pages()->create([
            'title'             => mb_substr($title, 0, 200),
            'slug'              => $slug,
            'status'            => 'draft',
            'template'          => $this->cleanTemplate((string) $request->input('template', 'default')),
            'content'           => null,
            'is_home'           => 0,
            'meta_title'        => null,
            'meta_description'  => null,
            'canonical_url'     => null,
            'robots'            => null,
            'og_image_id'       => null,
            'featured_image_id' => null,
            'published_at'      => null,
            'created_by'        => $this->currentUserId(),
            'updated_by'        => $this->currentUserId(),
        ]);

        $this->flash('success', 'Page created. Add content below.');
        return Response::redirect('/admin/pages/' . $id . '/edit');
    }

    public function edit(Request $request): Response
    {
        $this->requirePermission('pages.view');
        $id = (int) $request->route('id');
        $page = $this->pages()->findById($id);
        if ($page === null) {
            throw new HttpException(404);
        }

        $sections = $this->sections()->allForPage($id);

        return $this->adminView('admin.pages.edit', [
            'title'      => 'Edit: ' . $page['title'],
            'page'       => $page,
            'sections'   => $sections,
            'statuses'   => self::STATUSES,
            'sectionTypes' => SectionRegistry::types(),
        ], 'pages');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('pages.edit');
        $id = (int) $request->route('id');
        $page = $this->pages()->findById($id);
        if ($page === null) {
            throw new HttpException(404);
        }

        $title = trim((string) $request->input('title', '')) ?: $page['title'];
        $slug  = $this->uniqueSlug((string) $request->input('slug', '') ?: $title, $id);
        $isHome = $request->input('is_home') ? 1 : 0;

        $this->pages()->update($id, [
            'title'             => mb_substr($title, 0, 200),
            'slug'              => $slug,
            'status'            => in_array($request->input('status'), self::STATUSES, true) ? (string) $request->input('status') : $page['status'],
            'template'          => $this->cleanTemplate((string) $request->input('template', $page['template'])),
            'content'           => ($c = trim((string) $request->input('content', ''))) !== '' ? HtmlSanitizer::clean($c) : null,
            'is_home'           => $isHome,
            'meta_title'        => $this->nullable($request->input('meta_title'), 255),
            'meta_description'  => $this->nullable($request->input('meta_description'), 320),
            'canonical_url'     => $this->nullable($request->input('canonical_url'), 255),
            'robots'            => $this->nullable($request->input('robots'), 60),
            'og_image_id'       => $this->nullableInt($request->input('og_image_id')),
            'featured_image_id' => $this->nullableInt($request->input('featured_image_id')),
            'published_at'      => $page['published_at'],
            'updated_by'        => $this->currentUserId(),
        ]);

        if ($isHome === 1) {
            $this->pages()->clearOtherHome($id);
        }

        $this->flash('success', 'Page updated.');
        return Response::redirect('/admin/pages/' . $id . '/edit');
    }

    public function status(Request $request): Response
    {
        $this->requirePermission('pages.publish');
        $id = (int) $request->route('id');
        if ($this->pages()->findById($id) === null) {
            throw new HttpException(404);
        }
        $status = (string) $request->input('status', 'draft');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'draft';
        }
        $this->pages()->setStatus($id, $status, $this->currentUserId());
        $this->audit($status === 'published' ? 'PAGE_PUBLISHED' : 'PAGE_UNPUBLISHED', ['entity_type' => 'page', 'entity_id' => $id, 'meta' => ['status' => $status]]);
        $this->flash('success', 'Page status set to ' . $status . '.');
        return Response::redirect('/admin/pages/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        $this->requirePermission('pages.delete');
        $id = (int) $request->route('id');
        $page = $this->pages()->findById($id);
        if ($page === null) {
            throw new HttpException(404);
        }
        if ((int) $page['is_home'] === 1) {
            $this->flash('error', 'You cannot delete the home page. Set another page as home first.');
            return Response::redirect('/admin/pages/' . $id . '/edit');
        }
        $this->pages()->softDelete($id);
        $this->flash('success', 'Page deleted.');
        return Response::redirect('/admin/pages');
    }

    // --- Sections ------------------------------------------------------------

    public function addSection(Request $request): Response
    {
        $this->requirePermission('pages.edit');
        $pageId = (int) $request->route('id');
        if ($this->pages()->findById($pageId) === null) {
            throw new HttpException(404);
        }
        $type = (string) $request->input('type', '');
        if (!SectionRegistry::exists($type)) {
            $this->flash('error', 'Unknown section type.');
            return Response::redirect('/admin/pages/' . $pageId . '/edit');
        }
        $existing = $this->sections()->allForPage($pageId);
        $sort = ((int) ($existing[array_key_last($existing)]['sort_order'] ?? 0)) + 10;
        $this->sections()->create($pageId, $type, json_encode(new \stdClass()), $sort, true);
        $this->flash('success', SectionRegistry::label($type) . ' section added.');
        return Response::redirect('/admin/pages/' . $pageId . '/edit#sections');
    }

    public function updateSection(Request $request): Response
    {
        $this->requirePermission('pages.edit');
        $sectionId = (int) $request->route('id');
        $pageId = (int) $request->input('page_id');

        // Scope check (IDOR): section must belong to the posted page.
        $section = $this->sections()->find($sectionId);
        if ($section === null || (int) $section['page_id'] !== $pageId) {
            throw new HttpException(404);
        }

        $type = (string) $section['type'];
        [$json, $error] = $this->buildSectionData($type, (array) $request->input('data', []));
        if ($error !== null) {
            $this->flash('error', $error);
            return Response::redirect('/admin/pages/' . $pageId . '/edit#sections');
        }

        $sort = (int) $request->input('sort_order', (int) $section['sort_order']);
        $visible = $request->input('is_visible') ? true : false;
        $this->sections()->update($sectionId, $json, $sort, $visible);

        $this->flash('success', 'Section saved.');
        return Response::redirect('/admin/pages/' . $pageId . '/edit#sections');
    }

    public function deleteSection(Request $request): Response
    {
        $this->requirePermission('pages.edit');
        $sectionId = (int) $request->route('id');
        $pageId = (int) $request->input('page_id');
        $this->sections()->delete($sectionId, $pageId);
        $this->flash('success', 'Section removed.');
        return Response::redirect('/admin/pages/' . $pageId . '/edit#sections');
    }

    // --- Helpers -------------------------------------------------------------

    /**
     * Build the JSON payload for a section from posted field values.
     * @return array{0:string,1:?string} [jsonString, error]
     */
    private function buildSectionData(string $type, array $post): array
    {
        $out = [];
        foreach (SectionRegistry::fields($type) as $name => $meta) {
            $ftype = $meta['type'];
            if ($ftype === 'repeater') {
                $raw = trim((string) ($post[$name] ?? ''));
                if ($raw === '') {
                    $out[$name] = [];
                    continue;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    return ['', "The '{$meta['label']}' items must be valid JSON."];
                }
                $out[$name] = $decoded;
            } elseif ($ftype === 'richtext') {
                $out[$name] = HtmlSanitizer::clean((string) ($post[$name] ?? ''));
            } elseif ($ftype === 'media') {
                $out[$name] = ($v = (int) ($post[$name] ?? 0)) > 0 ? $v : null;
            } else {
                $out[$name] = mb_substr((string) ($post[$name] ?? ''), 0, 2000);
            }
        }
        return [(string) json_encode($out, JSON_UNESCAPED_UNICODE), null];
    }

    private function uniqueSlug(string $base, ?int $exceptId): string
    {
        $slug = Str::slug($base) ?: 'page';
        $slug = mb_substr($slug, 0, 190);
        $candidate = $slug;
        $i = 2;
        while ($this->pages()->findBySlug($candidate, $exceptId) !== null) {
            $candidate = $slug . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    private function cleanTemplate(string $t): string
    {
        return in_array($t, ['default', 'contact'], true) ? $t : 'default';
    }

    private function nullable(mixed $v, int $max): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private function nullableInt(mixed $v): ?int
    {
        $i = (int) $v;
        return $i > 0 ? $i : null;
    }
}
