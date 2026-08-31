<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RedirectRepository;

/**
 * CMS-managed redirects with loop and open-redirect protection (SECURITY_PLAN).
 */
final class RedirectService
{
    public function __construct(private readonly RedirectRepository $redirects)
    {
    }

    /**
     * Resolve a request path to a redirect, or null.
     * @return array{to:string,code:int}|null
     */
    public function resolve(string $path): ?array
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/'; // never redirect the home path via this table
        }

        $row = $this->redirects->findActiveByPath($path);
        if ($row === null) {
            return null;
        }

        $to = (string) $row['to_url'];

        // Self-loop guard: destination equals source.
        if (rtrim($to, '/') === rtrim($path, '/')) {
            return null;
        }

        // Only allow internal paths or safe absolute http(s) URLs.
        if (!$this->isSafeTarget($to)) {
            return null;
        }

        $this->redirects->incrementHit((int) $row['id']);

        return ['to' => $to, 'code' => (int) $row['code'] === 302 ? 302 : 301];
    }

    /**
     * Validate an admin-entered redirect before saving. Returns an error string
     * or null when valid. Prevents self-loops and unsafe targets.
     */
    public function validate(string $fromPath, string $toUrl): ?string
    {
        $fromPath = '/' . trim($fromPath, '/');
        $toUrl = trim($toUrl);

        if ($fromPath === '/' || $fromPath === '') {
            return 'The source path is required and cannot be the home page.';
        }
        if ($toUrl === '') {
            return 'The destination is required.';
        }
        if (rtrim($fromPath, '/') === rtrim($toUrl, '/')) {
            return 'Source and destination cannot be the same (redirect loop).';
        }
        if (!$this->isSafeTarget($toUrl)) {
            return 'Destination must be an internal path (/…) or a valid http(s) URL.';
        }
        return null;
    }

    /** Internal path starting with "/" (but not "//"), or an absolute http(s) URL. */
    private function isSafeTarget(string $to): bool
    {
        if (str_starts_with($to, '/') && !str_starts_with($to, '//')) {
            return true;
        }
        if (preg_match('#^https?://#i', $to)) {
            return filter_var($to, FILTER_VALIDATE_URL) !== false;
        }
        return false;
    }
}
