/**
 * Admin sidebar navigation, ported from
 * resources/views/layouts/components/sidebar.blade.php (Admin section).
 *
 * Route URLs are hardcoded (this app has no Ziggy). They were resolved from
 * Laravel's router — keep them in sync if route paths change. `match` is an
 * array of path prefixes used to compute the active state against
 * window.location.pathname.
 *
 * Icons are Heroicons outline `d` path strings (same ones the Blade sidebar
 * used), rendered by the <Icon> component in AppLayout.
 */


/** Legacy `icon` key → Lucide name, so nav entries without an explicit
 *  `lucide` still render a real icon. */
export const ICON_LUCIDE = {
    "dashboard": "layout-dashboard",
    "bell": "bell",
    "calendar": "calendar-days",
    "users": "users",
    "clipboard": "clipboard-list",
    "beaker": "factory",
    "office": "building-2",
    "hr": "users-round",
    "cog": "wrench",
    "wifi": "wifi",
    "shield": "shield",
    "cube": "package",
    "packaging": "package",
    "settings": "settings",
    "chart": "chart-column",
    "webhook": "webhook"
};

export const ICONS = {
    dashboard: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    bell: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
    calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    users: 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z',
    clipboard: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    beaker: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z',
    office: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    hr: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    cog: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    wifi: 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0',
    shield: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    cube: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    packaging: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    settings: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    chart: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    webhook: 'M13 10V3L4 14h7v7l9-11h-7z',
};

/**
 * Top-level items rendered as single links (no children).
 * `alert: true` marks the Alerts item so it can show the badge + red active.
 */
export const ADMIN_LINKS = [
    { key: 'dashboard', label: 'Dashboard', href: '/admin/dashboard', icon: 'dashboard', match: ['/admin/dashboard'] },
    { key: 'alerts', label: 'Alerts', href: '/admin/alerts', icon: 'bell', match: ['/admin/alerts'], alert: true },
    // The admin's own mount of the shift monitor. Supervisors reach the same
    // screen at /supervisor/shift-monitor (supervisorNav.js) — each section
    // links its own URL, so what gates an entry's visibility (here, the
    // shift_monitor tab) also gates where it leads.
    {
        key: 'shift_monitor',
        label: 'Shift Monitor',
        href: '/admin/shift-monitor',
        lucide: 'activity',
        match: ['/admin/shift-monitor'],
    },
];

/**
 * Collapsible groups. `key` is the persisted expand-state id; `match` decides
 * whether the group auto-expands and highlights based on the current path.
 */
