<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RedirectRepository;
use App\Repositories\TherapeuticAreaRepository;
use App\Support\HtmlSanitizer;
use App\Support\Str;

/**
 * Therapeutic-area management. Slug changes create a redirect from the old public
 * URL. Every change is audited. No medical claims are generated.
 */
final class TherapeuticAreaService
{
    private const STATUSES = ['draft', 'published', 'archived'];

    public function __construct(
        private readonly TherapeuticAreaRepository $areas,
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
        $id = $this->areas->create([
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
        $this->audit->log('THERAPEUTIC_AREA_CREATED', ['entity_type' => 'therapeutic_area', 'entity_id' => $id, 'meta' => ['name' => $name]]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok:bool,error?:string} */
    public function update(int $id, array $in, int $actorId): array
    {
        $existing = $this->areas->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Therapeutic area not found.'];
        }
        $name = trim((string) ($in['name'] ?? '')) ?: (string) $existing['name'];
        $newSlug = $this->uniqueSlug((string) ($in['slug'] ?? '') ?: $name, $id);
        $oldSlug = (string) $existing['slug'];

        $this->areas->update($id, [
            'name'             => mb_substr($name, 0, 150),
            'slug'             => $newSlug,
            'description'      => $this->cleanRich($in['description'] ?? ''),
            'image_id'         => $this->validMediaId($in['image_id'] ?? 0),
            'meta_title'       => $this->nstr($in['meta_title'] ?? '', 255),
            'meta_description' => $this->nstr($in['meta_description'] ?? '', 320),
            'status'           => $this->status($in['status'] ?? $existing['status']),
            'sort_order'       => (int) ($in['sort_order'] ?? 0),
        ]);
        if ($newSlug !== $oldSlug && $this->redirects->findByPath('/therapeutic-area/' . $oldSlug) === null) {
            $this->redirects->create(['from_path' => '/therapeutic-area/' . $oldSlug, 'to_url' => '/therapeutic-area/' . $newSlug, 'code' => 301, 'is_active' => 1, 'created_by' => $actorId]);
        }
        $this->audit->log('THERAPEUTIC_AREA_CHANGED', ['entity_type' => 'therapeutic_area', 'entity_id' => $id]);
        return ['ok' => true];
    }

    public function setStatus(int $id, string $status, int $actorId): array
    {
        if (!in_array($status, self::STATUSES, true) || $this->areas->findById($id) === null) {
            return ['ok' => false, 'error' => 'Invalid request.'];
        }
        $this->areas->setStatus($id, $status);
        $this->audit->log('THERAPEUTIC_AREA_CHANGED', ['entity_type' => 'therapeutic_area', 'entity_id' => $id, 'meta' => ['status' => $status]]);
        return ['ok' => true];
    }

    public function delete(int $id, int $actorId): array
    {
        if ($this->areas->findById($id) === null) {
            return ['ok' => false, 'error' => 'Therapeutic area not found.'];
        }
        $this->areas->softDelete($id);
        $this->audit->log('THERAPEUTIC_AREA_CHANGED', ['entity_type' => 'therapeutic_area', 'entity_id' => $id, 'meta' => ['archived' => true]]);
        return ['ok' => true];
    }

    private function uniqueSlug(string $base, ?int $exceptId): string
    {
        $slug = Str::slug($base) ?: 'area';
        $slug = mb_substr($slug, 0, 170);
        $candidate = $slug; $i = 2;
        while ($this->areas->findBySlug($candidate, $exceptId) !== null) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    private function status(string $s): string { return in_array($s, self::STATUSES, true) ? $s : 'draft'; }
    private function validMediaId(mixed $v): ?int { $i = (int) $v; return $i > 0 && $this->mediaRepo->findActive($i) !== null ? $i : null; }
    private function nstr(mixed $v, int $max): ?string { $s = trim((string) $v); return $s === '' ? null : mb_substr($s, 0, $max); }
    private function cleanRich(mixed $v): ?string { $s = trim((string) $v); return $s === '' ? null : HtmlSanitizer::clean($s); }
}
