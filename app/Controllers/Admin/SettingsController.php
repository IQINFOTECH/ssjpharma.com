<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\SettingRepository;
use App\Services\SettingsService;

/**
 * CMS global settings editor. Secrets are NOT stored here (they live in .env).
 */
final class SettingsController extends AdminController
{
    private function settings(): SettingRepository { return $this->container->get(SettingRepository::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('settings.view');
        return $this->adminView('admin.settings.index', [
            'title'  => 'Settings',
            'groups' => $this->settings()->grouped(),
        ], 'settings');
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('settings.edit');

        $posted = (array) $request->input('settings', []);
        $rows = $this->settings()->allForAdmin();
        $userId = $this->currentUserId();

        foreach ($rows as $row) {
            $key = $row['key'];
            $type = $row['type'];

            if ($type === 'bool') {
                // Unchecked checkboxes aren't posted — treat absence as "0".
                $value = !empty($posted[$key]) ? '1' : '0';
            } elseif (!array_key_exists($key, $posted)) {
                continue; // field not on this form
            } else {
                $value = $this->normalise($type, (string) $posted[$key]);
            }

            $this->settings()->updateValue($key, $value, $userId);
        }

        $this->container->get(SettingsService::class)->refresh();
        $this->audit('SETTINGS_UPDATED', ['entity_type' => 'settings']);
        $this->flash('success', 'Settings saved.');
        return Response::redirect('/admin/settings');
    }

    private function normalise(string $type, string $value): string
    {
        $value = trim($value);
        return match ($type) {
            'int'   => (string) (int) $value,
            'email' => mb_substr($value, 0, 190),
            'url'   => mb_substr($value, 0, 500),
            // Media: keep a numeric library id as-is, OR preserve a pasted image URL/path.
            'media' => is_numeric($value) ? (string) (int) $value : mb_substr($value, 0, 500),
            'text'  => mb_substr($value, 0, 5000),
            default => mb_substr($value, 0, 1000),
        };
    }
}
