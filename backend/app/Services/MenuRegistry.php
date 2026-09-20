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
 * ── Where things land in the sidebar ──────────────────────────────────────
 *
 * `$order` is a position on one shared scale, not a preference. Built-in groups
 * occupy 10–120 in steps of 10, which leaves room between every pair:
 *
 *     10 Connectivity    50 Analytics      90 Webhooks
 *     20 Orders          70 Maintenance   100 Admin
 *     30 Production      80 Inspections   110 Modules
 *     40 Warehouses                       120 Settings
 *
 * Pick any number: 45 sits between Warehouses and Analytics, 105 between Admin
 * and Modules. Omit it and the group goes after everything built in — the right
 * default when you have no opinion, and better than landing somewhere arbitrary.
 *
 * Equal numbers keep their registration order, and a built-in always wins a tie,
 * so a module cannot displace a core group by matching its number.
 *
 * Usage in a module ServiceProvider:
 *
 *   public function boot(): void
 *   {
 *       $menu = app(\App\Services\MenuRegistry::class);
 *
 *       // Add a link to an existing dropdown, third from the top:
 *       $menu->addItem('orders', 'My Feature', route('mymodule.index'), order: 25);
 *
 *       // Add a dropdown of your own, right after Warehouses:
 *       $menu->addGroup('mymodule', 'My Module', order: 45);
 *       $menu->addGroupItem('mymodule', 'Dashboard', route('mymodule.dashboard'), order: 10);
 *       $menu->addGroupItem('mymodule', 'Settings',  route('mymodule.settings'), order: 20);
 *   }
 */
class MenuRegistry
{
    /**
     * Default position for a group that names none: after every built-in one.
     *
     * Deliberately past the 10–120 range core uses. A module with no opinion
     * about placement should trail the application's own navigation rather than
     * appear in the middle of it by accident.
     */
    public const AFTER_BUILT_IN = 500;

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
     * @param  string  $group  Dropdown key: orders | production | structure | hr | maintenance | admin
     * @param  string  $label  Link text displayed in the dropdown
     * @param  string  $url  Resolved URL (call route() or url() in your ServiceProvider)
     * @param  int  $order  Sort weight — built-in items use multiples of 10; use ≥50 to appear after them
     * @param  string|null  $badge  Short tag rendered to the right of the label. Lets a module
     *                              mark which entries it contributed (its edition, tier, or
     *                              anything else) without core knowing any module by name.
     */
    public function addItem(string $group, string $label, string $url, int $order = 50, ?string $badge = null): void
    {
        $this->items[$group][] = compact('label', 'url', 'order', 'badge');
    }

    /**
     * Return the extra items registered for a built-in dropdown, sorted by order.
     *
     * @return list<array{label: string, url: string, order: int, badge: ?string}>
     */
    public function getItems(string $group): array
    {
        $items = $this->items[$group] ?? [];
        usort($items, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $items;
    }

    /**
     * All built-in-dropdown injections keyed by group, each list sorted by order.
     * Feeds the React sidebar (HandleInertiaRequests) so module menu hooks render
     * in the SPA the same way they used to render in the deleted Blade sidebar.
     *
     * @return array<string, list<array{label: string, url: string, order: int, badge: ?string}>>
     */
    public function getAllItems(): array
    {
        $out = [];
        foreach (array_keys($this->items) as $group) {
            $out[$group] = $this->getItems($group);
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Custom dropdown groups
    // -------------------------------------------------------------------------

    /**
     * Register a new top-level dropdown group.
     * Call this before addGroupItem() to control the label and sort order.
     * Calling it again with the same id is a no-op (first registration wins).
     *
     * @param  string  $id  Unique identifier used as key for addGroupItem()
     * @param  string  $label  Dropdown button text
     * @param  int  $order  Position relative to other custom groups (lower = rendered first / leftmost)
     * @param  string|null  $badge  Short tag rendered to the right of the group label.
     * @param  string|null  $url  Give a destination and add no items, and the entry
     *                            renders as a flat link in the group's position
     *                            rather than as a dropdown. For a module whose
     *                            feature is one screen: a dropdown holding a
     *                            single link is a link with an extra click.
     */
    public function addGroup(string $id, string $label, int $order = self::AFTER_BUILT_IN, ?string $badge = null, ?string $url = null): void
    {
        if (! isset($this->groups[$id])) {
            $this->groups[$id] = [
                'id' => $id, 'label' => $label, 'order' => $order,
                'badge' => $badge, 'url' => $url, 'items' => [],
            ];
        }
    }

    /**
     * Add a link to a custom dropdown group.
     * If the group has not been registered yet, it is auto-created using the id as its label.
     *
     * @param  string  $groupId  Target group id (must match a prior addGroup() call)
     * @param  string  $label  Link text
     * @param  string  $url  Resolved URL
     * @param  int  $order  Sort weight within the group
     * @param  string|null  $badge  Short tag rendered to the right of the label
     */
    public function addGroupItem(string $groupId, string $label, string $url, int $order = 50, ?string $badge = null): void
    {
        if (! isset($this->groups[$groupId])) {
            $this->addGroup($groupId, ucfirst($groupId));
        }

        $this->groups[$groupId]['items'][] = compact('label', 'url', 'order', 'badge');
    }

    /**
     * Return all custom groups that have at least one item, sorted by order.
     *
     * @return list<array{id: string, label: string, order: int, badge: ?string, url: ?string, items: list<array{label: string, url: string, order: int, badge: ?string}>}>
     */
    public function getGroups(): array
    {
        // A group earns its place by having somewhere to go: entries to show,
        // or a destination of its own. One with neither would be a header that
        // opens onto nothing.
        $groups = array_values(array_filter(
            $this->groups,
            fn ($g) => ! empty($g['items']) || ! empty($g['url'])
        ));

        foreach ($groups as &$group) {
            usort($group['items'], fn ($a, $b) => $a['order'] <=> $b['order']);
        }

        usort($groups, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $groups;
    }
}
