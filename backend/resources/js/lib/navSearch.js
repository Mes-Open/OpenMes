/**
 * Sidebar menu search index.
 *
 * The footer Settings link is not part of either nav tree, so the index adds
 * it for a supervisor, who would otherwise have no way to search for it. The
 * admin tree already has a Settings group with that same label and href.
 * Indexing both produced two rows with the React key `>Settings`. React keeps
 * one fiber per key. When the result list changes shape — typing "s", then
 * "se", then "set" — the other row is left in the sidebar, and the next
 * search adds more. Every leftover links to `/settings`.
 *
 * `searchResultKeys` still distinguishes a repeated trail+label, so a module
 * that registers a second copy cannot start the pile-up again.
 */

// A nav group is visible when its own tab is accessible OR at least one of its
// descendants carries its own (different) accessible tab. The second clause lets
// a group survive when its header key is off but a fine-grained child module is
// on — e.g. Reports hidden but Advanced reports enabled. Groups whose children
// have no explicit tab fall back to the header key alone.
export function groupVisible(group, showTab) {
    // Groups that are not a feature module — Settings holds the current user's
    // own profile, password and 2FA, which no enabled-module set should hide.
    if (group.alwaysVisible) return true;
    if (showTab(group.tab ?? group.key)) return true;
    const anyChild = (nodes) => (nodes || []).some((n) =>
        n.children ? anyChild(n.children) : (n.tab && showTab(n.tab)));
    return anyChild(group.children);
}

/**
 * Flat list of every navigable sidebar item (top links, group/subgroup headers
 * that have their own landing page, and children) for the sidebar search.
 * `trail` is the group path shown under a result, e.g. "Production /
 * Production Lines". Disabled entries are skipped.
 *
 * @param {(key: string|undefined) => boolean} showTab
 * @param {Array<object>} groups
 * @param {Array<object>} links
 */
export function flattenNavItems(showTab, groups, links) {
    const items = links.filter((link) => showTab(link.key)).map((link) => ({
        label: link.label, href: link.href, match: link.match, exact: link.exact, trail: [],
    }));
    const walk = (nodes, trail) => {
        nodes.filter((node) => showTab(node.tab)).forEach((node) => {
            if (node.href && !node.disabled) {
                items.push({
                    label: node.label,
                    href: node.href,
                    match: node.match,
                    exact: node.exact,
                    external: node.external,
                    trail,
                });
            }
            if (node.children) {
                walk(node.children, [...trail, node.label]);
            }
        });
    };
    // Index groups with at least one accessible tab (role + enabled module — #144);
    // module-declared groups are always indexed (already gated server-side).
    walk(groups.filter((group) => group.moduleGroup || groupVisible(group, showTab)), []);
    // The footer link. Skip it when the tree already indexed Settings → /settings,
    // which is the admin group. A supervisor has no such group, so the row stays.
    if (!items.some((item) => item.label === 'Settings' && item.href === '/settings')) {
        items.push({ label: 'Settings', href: '/settings', match: ['/settings'], trail: [] });
    }
    return items;
}

/**
 * @param {Array<{label: string, trail: string[]}>} items
 * @param {string} query
 * @param {(text: string) => string} [translate]
 * @returns {Array|null} null when the query is empty, so the caller shows the tree
 */
export function filterNavItems(items, query, translate = (text) => text) {
    const q = String(query ?? '').trim().toLowerCase();
    if (!q) return null;

    return items.filter((item) =>
        [item.label, translate(item.label), ...item.trail.flatMap((t) => [t, translate(t)])]
            .join(' ')
            .toLowerCase()
            .includes(q));
}

/**
 * One key per row. `href` is not enough — a group and its first child can
 * share one — and trail+label is not enough when two rows are the same
 * Settings entry. A repeated key is how the sidebar filled up.
 *
 * @param {Array<{label: string, trail: string[]}>} items
 * @returns {string[]}
 */
export function searchResultKeys(items) {
    const seen = new Map();

    return items.map((item) => {
        const base = `${item.trail.join('/')}>${item.label}`;
        const n = seen.get(base) ?? 0;
        seen.set(base, n + 1);

        return n === 0 ? base : `${base}#${n}`;
    });
}
