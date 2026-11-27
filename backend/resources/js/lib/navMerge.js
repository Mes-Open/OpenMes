/**
 * Where a module's menu contributions land in the sidebar.
 *
 * A module declares a position rather than being appended blindly, so an
 * extension can sit where it belongs — workforce screens next to Admin, a
 * reporting add-on beside Analytics — instead of always trailing behind
 * Settings.
 *
 * The scale is the group `order` in adminNav.js. Built-in groups occupy 10–120
 * in steps of 10, which leaves room between every pair:
 *
 *    10 Connectivity   50 Analytics      90 Webhooks
 *    20 Orders         70 Maintenance   100 Admin
 *    30 Production     80 Inspections   110 Modules
 *    40 Warehouses                      120 Settings
 *
 * A module picks any number: 45 sits between Warehouses and Analytics, 105
 * between Admin and Modules. Omitting it means DEFAULT_MODULE_ORDER — after
 * everything built in, which is the right default for a module whose author has
 * no opinion.
 *
 * Ties keep their relative order (built-in before module, then registration
 * order), so a module cannot displace a core group by matching its number.
 *
 * Plain JS and dependency-free on purpose: the ordering is the part worth
 * testing, and AppLayout cannot be imported in a test.
 */

/** Where a module group goes when it declares no position: after everything. */
export const DEFAULT_MODULE_ORDER = 500;

/** Built-in entries with no explicit order sort as if they were last. */
const UNORDERED = Number.MAX_SAFE_INTEGER;

/**
 * Stable sort by `order`.
 *
 * Array.prototype.sort is stable in every engine this runs on, so equal orders
 * keep the order they were given — which is what makes ties predictable rather
 * than arbitrary.
 *
 * @template {{order?: number}} T
 * @param {T[]} entries
 * @returns {T[]}
 */
export function byOrder(entries) {
    return [...entries].sort((a, b) => (a.order ?? UNORDERED) - (b.order ?? UNORDERED));
}

/**
 * Merge one module's links into a built-in group's children, by position.
 *
 * A module item without an order goes after the built-in children, not into the
 * middle of them.
 *
 * @param {Array<object>} children  the group's own children, in file order
 * @param {Array<{order?: number}>} contributed
 */
export function mergeChildren(children, contributed) {
    if (!contributed?.length) {
        return children;
    }

    // Built-in children have no `order` of their own — their position in the
    // file is the intent — so they are numbered by index on a scale the
    // contributed orders share.
    const positioned = children.map((child, i) => ({ ...child, order: child.order ?? (i + 1) * 10 }));

    return byOrder([...positioned, ...contributed]);
}

/**
 * The full top-level group list: built-ins plus whatever modules registered,
 * interleaved by order.
 *
 * @param {Array<{key: string, order?: number}>} builtIn
 * @param {Array<{key: string, order?: number}>} fromModules
 */
export function mergeGroups(builtIn, fromModules) {
    if (!fromModules?.length) {
        return builtIn;
    }

    return byOrder([
        ...builtIn,
        ...fromModules.map((g) => ({ ...g, order: g.order ?? DEFAULT_MODULE_ORDER })),
    ]);
}

/**
 * A group's `match` list — the paths that highlight and auto-expand it —
 * extended with the paths of the children it was just given.
 *
 * A built-in group lists its own children's paths in `match` by hand; a link
 * a module injects is not on that list, so the group stayed dark (and shut)
 * on the module's own pages while the core pages next to them lit it up. A
 * module's own group has no list at all. Either way the answer is the same:
 * whatever the group's children link to, the group matches.
 *
 * @param {string[]|undefined} match      the group's declared prefixes
 * @param {Array<{href?: string, match?: string[]}>} children
 * @returns {string[]}
 */
export function groupMatch(match, children) {
    const fromChildren = (children ?? []).flatMap((child) => child.match ?? (child.href ? [child.href] : []));

    return [...new Set([...(match ?? []), ...fromChildren])];
}

/**
 * Put module links into the supervisor sidebar's groups.
 *
 * MenuRegistry::addSupervisorItem() names a group by its short key (orders,
 * production, materials, logistics, reports); supervisorNav.js keys the same
 * groups `supervisor-<key>`. A group that gets links also matches their paths,
 * so it opens and lights up on the module's own screens, and takes the first
 * link's badge when it has none of its own.
 *
 * @param {Array<object>} groups  SUPERVISOR_GROUPS
 * @param {Record<string, Array<object>>} items  short group key => sidebar children
 */
export function mergeSupervisorItems(groups, items) {
    return groups.map((group) => {
        const extra = items?.[group.key.replace(/^supervisor-/, '')];
        if (! extra?.length) {
            return group;
        }

        return {
            ...group,
            badge: group.badge ?? extra.find((child) => child.badge)?.badge,
            match: groupMatch(group.match, extra),
            children: mergeChildren(group.children, extra),
        };
    });
}

/**
 * Drop sidebar entries whose href an install hides (`moduleNav.hidden`, filter
 * `nav.hidden`). A group left without children disappears with them.
 *
 * A node with an `href` and no children renders as a flat link — a module group
 * that is a single screen comes as `{ href, children: [] }` — so it is a link
 * here too: hidden when its href is listed, kept otherwise. Only a group that
 * had children and lost them all disappears.
 */
export function withoutHidden(nodes, hidden) {
    if (! Array.isArray(hidden) || hidden.length === 0) {
        return nodes;
    }

    const isLink = (node) => ! node.children?.length;

    return nodes
        .filter((node) => ! (node.href && isLink(node) && hidden.includes(node.href)))
        .map((node) => (node.children?.length
            ? { ...node, children: withoutHidden(node.children, hidden) }
            : node))
        .filter((node) => isLink(node) ? (node.href || ! node.children) : node.children.length > 0);
}
