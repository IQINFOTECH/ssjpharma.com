<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MenuItemRepository;
use App\Repositories\MenuRepository;

/**
 * Builds nested menu trees for the public site with resolved URLs. Only active
 * items, and page-linked items whose page is published, are surfaced publicly.
 */
final class MenuService
{
    /** @var array<string,array<int,array<string,mixed>>> per-request cache */
    private array $cache = [];

    public function __construct(
        private readonly MenuRepository $menus,
        private readonly MenuItemRepository $items,
    ) {
    }

    /**
     * @return array<int,array<string,mixed>> tree of items: each has
     *   label, url, open_new_tab, children[]
     */
    public function tree(string $menuKey): array
    {
        if (isset($this->cache[$menuKey])) {
            return $this->cache[$menuKey];
        }

        $menu = $this->menus->findByKey($menuKey);
        if ($menu === null) {
            return $this->cache[$menuKey] = [];
        }

        $rows = $this->items->activeForMenu((int) $menu['id']);

        // Index children by parent.
        $byParent = [];
        foreach ($rows as $row) {
            // Drop page-linked items whose page is not publicly available.
            if ($row['page_id'] !== null) {
                if (($row['page_status'] ?? null) !== 'published' || $row['page_deleted'] !== null) {
                    continue;
                }
            }
            $parent = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $byParent[$parent][] = $this->normalise($row);
        }

        $tree = $this->buildBranch($byParent, 0);
        return $this->cache[$menuKey] = $tree;
    }

    /** @param array<int,array<int,array<string,mixed>>> $byParent */
    private function buildBranch(array $byParent, int $parentId): array
    {
        $branch = [];
        foreach ($byParent[$parentId] ?? [] as $item) {
            $item['children'] = $this->buildBranch($byParent, (int) $item['id']);
            $branch[] = $item;
        }
        return $branch;
    }

    /** @return array<string,mixed> */
    private function normalise(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'label'        => (string) $row['label'],
            'url'          => $this->resolveUrl($row),
            'open_new_tab' => (bool) $row['open_new_tab'],
            'is_external'  => (bool) $row['is_external'],
        ];
    }

    /** Resolve an item's href: page slug > explicit url > '#'. */
    private function resolveUrl(array $row): string
    {
        if ($row['page_id'] !== null && !empty($row['page_slug'])) {
            $slug = (string) $row['page_slug'];
            return $slug === 'home' ? '/' : '/' . $slug;
        }
        $url = trim((string) ($row['url'] ?? ''));
        return $url !== '' ? $url : '#';
    }
}
