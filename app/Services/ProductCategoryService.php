<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductCategoryRepository;
use App\Repositories\RedirectRepository;
use App\Support\HtmlSanitizer;
use App\Support\Str;

/**
 * Nested product-category management. Prevents circular hierarchy (a category
 * cannot be its own parent or a descendant's parent). Slug changes create a
 * redirect from the old public URL. Every change is audited.
 */
final class ProductCategoryService
{
    private const STATUSES = ['draft', 'published', 'archived'];

    public function __construct(
        private readonly ProductCategoryRepository $categories,
        private readonly RedirectRepository $redirects,
        private readonly AuditService $audit,
        private readonly \App\Repositories\MediaRepository $mediaRepo,
    ) {
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public function create(array $in, int $actorId): array
    {
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'Name is required.'];
        }
        $slug = $this->uniqueSlug((string) ($in['slug'] ?? '') ?: $name, null);
        $parentId = (int) ($in['parent_id'] ?? 0);

        $id = $this->categories->create([
            'parent_id'        => $parentId > 0 ? $parentId : null,
            'name'             => mb_substr($name, 0, 150),
            'slug'             => $slug,
            'description'      => $this->cleanRich($in['description'] ?? ''),
            'image_id'         => $this->validMediaId($in['image_id'] ?? 0),
            'meta_title'       => $this->nstr($in['meta_title'] ?? '', 255),
            'meta_description' => $this->nstr($in['meta_description'] ?? '', 320),
            'status'           => $this->status($in['status'] ?? 'draft'),
            'sort_order'       => (int) ($in['sort_order'] ?? 0),
            'is_demo'          => !empty($in['is_demo']) ? 1 : 0,
        ]);
        $this->audit->log('CATEGORY_CREATED', ['entity_type' => 'product_category', 'entity_id' => $id, 'meta' => ['name' => $name]]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok:bool,error?:string} */
    public function update(int $id, array $in, int $actorId): array
    {
        $existing = $this->categories->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Category not found.'];
        }

        $parentId = (int) ($in['parent_id'] ?? 0);
        if ($parentId > 0 && $this->categories->wouldCreateCycle($id, $parentId)) {
            return ['ok' => false, 'error' => 'That parent would create a circular hierarchy.'];
        }

        $name = trim((string) ($in['name'] ?? '')) ?: (string) $existing['name'];
        $newSlug = $this->uniqueSlug((string) ($in['slug'] ?? '') ?: $name, $id);
        $oldSlug = (string) $existing['slug'];

        $this->categories->update($id, [
            'parent_id'        => $parentId > 0 ? $parentId : null,
            'name'             => mb_substr($name, 0, 150),
            'slug'             => $newSlug,
            'description'      => $this->cleanRich($in['description'] ?? ''),
            'image_id'         => $this->validMediaId($in['image_id'] ?? 0),
            'meta_title'       => $this->nstr($in['meta_title'] ?? '', 255),
            'meta_description' => $this->nstr($in['meta_description'] ?? '', 320),
            'status'           => $this->status($in['status'] ?? $existing['status']),
            'sort_order'       => (int) ($in['sort_order'] ?? 0),
        ]);

        if ($newSlug !== $oldSlug) {
            $this->makeRedirect('/product-category/' . $oldSlug, '/product-category/' . $newSlug, $actorId);
        }
        $this->audit->log('CATEGORY_UPDATED', ['entity_type' => 'product_category', 'entity_id' => $id]);
        return ['ok' => true];
    }

    public function setStatus(int $id, string $status, int $actorId): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }
        if ($this->categories->findById($id) === null) {
            return ['ok' => false, 'error' => 'Category not found.'];
        }
        $this->categories->setStatus($id, $status);
        $this->audit->log('CATEGORY_UPDATED', ['entity_type' => 'product_category', 'entity_id' => $id, 'meta' => ['status' => $status]]);
        return ['ok' => true];
    }

    public function delete(int $id, int $actorId): array
    {
        if ($this->categories->findById($id) === null) {
            return ['ok' => false, 'error' => 'Category not found.'];
        }
        $this->categories->softDelete($id);
        $this->audit->log('CATEGORY_ARCHIVED', ['entity_type' => 'product_category', 'entity_id' => $id]);
        return ['ok' => true];
    }

    private function makeRedirect(string $from, string $to, int $actorId): void
    {
        if (rtrim($from, '/') === rtrim($to, '/') || $this->redirects->findByPath($from) !== null) {
            return;
        }
        $this->redirects->create(['from_path' => $from, 'to_url' => $to, 'code' => 301, 'is_active' => 1, 'created_by' => $actorId]);
    }

    private function uniqueSlug(string $base, ?int $exceptId): string
    {
        $slug = Str::slug($base) ?: 'category';
        $slug = mb_substr($slug, 0, 170);
        $candidate = $slug;
        $i = 2;
        while ($this->categories->findBySlug($candidate, $exceptId) !== null) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    private function status(string $s): string { return in_array($s, self::STATUSES, true) ? $s : 'draft'; }
    private function validMediaId(mixed $v): ?int { $i = (int) $v; return $i > 0 && $this->mediaRepo->findActive($i) !== null ? $i : null; }
    private function nstr(mixed $v, int $max): ?string { $s = trim((string) $v); return $s === '' ? null : mb_substr($s, 0, $max); }
    private function cleanRich(mixed $v): ?string { $s = trim((string) $v); return $s === '' ? null : HtmlSanitizer::clean($s); }
}
