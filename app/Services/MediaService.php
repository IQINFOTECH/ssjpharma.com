<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Repositories\MediaRepository;
use RuntimeException;

/**
 * Handles secure media uploads (SECURITY_PLAN §9):
 *  - extension allowlist + real MIME check (finfo), not just the client's word
 *  - size limit, random filenames, no user-controlled paths (no traversal)
 *  - uploads land under public/uploads where PHP execution is disabled (.htaccess)
 *  - SVGs are scrubbed of scripts/handlers before being written
 */
final class MediaService
{
    private const MAX_BYTES = 8 * 1024 * 1024; // 8 MB

    /** extension => allowed MIME types */
    private const ALLOWED = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'svg'  => ['image/svg+xml', 'text/plain', 'text/xml', 'application/xml'],
        'pdf'  => ['application/pdf'],
    ];

    public function __construct(
        private readonly MediaRepository $media,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return array{ok:bool,error?:string,id?:int}
     */
    /**
     * @param array<int,string>|null $restrictExtensions when set, only these
     *        extensions are accepted (e.g. ['jpg','jpeg','png','webp'] for product
     *        images, or ['pdf'] for documents) — narrows the global allowlist.
     */
    public function handleUpload(array $file, ?int $userId, ?string $altText = null, ?array $restrictExtensions = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'No file was uploaded or the upload failed.'];
        }
        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'File is empty or exceeds the 8 MB limit.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'Invalid upload.'];
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return ['ok' => false, 'error' => 'File type not allowed.'];
        }
        if ($restrictExtensions !== null && !in_array($ext, $restrictExtensions, true)) {
            return ['ok' => false, 'error' => 'This upload only accepts: ' . implode(', ', $restrictExtensions) . '.'];
        }

        // Real MIME from content, not the client-supplied type.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = (string) $finfo->file($file['tmp_name']);
        if (!in_array($realMime, self::ALLOWED[$ext], true)) {
            $this->logger->warning('media.upload.mime_mismatch', ['ext' => $ext, 'mime' => $realMime]);
            return ['ok' => false, 'error' => 'File content does not match its extension.'];
        }

        // Build a safe destination path: public/uploads/YYYY/MM/<random>.<ext>
        $publicRoot = base_path('public');
        $relDir = '/uploads/' . date('Y') . '/' . date('m');
        $absDir = $publicRoot . $relDir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return ['ok' => false, 'error' => 'Upload directory is not writable.'];
        }

        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $absPath  = $absDir . '/' . $safeName;
        $urlPath  = $relDir . '/' . $safeName;

        // SVGs are sanitised (remove scripts/handlers) before writing.
        if ($ext === 'svg') {
            $svg = (string) file_get_contents($file['tmp_name']);
            $clean = $this->sanitizeSvg($svg);
            if ($clean === '') {
                return ['ok' => false, 'error' => 'The SVG could not be sanitised.'];
            }
            if (file_put_contents($absPath, $clean) === false) {
                return ['ok' => false, 'error' => 'Could not store the file.'];
            }
        } elseif (!move_uploaded_file($file['tmp_name'], $absPath)) {
            return ['ok' => false, 'error' => 'Could not store the file.'];
        }

        @chmod($absPath, 0644);

        // Image dimensions (best-effort).
        $width = $height = null;
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $size = @getimagesize($absPath);
            if (is_array($size)) {
                $width  = (int) $size[0];
                $height = (int) $size[1];
            }
        }

        try {
            $id = $this->media->create([
                'disk_path'     => $absPath,
                'url_path'      => $urlPath,
                'original_name' => mb_substr((string) $file['name'], 0, 255),
                'mime'          => $realMime,
                'extension'     => $ext,
                'size_bytes'    => (int) $file['size'],
                'width'         => $width,
                'height'        => $height,
                'alt_text'      => $altText,
                'title'         => null,
                'uploaded_by'   => $userId,
            ]);
        } catch (\Throwable $e) {
            @unlink($absPath);
            $this->logger->error('media.upload.db_failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Could not save the media record.'];
        }

        return ['ok' => true, 'id' => $id];
    }

    public function delete(int $id): void
    {
        $row = $this->media->findActive($id);
        if ($row === null) {
            return;
        }
        // Soft-delete the record; remove the file from disk.
        $this->media->softDelete($id);
        $path = (string) $row['disk_path'];
        if ($path !== '' && is_file($path) && str_contains($path, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR)) {
            @unlink($path);
        }
    }

    /** Strip scripts, event handlers and external refs from an SVG. */
    private function sanitizeSvg(string $svg): string
    {
        if (!str_contains(strtolower($svg), '<svg')) {
            return '';
        }
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? '';
        $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg) ?? '';
        $svg = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $svg) ?? '';
        $svg = preg_replace('#(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*(\2)#i', '', $svg) ?? '';
        $svg = preg_replace('#<!ENTITY[^>]*>#i', '', $svg) ?? '';
        return trim($svg);
    }
}
