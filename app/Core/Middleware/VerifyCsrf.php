<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use Closure;

/**
 * Verifies a valid CSRF token on every state-changing request (SECURITY_PLAN §8).
 * Token accepted from the form field (_token) or the X-CSRF-Token header.
 */
final class VerifyCsrf implements MiddlewareInterface
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly Config $config,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isWriteMethod()) {
            $fieldName  = (string) $this->config->get('security.csrf.field_name', '_token');
            $headerName = (string) $this->config->get('security.csrf.header_name', 'X-CSRF-Token');

            $token = $request->input($fieldName) ?? $request->header($headerName);

            if (!is_string($token) || !$this->csrf->verify($token)) {
                throw new HttpException(419, 'CSRF token mismatch.');
            }
        }

        return $next($request);
    }
}
