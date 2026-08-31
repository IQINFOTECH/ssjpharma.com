<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Health & readiness endpoint plus a minimal Phase-0 landing view.
 * No pharmaceutical content — the real site is built in later phases.
 */
final class HealthController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Database $db */
        $db = $this->container->get(Database::class);

        $dbConnected = $db->isConnected();

        $checks = [
            'php'      => PHP_VERSION,
            'app_env'  => (string) $this->config('app.env', 'production'),
            'database' => $dbConnected ? 'connected' : 'unavailable',
            'writable' => [
                'storage/logs'     => is_writable(BASE_PATH . '/storage/logs'),
                'storage/sessions' => is_writable(BASE_PATH . '/storage/sessions'),
                'storage/cache'    => is_writable(BASE_PATH . '/storage/cache'),
            ],
        ];

        $ok = $dbConnected
            || (string) $this->config('app.env') === 'local'; // don't fail health locally before DB is set up

        return $this->json([
            'status'    => $ok ? 'ok' : 'degraded',
            'service'   => 'ssjpharma',
            'time'      => date(DATE_ATOM),
            'checks'    => $checks,
        ], $ok ? 200 : 503);
    }

    public function welcome(Request $request): Response
    {
        return $this->view('welcome', [
            'appName' => (string) $this->config('app.name', 'SSJ Pharmaceuticals'),
        ]);
    }
}
