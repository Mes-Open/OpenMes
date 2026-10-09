/**
 * Supervisor sidebar navigation.
 *
 * A supervisor has their own route tree under `/supervisor`, and this is the
 * base menu they see. Admin-panel tabs explicitly granted through Settings
 * Access are appended separately. Keeping the two apart is the point: a shift
 * supervisor's job is the floor in front of them, not the plant's configuration,
 * and a menu that mixed the two would keep sending them somewhere they'd be
 * refused.
 *
 * Every entry here must resolve to a real `/supervisor` route (routes/web.php,
 * `supervisor.*`). Screens shared with the admin section are the same controller
 * and the same React page mounted under both prefixes — see
 * `App\Http\Controllers\Concerns\ServesBothSections`.
 *
 * Shape matches adminNav's: `ADMIN_LINKS`-style flat entries and
 * `ADMIN_GROUPS`-style collapsible groups, so `AppLayout` renders either tree
 * with the same components. No `tab` keys — access is the route group's
 * `role:Supervisor|Admin` middleware, not the per-tab admin matrix.
 *
 * An entry for an optional feature module carries `module: '<key>'` and is
 * hidden when an admin has switched that module off, matching what the routes
 * do (`module:` middleware) and what the admin tree gets via its tab keys.
 */

export const SUPERVISOR_LINKS = [
    {
        key: 'supervisor-dashboard',
        label: 'Dashboard',
        href: '/supervisor/dashboard',
        lucide: 'layout-dashboard',
        match: ['/supervisor/dashboard'],
    },
    {
        key: 'supervisor-shift-overview',
        label: 'Line Overview',
        href: '/supervisor/shift-overview',
        lucide: 'layout-list',
        match: ['/supervisor/shift-overview'],
    },
    {
        key: 'supervisor-shift-monitor',
        label: 'Shift Monitor',
        href: '/supervisor/shift-monitor',
        lucide: 'activity',
        match: ['/supervisor/shift-monitor'],
    },
    {
        key: 'supervisor-shift-board',
        label: 'Plant Board',
        href: '/supervisor/shift-board',
        lucide: 'layout-grid',
        match: ['/supervisor/shift-board'],
    },
];

export const SUPERVISOR_GROUPS = [
    {
        key: 'supervisor-orders',
        label: 'Orders',
        lucide: 'clipboard-list',
        href: '/supervisor/work-orders',
        match: [
            '/supervisor/work-orders', '/supervisor/customers',
            '/supervisor/priority-rules', '/supervisor/import',
        ],
        children: [
            { label: 'All Orders', href: '/supervisor/work-orders', match: ['/supervisor/work-orders'], lucide: 'list' },
            { label: 'Customers', href: '/supervisor/customers', match: ['/supervisor/customers'], lucide: 'users' },
            { label: 'Priority Settings', href: '/supervisor/priority-rules', match: ['/supervisor/priority-rules'], lucide: 'sliders-horizontal' },
            { label: 'Import', href: '/supervisor/import', match: ['/supervisor/import'], lucide: 'file-up' },
        ],
    },
    {
        key: 'supervisor-production',
        label: 'Production',
        lucide: 'factory',
        href: '/supervisor/issues',
        match: ['/supervisor/issues', '/supervisor/quality-tasks', '/supervisor/shift-handover'],
        children: [
            { label: 'Issues', href: '/supervisor/issues', match: ['/supervisor/issues'], lucide: 'triangle-alert', module: 'quality' },
            { label: 'Quality Tasks', href: '/supervisor/quality-tasks', match: ['/supervisor/quality-tasks'], lucide: 'clipboard-check', module: 'quality' },
            { label: 'Shift Handover', href: '/supervisor/shift-handover', match: ['/supervisor/shift-handover'], lucide: 'arrow-left-right' },
        ],
    },
    {
        key: 'supervisor-reports',
        label: 'Reports',
        lucide: 'chart-column',
        href: '/supervisor/reports',
        match: ['/supervisor/reports'],
        module: 'reports',
        children: [
            { label: 'Work Order History', href: '/supervisor/reports', match: ['/supervisor/reports'], exact: true, lucide: 'history' },
        ],
    },
];

/** Add only server-authorized admin destinations; native supervisor routes stay intact. */
export function withGrantedAdminTabs(groups, grants = []) {
    const children = grants
        .filter((grant) => grant.url?.startsWith('/admin/'))
        .map((grant) => ({
            key: `supervisor-admin-${grant.key}`,
            label: grant.label,
            href: grant.url,
            match: [grant.url],
        }));

    if (!children.length) return groups;

    return [...groups, {
        key: 'supervisor-admin-panel',
        label: 'Admin Panel',
        lucide: 'layout-grid',
        match: children.flatMap((child) => child.match),
        children,
    }];
}
