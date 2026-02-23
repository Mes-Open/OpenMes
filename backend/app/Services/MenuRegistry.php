<?php

namespace App\Services;

/**
 * MenuRegistry — allows modules to extend the navigation menu.
 *
 * Registered as a singleton in AppServiceProvider and shared with all views
 * as $menuRegistry. Modules call methods in their ServiceProvider::boot().
 *
 * Built-in group keys: orders | production | structure | hr | maintenance | admin
 *
 * Usage in a module ServiceProvider:
 *
 *   public function boot(): void
 *   {
 *       $menu = app(\App\Services\MenuRegistry::class);
 *
 *       // Add a link to an existing dropdown:
 *       $menu->addItem('orders', 'My Feature', route('mymodule.index'));
 *
 *       // Add a brand-new dropdown group:
 *       $menu->addGroup('mymodule', 'My Module', order: 55);
 *       $menu->addGroupItem('mymodule', 'Dashboard', route('mymodule.dashboard'));
 *       $menu->addGroupItem('mymodule', 'Settings',  route('mymodule.settings'), order: 20);
 *   }
 */
class MenuRegistry
{
    /** Extra items injected into built-in dropdowns. */
    private array $items = [];

    /** Custom top-level dropdown groups. */
    private array $groups = [];

    // -------------------------------------------------------------------------
    // Built-in group injection
    // -------------------------------------------------------------------------

    /**
     * Add a link to one of the existing nav dropdowns.
     *
     * @param string $group  Dropdown key: orders | production | structure | hr | maintenance | admin
     * @param string $label  Link text displayed in the dropdown
     * @param string $url    Resolved URL (call route() or url() in your ServiceProvider)
     * @param int    $order  Sort weight — built-in items use multiples of 10; use ≥50 to appear after them
     */
    public function addItem(string $group, string $label, string $url, int $order = 50): void
    {
        $this->items[$group][] = compact('label', 'url', 'order');
    }

    /**
     * Return the extra items registered for a built-in dropdown, sorted by order.
     *
     * @return list<array{label: string, url: string, order: int}>
     */
    public function getItems(string $group): array
    {
        $items = $this->items[$group] ?? [];
        usort($items, fn($a, $b) => $a['order'] <=> $b['order']);
        return $items;
    }

    // -------------------------------------------------------------------------
    // Custom dropdown groups
    // -------------------------------------------------------------------------

    /**
     * Register a new top-level dropdown group.
     * Call this before addGroupItem() to control the label and sort order.
     * Calling it again with the same id is a no-op (first registration wins).
     *
     * @param string $id    Unique identifier used as key for addGroupItem()
     * @param string $label Dropdown button text
     * @param int    $order Position relative to other custom groups (lower = rendered first / leftmost)
     */
    public function addGroup(string $id, string $label, int $order = 50): void
    {
        if (!isset($this->groups[$id])) {
            $this->groups[$id] = ['id' => $id, 'label' => $label, 'order' => $order, 'items' => []];
        }
    }

    /**
     * Add a link to a custom dropdown group.
     * If the group has not been registered yet, it is auto-created using the id as its label.
     *
     * @param string $groupId Target group id (must match a prior addGroup() call)
     * @param string $label   Link text
     * @param string $url     Resolved URL
     * @param int    $order   Sort weight within the group
     */
    public function addGroupItem(string $groupId, string $label, string $url, int $order = 50): void
    {
        if (!isset($this->groups[$groupId])) {
            $this->addGroup($groupId, ucfirst($groupId));
        }

        $this->groups[$groupId]['items'][] = compact('label', 'url', 'order');
    }

    /**
     * Return all custom groups that have at least one item, sorted by order.
     *
     * @return list<array{id: string, label: string, order: int, items: list<array{label: string, url: string, order: int}>}>
     */
    public function getGroups(): array
    {
        $groups = array_values(array_filter(
            $this->groups,
            fn($g) => !empty($g['items'])
        ));

        foreach ($groups as &$group) {
            usort($group['items'], fn($a, $b) => $a['order'] <=> $b['order']);
        }

        usort($groups, fn($a, $b) => $a['order'] <=> $b['order']);

        return $groups;
    }
}