export const ADMIN_GROUPS = [
    {
        // key doubles as the gating tab (AppLayout's groupVisible falls back to
        // it), so it has to stay 'schedule' — the TabRegistry name. The label is
        // free to differ.
        key: 'schedule',
        label: 'Scheduler',
        icon: 'calendar',
        lucide: 'calendar-days',
        href: '/admin/schedule',
        match: ['/admin/schedule'],
        children: [
            { label: 'Planner', href: '/admin/schedule', match: ['/admin/schedule'], exact: true, lucide: 'calendar-range' },
        ],
    },
    {
        key: 'connectivity',
        label: 'Connectivity',
        icon: 'wifi',
        lucide: 'wifi',
        match: ['/admin/connectivity', '/admin/workstation-types', '/admin/workstation-devices'],
        children: [
            { label: 'Overview', href: '/admin/connectivity', match: ['/admin/connectivity'], exact: true, lucide: 'radio' },
            { label: 'MQTT', href: '/admin/connectivity/mqtt', match: ['/admin/connectivity/mqtt'], lucide: 'antenna' },
            { label: 'Modbus', href: '/admin/connectivity/modbus', match: ['/admin/connectivity/modbus'], lucide: 'cable' },
            { label: 'OPC UA', href: '/admin/connectivity/opcua', match: ['/admin/connectivity/opcua'], lucide: 'plug' },
            // The physical end of the same story: what a station is, and which
            // boxes are enrolled as one. They sit with the protocols rather than
            // in a separate Structure group.
            // Listed here, but still owned by the structure module — that is what
            // TabAccessMiddleware checks for these URLs. Without the tab they
            // would show for anyone with Connectivity on and 404 on click.
            { label: 'Workstation Types', href: '/admin/workstation-types', match: ['/admin/workstation-types'], tab: 'structure', lucide: 'monitor-cog' },
            { label: 'Workstation Devices', href: '/admin/workstation-devices', match: ['/admin/workstation-devices'], tab: 'structure', lucide: 'monitor' },
        ],
    },
    {
        key: 'orders',
        label: 'Orders',
        // `lucide` supersedes the path-based `icon` where present — same glyph the
        // page's own breadcrumb uses, so the sidebar and header agree.
        icon: 'clipboard',
        lucide: 'clipboard-list',
        href: '/admin/work-orders',
        match: ['/admin/work-orders', '/admin/customers', '/admin/priority-rules'],
        children: [
            { label: 'All Orders', href: '/admin/work-orders', match: ['/admin/work-orders'], tab: 'orders', lucide: 'list' },
            { label: 'Customers', href: '/admin/customers', match: ['/admin/customers'], tab: 'order_data', lucide: 'users' },
            { label: 'Priority Settings', href: '/admin/priority-rules', match: ['/admin/priority-rules'], tab: 'order_data', lucide: 'sliders-horizontal' },
        ],
    },
    {
        key: 'production',
        label: 'Production',
        icon: 'beaker',
        lucide: 'factory',
        match: [
            '/admin/product-types', '/admin/product-revisions', '/admin/traceability',
            '/admin/lot-sequences', '/admin/process-segments', '/admin/lines',
            '/admin/line-statuses', '/admin/view-templates',
            '/admin/issue-types', '/admin/scrap-reasons', '/packaging/eans',
        ],
        children: [
            {
                key: 'linesGroup',
                label: 'Production Lines',
                lucide: 'factory',
                match: ['/admin/lines', '/admin/line-statuses', '/admin/view-templates'],
                children: [
                    { label: 'All Production Lines', href: '/admin/lines', match: ['/admin/lines'], lucide: 'list' },
                    { label: 'Line Statuses', href: '/admin/line-statuses', match: ['/admin/line-statuses'], lucide: 'activity' },
                    { label: 'View Templates', href: '/admin/view-templates', match: ['/admin/view-templates'], lucide: 'layout-template' },
                ],
            },
            // Process templates and their BOMs hang off a specific product type
            // (/admin/product-types/{id}/process-templates/{id}/bom) — there is no
            // standalone list to link, so the product type is the way in.
            { label: 'Product Types', href: '/admin/product-types', match: ['/admin/product-types'], lucide: 'box' },
            { label: 'EAN Management', href: '/packaging/eans', match: ['/packaging/eans'], lucide: 'barcode' },
            // Fine-grained feature toggles: each renders under this (core) Production
            // group but is gated by its own module so it can be switched off alone.
            { label: 'Product Revisions', href: '/admin/product-revisions', match: ['/admin/product-revisions'], tab: 'product_engineering', lucide: 'git-branch' },
            { label: 'Traceability', href: '/admin/traceability', match: ['/admin/traceability'], tab: 'materials', lucide: 'route' },
            { label: 'LOT Sequences', href: '/admin/lot-sequences', match: ['/admin/lot-sequences'], lucide: 'hash' },
            { label: 'Process Segments', href: '/admin/process-segments', match: ['/admin/process-segments'], tab: 'product_engineering', lucide: 'workflow' },
            // What an operator picks from when reporting a problem, including
            // whether that choice blocks the work order. The page has always
            // existed with a full CRUD but was never listed in the React
            // sidebar, so the only way in was to type the URL.
            //
            // No `tab`: unlike Issues, this route is not tab-governed (it sits
            // behind role:Admin only), so gating it behind quality would hide a
            // page that still opens.
            { label: 'Issue Types', href: '/admin/issue-types', match: ['/admin/issue-types'], lucide: 'list-checks' },
            { label: 'Scrap Reasons', href: '/admin/scrap-reasons', match: ['/admin/scrap-reasons'], tab: 'quality', lucide: 'file-x' },
        ],
    },
    {
        key: 'warehouses',
        tab: 'warehouse',
        label: 'Warehouses',
        icon: 'cube',
        lucide: 'warehouse',
        match: ['/admin/materials', '/admin/material-types', '/admin/material-lots'],
        children: [
            { label: 'Materials', href: '/admin/materials', match: ['/admin/materials'], tab: 'materials', lucide: 'boxes' },
            { label: 'Material Types', href: '/admin/material-types', match: ['/admin/material-types'], tab: 'materials', lucide: 'tag' },
            { label: 'Material Lots', href: '/admin/material-lots', match: ['/admin/material-lots'], tab: 'materials', lucide: 'layers' },
        ],
    },
    {
        // As above: gated as 'reports', shown as Analytics.
        key: 'reports',
        label: 'Analytics',
        icon: 'chart',
        lucide: 'chart-column',
        match: ['/admin/reports', '/admin/cost-reports', '/admin/scrap-reports', '/admin/oee', '/admin/issues'],
        children: [
            { label: 'Work Order History', href: '/admin/reports', match: ['/admin/reports'], tab: 'reports', lucide: 'history' },
            // Analytical reports gated by the Advanced reports module, so a Lightweight
            // install keeps only Work Order History.
            { label: 'Production Cost Report', href: '/admin/cost-reports', match: ['/admin/cost-reports'], tab: 'advanced_reports', lucide: 'banknote' },
            { label: 'Scrap Reports', href: '/admin/scrap-reports', match: ['/admin/scrap-reports'], tab: 'advanced_reports', lucide: 'trash-2' },
            // Shown under Analytics but still gated by the module that owns the
            // URL. TabAccessMiddleware maps /admin/oee to the maintenance tab,
            // so without this the entry would be listed whenever Reports is on
            // and 404 on click for anyone whose maintenance module is off — it
            // used to be hidden for free by living inside the Maintenance group.
            { label: 'OEE Report', href: '/admin/oee', match: ['/admin/oee'], tab: 'maintenance', lucide: 'gauge' },
            // What the shop floor actually reported, read as history rather
            // than as a queue to work — the triage actions still live on the
            // page itself.
            { label: 'Reported Issues', href: '/admin/issues', match: ['/admin/issues'], tab: 'quality', lucide: 'circle-alert' },
        ],
    },
    {
        key: 'maintenance',
        label: 'Maintenance',
        icon: 'cog',
        lucide: 'wrench',
        match: ['/admin/maintenance-events', '/admin/maintenance-schedules', '/admin/tools'],
        children: [
            { label: 'Maintenance Events', href: '/admin/maintenance-events', match: ['/admin/maintenance-events'], lucide: 'calendar-clock' },
            { label: 'Maintenance Schedules', href: '/admin/maintenance-schedules', match: ['/admin/maintenance-schedules'], lucide: 'calendar-check' },
            { label: 'Tools', href: '/admin/tools', match: ['/admin/tools'], lucide: 'wrench' },
        ],
    },
    {
        key: 'webhooks',
        label: 'Webhooks',
        icon: 'webhook',
        lucide: 'webhook',
        href: '/admin/webhooks',
        match: ['/admin/webhooks'],
        children: [
            { label: 'Endpoints', href: '/admin/webhooks', match: ['/admin/webhooks'], exact: true, lucide: 'webhook' },
        ],
    },
    {
        key: 'adminGroup',
        tab: 'admin',
        label: 'Admin',
        icon: 'shield',
        lucide: 'shield',
        match: ['/admin/users', '/admin/logs', '/admin/audit-logs', '/admin/import', '/admin/trash'],
        children: [
            { label: 'Users & Accounts', href: '/admin/users', match: ['/admin/users'], lucide: 'users' },
            { label: 'Activity Logs', href: '/admin/logs/activity', match: ['/admin/logs/activity'], lucide: 'scroll-text' },
            { label: 'System Logs', href: '/admin/logs/system', match: ['/admin/logs/system'], lucide: 'file-text' },
            { label: 'Audit Logs', href: '/admin/audit-logs', match: ['/admin/audit-logs'], lucide: 'file-search' },
            // Unified importer — its own tab so it can be granted without the rest of Admin.
            { label: 'Import', href: '/admin/import', match: ['/admin/import'], tab: 'import', lucide: 'file-up' },
            { label: 'Trash', href: '/admin/trash', match: ['/admin/trash'], lucide: 'trash-2' },
        ],
    },
    {
        key: 'modulesGroup',
        tab: 'modules',
        label: 'Modules',
        icon: 'cube',
        lucide: 'blocks',
        href: '/admin/modules',
        match: ['/admin/modules'],
        children: [
            { label: 'Installed Modules', href: '/admin/modules', match: ['/admin/modules'], exact: true, lucide: 'blocks' },
        ],
    },
    {
        key: 'settings',
        // Not a feature module: profile, password and 2FA belong to whoever is
        // logged in, so this one is not gated behind an enabled-module tab.
        alwaysVisible: true,
        label: 'Settings',
        icon: 'settings',
        lucide: 'settings',
        href: '/settings',
        match: ['/settings', '/admin/custom-fields'],
        children: [
            {
                key: 'systemSettingsGroup',
                label: 'System Settings',
                lucide: 'sliders-horizontal',
                match: ['/settings/system'],
                // One page, six panels. The panel is chosen by ?tab=, so each entry
                // deep-links straight into its own section (System.jsx reads it).
                children: [
                    { label: 'General', href: '/settings/system?tab=general', query: 'general', match: ['/settings/system'], lucide: 'settings' },
                    { label: 'Production', href: '/settings/system?tab=production', query: 'production', match: ['/settings/system'], lucide: 'factory' },
                    { label: 'Schedule', href: '/settings/system?tab=schedule', query: 'schedule', match: ['/settings/system'], lucide: 'calendar-days' },
                    { label: 'Security', href: '/settings/system?tab=security', query: 'security', match: ['/settings/system'], lucide: 'shield' },
                    { label: 'Modules', href: '/settings/system?tab=modules', query: 'modules', match: ['/settings/system'], lucide: 'blocks' },
                    { label: 'Data', href: '/settings/system?tab=data', query: 'data', match: ['/settings/system'], lucide: 'database' },
                ],
            },
            { label: 'API Keys', href: '/settings/api-tokens', match: ['/settings/api-tokens'], lucide: 'key' },
            { label: 'Custom Fields', href: '/admin/custom-fields', match: ['/admin/custom-fields'], lucide: 'list-plus' },
            { label: 'Tab Access', href: '/settings/access', match: ['/settings/access'], lucide: 'lock' },
            { label: 'Profile', href: '/settings/profile', match: ['/settings/profile'], lucide: 'user-round' },
            { label: 'Change Password', href: '/settings/change-password', match: ['/settings/change-password'], lucide: 'key-round' },
            { label: 'Two-Factor Authentication', href: '/settings/two-factor/enable', match: ['/settings/two-factor'], lucide: 'smartphone' },
        ],
    },
];
