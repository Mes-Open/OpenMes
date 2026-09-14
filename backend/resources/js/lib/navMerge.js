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
