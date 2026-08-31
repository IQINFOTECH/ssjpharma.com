<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session wrapper with hardened cookie parameters and flash-message support.
 * Sessions are stored outside the webroot (config/security.php). See SECURITY_PLAN §6.
 */
final class Session
{
    private bool $started = false;

    /**
     * @param array{name:string,lifetime:int,secure:bool,http_only:bool,same_site:string,save_path:string} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        // Headers can't be sent yet (CLI test harness has no session support).
        if (PHP_SAPI === 'cli') {
            $this->started = true;
            return;
        }

        $savePath = $this->config['save_path'];
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        session_name($this->config['name']);

        session_set_cookie_params([
            'lifetime' => 0, // session cookie; idle timeout enforced below
            'path'     => '/',
            'secure'   => $this->config['secure'],
            'httponly' => $this->config['http_only'],
            'samesite' => $this->config['same_site'],
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();
        $this->started = true;

        $this->enforceIdleTimeout();
    }

    private function enforceIdleTimeout(): void
    {
        $lifetime = $this->config['lifetime'] * 60;
        $now = time();

        if (isset($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $lifetime) {
            $this->invalidate();
        }

        $_SESSION['_last_activity'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Regenerate the session id (call on login / privilege change). */
    public function regenerate(bool $deleteOld = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    /** Current session id (empty string when no active session, e.g. CLI). */
    public function id(): string
    {
        return session_status() === PHP_SESSION_ACTIVE ? (string) session_id() : '';
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    // --- Flash messages ------------------------------------------------------

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
