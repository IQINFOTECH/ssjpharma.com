<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\DosageFormRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RedirectRepository;
use App\Repositories\TherapeuticAreaRepository;
use App\Support\HtmlSanitizer;
use App\Support\Str;
use App\Support\Validator;

/**
 * Product management (Phase 3). Handles CRUD, SEO-friendly unique slugs with
 * automatic redirects on change, therapeutic-area links, specifications, and
 * secure image/document uploads (delegated to MediaService). Every change is
 * audited. Pharmaceutical fields are OPTIONAL — only the name is required.
 */
final class ProductService
{
    private const STATUSES  = ['draft', 'in_review', 'approved', 'published', 'archived'];
    private const DOC_TYPES  = ['spec_sheet', 'brochure', 'technical', 'document'];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductCategoryRepository $categories,
        private readonly DosageFormRepository $dosageForms,
        private readonly TherapeuticAreaRepository $areas,
        private readonly RedirectRepository $redirects,
        private readonly MediaService $media,
        private readonly AuditService $audit,
        private readonly Logger $logger,
        private readonly \App\Repositories\MediaRepository $mediaRepo,
    ) {
    }

    /** @return array<string,string> validation errors */
    public function validate(array $in, ?int $exceptId = null): array
    {
        $v = new Validator();
        $v->validate($in, [
            'name'              => 'required|max:200',
            'code'              => 'max:80',
            'short_description' => 'max:500',
            'strength'          => 'max:120',
            'pack_size'         => 'max:120',
            'generic_name'      => 'max:255',
        ]);
        return $v->errors();
    }

    /** @return array{ok:bool,errors?:array<string,string>,id?:int} */
    public function create(array $in, array $taIds, array $specs, int $actorId): array
    {
        $errors = $this->validate($in);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }
        $slug = $this->uniqueSlug((string) ($in['slug'] ?? '') ?: (string) $in['name'], null);
        $data = $this->buildData($in, $slug, null, $actorId);
        $id = $this->products->create($data);

        $this->products->setTherapeuticAreas($id, $this->validTaIds($taIds));
        $this->products->replaceSpecifications($id, $specs);

        $this->audit->log('PRODUCT_CREATED', ['entity_type' => 'product', 'entity_id' => $id, 'meta' => ['name' => $in['name']]]);
        return ['ok' => true, 'id' => $id];
    }

    /** @return array{ok:bool,errors?:array<string,string>} */
    public function update(int $id, array $in, array $taIds, array $specs, int $actorId): array
    {
        $existing = $this->products->findById($id);
        if ($existing === null) {
            return ['ok' => false, 'errors' => ['_form' => 'Product not found.']];
        }
        $errors = $this->validate($in, $id);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $newSlug = $this->uniqueSlug((string) ($in['slug'] ?? '') ?: (string) $in['name'], $id);
        $oldSlug = (string) $existing['slug'];
        $data = $this->buildData($in, $newSlug, $existing, $actorId);
        $this->products->update($id, $data);

        $this->products->setTherapeuticAreas($id, $this->validTaIds($taIds));
        $this->products->replaceSpecifications($id, $specs);

        if ($newSlug !== $oldSlug && $this->redirects->findByPath('/products/' . $oldSlug) === null) {
            $this->redirects->create(['from_path' => '/products/' . $oldSlug, 'to_url' => '/products/' . $newSlug, 'code' => 301, 'is_active' => 1, 'created_by' => $actorId]);
        }
        $this->audit->log('PRODUCT_UPDATED', ['entity_type' => 'product', 'entity_id' => $id]);
        return ['ok' => true];
    }

    public function setStatus(int $id, string $status, int $actorId): array
    {
        if (!in_array($status, self::STATUSES, true) || $this->products->findById($id) === null) {
            return ['ok' => false, 'error' => 'Invalid request.'];
        }
        $this->products->setStatus($id, $status);
        $event = match ($status) {
            'in_review' => 'PRODUCT_SUBMITTED_REVIEW',
            'approved'  => 'PRODUCT_APPROVED',
            'published' => 'PRODUCT_PUBLISHED',
            'archived'  => 'PRODUCT_ARCHIVED',
            default     => 'PRODUCT_UNPUBLISHED',
        };
        $this->audit->log($event, ['entity_type' => 'product', 'entity_id' => $id, 'meta' => ['status' => $status]]);
        return ['ok' => true];
    }

    public function duplicate(int $id, int $actorId): array
    {
        $src = $this->products->findById($id);
        if ($src === null) {
            return ['ok' => false, 'error' => 'Product not found.'];
        }
        $name = mb_substr((string) $src['name'] . ' (Copy)', 0, 200);
        $slug = $this->uniqueSlug($name, null);

        $data = $this->buildData(array_merge($src, [
            'name'   => $name,
            'status' => 'draft',
            'code'   => $src['code'] ? $src['code'] . '-copy' : null,
        ]), $slug, null, $actorId);
        $data['published_at'] = null;
        $newId = $this->products->create($data);

        $this->products->setTherapeuticAreas($newId, $this->products->therapeuticAreaIds($id));
        $this->products->replaceSpecifications($newId, $this->products->specifications($id));

        $this->audit->log('PRODUCT_CREATED', ['entity_type' => 'product', 'entity_id' => $newId, 'meta' => ['duplicated_from' => $id]]);
        return ['ok' => true, 'id' => $newId];
    }

    public function delete(int $id, int $actorId): array
    {
        if ($this->products->findById($id) === null) {
            return ['ok' => false, 'error' => 'Product not found.'];
        }
        $this->products->softDelete($id);
        $this->audit->log('PRODUCT_ARCHIVED', ['entity_type' => 'product', 'entity_id' => $id, 'meta' => ['deleted' => true]]);
        return ['ok' => true];
    }

    // --- Images --------------------------------------------------------------

    /** @param array $file $_FILES entry @return array{ok:bool,error?:string} */
    public function addImage(int $productId, array $file, ?string $alt, bool $primary, int $actorId): array
    {
        if ($this->products->findById($productId) === null) {
            return ['ok' => false, 'error' => 'Product not found.'];
        }
        $res = $this->media->handleUpload($file, $actorId, $alt, ['jpg', 'jpeg', 'png', 'webp']);
        if (!($res['ok'] ?? false)) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Upload failed.'];
        }
        $mediaId = (int) $res['id'];
        if ($primary) {
            $this->products->clearPrimaryImages($productId);
            $this->syncHeroImage($productId, $mediaId);
        }
        $imgId = $this->products->addImage($productId, $mediaId, $alt, $primary);
        $this->audit->log('IMAGE_UPLOADED', ['entity_type' => 'product', 'entity_id' => $productId, 'meta' => ['image_id' => $imgId]]);
        return ['ok' => true];
    }

    public function setPrimaryImage(int $productId, int $imageId, int $actorId): array
    {
        $img = $this->products->findImage($imageId, $productId);
        if ($img === null) {
            return ['ok' => false, 'error' => 'Image not found.'];
        }
        $this->products->setPrimaryImage($imageId, $productId);
        $this->syncHeroImage($productId, (int) $img['media_id']);
        $this->audit->log('PRODUCT_UPDATED', ['entity_type' => 'product', 'entity_id' => $productId, 'meta' => ['primary_image' => $imageId]]);
        return ['ok' => true];
    }

    public function deleteImage(int $productId, int $imageId, int $actorId): array
    {
        $img = $this->products->findImage($imageId, $productId);
        if ($img === null) {
            return ['ok' => false, 'error' => 'Image not found.'];
        }
        $this->products->deleteImage($imageId, $productId);
        $this->media->delete((int) $img['media_id']);
        $this->audit->log('IMAGE_REMOVED', ['entity_type' => 'product', 'entity_id' => $productId, 'meta' => ['image_id' => $imageId]]);
        return ['ok' => true];
    }

    // --- Documents (PDF only) ------------------------------------------------

    public function addDocument(int $productId, array $file, string $displayName, string $docType, int $actorId): array
    {
        if ($this->products->findById($productId) === null) {
            return ['ok' => false, 'error' => 'Product not found.'];
        }
        $res = $this->media->handleUpload($file, $actorId, null, ['pdf']);
        if (!($res['ok'] ?? false)) {
            return ['ok' => false, 'error' => $res['error'] ?? 'Upload failed.'];
        }
        $displayName = trim($displayName) !== '' ? mb_substr(trim($displayName), 0, 200) : 'Document';
        $docType = in_array($docType, self::DOC_TYPES, true) ? $docType : 'document';
        $docId = $this->products->addDocument($productId, (int) $res['id'], $displayName, $docType, $actorId);
        $this->audit->log('DOCUMENT_UPLOADED', ['entity_type' => 'product', 'entity_id' => $productId, 'meta' => ['document_id' => $docId, 'type' => $docType]]);
        return ['ok' => true];
    }

    public function deleteDocument(int $productId, int $docId, int $actorId): array
    {
        $doc = $this->products->findDocument($docId, $productId);
        if ($doc === null) {
            return ['ok' => false, 'error' => 'Document not found.'];
        }
        $this->products->deleteDocument($docId, $productId);
        $this->media->delete((int) $doc['media_id']);
        $this->audit->log('DOCUMENT_REMOVED', ['entity_type' => 'product', 'entity_id' => $productId, 'meta' => ['document_id' => $docId]]);
        return ['ok' => true];
    }

    // --- Helpers -------------------------------------------------------------

    private function syncHeroImage(int $productId, int $mediaId): void
    {
        // Keep products.hero_image_id (used for cards/OG) aligned with the primary image.
        $p = $this->products->findById($productId);
        if ($p !== null) {
            $data = $this->rowToUpdate($p);
            $data['hero_image_id'] = $mediaId;
            $this->products->update($productId, $data);
        }
    }

    /** @param array<string,mixed> $existing @return array<string,mixed> */
    private function buildData(array $in, string $slug, ?array $existing, int $actorId): array
    {
        $status = in_array($in['status'] ?? '', self::STATUSES, true) ? (string) $in['status'] : ($existing['status'] ?? 'draft');
        $publishedAt = $existing['published_at'] ?? null;
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        return [
            'name'             => mb_substr(trim((string) $in['name']), 0, 200),
            'code'             => $this->nstr($in['code'] ?? '', 80),
            'slug'             => $slug,
            'short_description'=> $this->nstr($in['short_description'] ?? '', 500),
            'description'      => $this->cleanRich($in['description'] ?? ''),
            'status'           => $status,
            'is_featured'      => !empty($in['is_featured']) ? 1 : 0,
            'is_demo'          => !empty($in['is_demo']) ? 1 : (int) ($existing['is_demo'] ?? 0),
            'sort_order'       => (int) ($in['sort_order'] ?? ($existing['sort_order'] ?? 0)),
            'generic_name'     => $this->nstr($in['generic_name'] ?? '', 255),
            'composition'      => $this->nstr($in['composition'] ?? '', 5000),
            'strength'         => $this->nstr($in['strength'] ?? '', 120),
            'dosage_form_id'   => $this->validDosageId($in['dosage_form_id'] ?? 0),
            'pack_size'        => $this->nstr($in['pack_size'] ?? '', 120),
            'category_id'      => $this->validCategoryId($in['category_id'] ?? 0),
            'hero_image_id'    => $this->validMediaId($in['hero_image_id'] ?? ($existing['hero_image_id'] ?? 0)),
            'meta_title'       => $this->nstr($in['meta_title'] ?? '', 255),
            'meta_description' => $this->nstr($in['meta_description'] ?? '', 320),
            'canonical_url'    => $this->nstr($in['canonical_url'] ?? '', 255),
            'og_image_id'      => $this->validMediaId($in['og_image_id'] ?? 0),
            'robots'           => $this->nstr($in['robots'] ?? '', 60),
            'published_at'     => $publishedAt,
            'created_by'       => $existing === null ? $actorId : ($existing['created_by'] ?? $actorId),
            'updated_by'       => $actorId,
        ];
    }

    /** Convert a product row into an update payload (for hero-sync). */
    private function rowToUpdate(array $p): array
    {
        $keys = ['name','code','slug','short_description','description','status','is_featured','is_demo','sort_order',
                 'generic_name','composition','strength','dosage_form_id','pack_size','category_id','hero_image_id',
                 'meta_title','meta_description','canonical_url','og_image_id','robots','published_at'];
        $out = [];
        foreach ($keys as $k) { $out[$k] = $p[$k] ?? null; }
        $out['updated_by'] = $p['updated_by'] ?? null;
        return $out;
    }

    private function validTaIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $tid) {
            $tid = (int) $tid;
            if ($tid > 0 && $this->areas->findById($tid) !== null) { $out[] = $tid; }
        }
        return $out;
    }

    private function validCategoryId(mixed $v): ?int
    {
        $id = (int) $v;
        return $id > 0 && $this->categories->findById($id) !== null ? $id : null;
    }

    private function validDosageId(mixed $v): ?int
    {
        $id = (int) $v;
        return $id > 0 && $this->dosageForms->find($id) !== null ? $id : null;
    }

    /** Resolve a media id to itself only if the media exists (active) — else null.
     *  Prevents an invalid/tampered media reference from hitting the FK (was a 500). */
    private function validMediaId(mixed $v): ?int
    {
        $id = (int) $v;
        return $id > 0 && $this->mediaRepo->findActive($id) !== null ? $id : null;
    }

    private function uniqueSlug(string $base, ?int $exceptId): string
    {
        $slug = Str::slug($base) ?: 'product';
        $slug = mb_substr($slug, 0, 200);
        $candidate = $slug; $i = 2;
        while ($this->products->findBySlug($candidate, $exceptId) !== null) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    private function nid(mixed $v): ?int { $i = (int) $v; return $i > 0 ? $i : null; }
    private function nstr(mixed $v, int $max): ?string { $s = trim((string) $v); return $s === '' ? null : mb_substr($s, 0, $max); }
    private function cleanRich(mixed $v): ?string { $s = trim((string) $v); return $s === '' ? null : HtmlSanitizer::clean($s); }
}
