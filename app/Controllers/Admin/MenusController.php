<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\MenuItemRepository;
use App\Repositories\MenuRepository;

/**
 * CMS Menus: manage header / mobile / footer navigation and their items
 * (labels, targets, nesting, order). Navigation is never hardcoded.
 */
final class MenusController extends AdminController
{
    private function menus(): MenuRepository        { return $this->container->get(MenuRepository::class); }
    private function items(): MenuItemRepository     { return $this->container->get(MenuItemRepository::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('menus.view');

        $menus = $this->menus()->allMenus();
        $data = [];
        foreach ($menus as $menu) {
            $data[] = ['menu' => $menu, 'items' => $this->items()->allForMenu((int) $menu['id'])];
        }

        /** @var Database $db */
        $db = $this->container->get(Database::class);
        $pages = $db->select("SELECT id, title, slug FROM pages WHERE deleted_at IS NULL ORDER BY title ASC");

        return $this->adminView('admin.menus.index', [
            'title' => 'Menus',
            'menus' => $data,
            'pages' => $pages,
        ], 'menus');
    }

    public function addItem(Request $request): Response
    {
        $this->requirePermission('menus.create');
        $menuId = (int) $request->route('menu');
        $menu = $this->menus()->find($menuId);
        if ($menu === null) {
            throw new HttpException(404);
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            $this->flash('error', 'Label is required.');
            return Response::redirect('/admin/menus');
        }

        $this->items()->create($this->itemPayload($request, $menuId));
        $this->flash('success', 'Menu item added.');
        return Response::redirect('/admin/menus');
    }

    public function updateItem(Request $request): Response
    {
        $this->requirePermission('menus.edit');
        $id = (int) $request->route('id');
        $menuId = (int) $request->input('menu_id');

        $item = $this->items()->findScoped($id, $menuId);
        if ($item === null) {
            throw new HttpException(404);
        }

        $this->items()->update($id, $this->itemPayload($request, $menuId, $id));
        $this->flash('success', 'Menu item updated.');
        return Response::redirect('/admin/menus');
    }

    public function deleteItem(Request $request): Response
    {
        $this->requirePermission('menus.delete');
        $id = (int) $request->route('id');
        $menuId = (int) $request->input('menu_id');
        $this->items()->delete($id, $menuId);
        $this->flash('success', 'Menu item removed.');
        return Response::redirect('/admin/menus');
    }

    /** @return array<string,mixed> */
    private function itemPayload(Request $request, int $menuId, ?int $selfId = null): array
    {
        $pageId = (int) $request->input('page_id', 0);
        $url    = trim((string) $request->input('url', ''));
        $parent = (int) $request->input('parent_id', 0);

        // A parent cannot be the item itself.
        if ($selfId !== null && $parent === $selfId) {
            $parent = 0;
        }

        return [
            'menu_id'      => $menuId,
            'parent_id'    => $parent > 0 ? $parent : null,
            'label'        => mb_substr(trim((string) $request->input('label', '')), 0, 150),
            'page_id'      => $pageId > 0 ? $pageId : null,
            'url'          => $url !== '' ? mb_substr($url, 0, 500) : null,
            'is_external'  => preg_match('#^https?://#i', $url) ? 1 : 0,
            'open_new_tab' => $request->input('open_new_tab') ? 1 : 0,
            'sort_order'   => (int) $request->input('sort_order', 0),
            'is_active'    => $request->input('is_active') ? 1 : 0,
        ];
    }
}
