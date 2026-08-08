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
    { key: 'schedule', label: 'Schedule', href: '/admin/schedule', icon: 'calendar', match: ['/admin/schedule'], exact: true },
    // Hidden for now — re-enable to restore the Employees tab in the sidebar.
    // { label: 'Employees', href: '/admin/schedule/employees', icon: 'users', lucide: 'users', match: ['/admin/schedule/employees'] },
];

/**
 * Collapsible groups. `key` is the persisted expand-state id; `match` decides
 * whether the group auto-expands and highlights based on the current path.
 */
export const ADMIN_GROUPS = [
    {
        key: 'schedule',
        label: 'Schedule',
        icon: 'calendar',
        lucide: 'calendar-days',
        href: '/admin/schedule',
        match: ['/admin/schedule'],
        children: [
            { label: 'Planner', href: '/admin/schedule', match: ['/admin/schedule'], exact: true, lucide: 'calendar-range' },
            { label: 'Capacity', href: '/admin/schedule/capacity', match: ['/admin/schedule/capacity'], lucide: 'gauge' },
            { label: 'Employee', href: '/admin/schedule/employees', match: ['/admin/schedule/employees'], tab: 'hr', lucide: 'user-round' },
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
        match: ['/admin/work-orders', '/admin/customers', '/admin/priority-rules', '/admin/csv-import'],
        children: [
            { label: 'All Orders', href: '/admin/work-orders', match: ['/admin/work-orders'], lucide: 'list' },
            { label: 'Customers', href: '/admin/customers', match: ['/admin/customers'], lucide: 'users' },
            { label: 'Priority Settings', href: '/admin/priority-rules', match: ['/admin/priority-rules'], lucide: 'sliders-horizontal' },
            { label: 'CSV Import', href: '/admin/csv-import', match: ['/admin/csv-import'], lucide: 'file-up' },
        ],
    },
    {
        key: 'production',
        label: 'Production',
        icon: 'beaker',
        lucide: 'factory',
        match: [
            '/admin/product-types', '/admin/product-revisions', '/admin/materials', '/admin/material-lots',
            '/admin/traceability', '/admin/lot-sequences', '/admin/process-segments', '/admin/lines',
            '/admin/line-statuses', '/admin/view-templates', '/admin/shifts',
            '/admin/issues', '/admin/companies', '/admin/anomaly-reasons', '/admin/scrap-reasons',
        ],
        children: [
            { label: 'Product Types', href: '/admin/product-types', match: ['/admin/product-types'], lucide: 'box' },
            // Fine-grained feature toggles: each renders under this (core) Production
            // group but is gated by its own module so it can be switched off alone.
            { label: 'Product Revisions', href: '/admin/product-revisions', match: ['/admin/product-revisions'], tab: 'product_engineering', lucide: 'git-branch' },
            { label: 'Materials', href: '/admin/materials', match: ['/admin/materials', '/admin/materials-import'], tab: 'materials', lucide: 'boxes' },
            { label: 'Material Lots', href: '/admin/material-lots', match: ['/admin/material-lots'], tab: 'materials', lucide: 'layers' },
            { label: 'Traceability', href: '/admin/traceability', match: ['/admin/traceability'], tab: 'materials', lucide: 'route' },
            { label: 'LOT Sequences', href: '/admin/lot-sequences', match: ['/admin/lot-sequences'], lucide: 'hash' },
            { label: 'Process Segments', href: '/admin/process-segments', match: ['/admin/process-segments'], tab: 'product_engineering', lucide: 'workflow' },
            {
                key: 'linesGroup',
                label: 'Production Lines',
                lucide: 'factory',
                match: ['/admin/lines', '/admin/line-statuses', '/admin/view-templates', '/admin/shifts'],
                children: [
                    { label: 'All Lines', href: '/admin/lines', match: ['/admin/lines'], lucide: 'list' },
                    { label: 'Line Statuses', href: '/admin/line-statuses', match: ['/admin/line-statuses'], lucide: 'activity' },
                    { label: 'View Templates', href: '/admin/view-templates', match: ['/admin/view-templates'], lucide: 'layout-template' },
                    { label: 'Shifts', href: '/admin/shifts', match: ['/admin/shifts'], lucide: 'clock' },
                ],
            },
            // Issues + reason codes gated by the Quality module; Companies stand alone.
            { label: 'Issues', href: '/admin/issues', match: ['/admin/issues'], tab: 'quality', lucide: 'circle-alert' },
            { label: 'Companies', href: '/admin/companies', match: ['/admin/companies'], tab: 'companies', lucide: 'briefcase' },
            { label: 'Anomaly Reasons', href: '/admin/anomaly-reasons', match: ['/admin/anomaly-reasons'], tab: 'quality', lucide: 'file-warning' },
            { label: 'Scrap Reasons', href: '/admin/scrap-reasons', match: ['/admin/scrap-reasons'], tab: 'quality', lucide: 'file-x' },
        ],
    },
    {
        key: 'reports',
        label: 'Reports',
        icon: 'chart',
        lucide: 'chart-column',
        match: ['/admin/reports', '/admin/cost-reports', '/admin/scrap-reports', '/admin/non-conformance-reports', '/admin/net-requirements'],
        children: [
            { label: 'Work Order History', href: '/admin/reports', match: ['/admin/reports'], tab: 'reports', lucide: 'history' },
            // Analytical reports gated by the Advanced reports module, so a Lightweight
            // install keeps only Work Order History.
            { label: 'Production Cost', href: '/admin/cost-reports', match: ['/admin/cost-reports'], tab: 'advanced_reports', lucide: 'banknote' },
            { label: 'Scrap Reports', href: '/admin/scrap-reports', match: ['/admin/scrap-reports'], tab: 'advanced_reports', lucide: 'trash-2' },
            { label: 'Non-conformance', href: '/admin/non-conformance-reports', match: ['/admin/non-conformance-reports'], tab: 'advanced_reports', lucide: 'triangle-alert' },
            { label: 'Net requirements', href: '/admin/net-requirements', match: ['/admin/net-requirements'], tab: 'advanced_reports', lucide: 'calculator' },
        ],
    },
    {
        key: 'structure',
        label: 'Structure',
        icon: 'office',
        lucide: 'building-2',
        match: [
            '/admin/sites', '/admin/areas', '/admin/factories', '/admin/divisions',
            '/admin/workstation-types', '/admin/subassemblies', '/admin/workstation-devices',
        ],
        children: [
            { label: 'Sites', href: '/admin/sites', match: ['/admin/sites'], lucide: 'map-pin' },
            { label: 'Areas', href: '/admin/areas', match: ['/admin/areas'], lucide: 'map' },
            { label: 'Factories', href: '/admin/factories', match: ['/admin/factories'], lucide: 'building' },
            { label: 'Divisions', href: '/admin/divisions', match: ['/admin/divisions'], lucide: 'network' },
            { label: 'Workstation Types', href: '/admin/workstation-types', match: ['/admin/workstation-types'], lucide: 'monitor-cog' },
            { label: 'Workstation Devices', href: '/admin/workstation-devices', match: ['/admin/workstation-devices'], lucide: 'monitor' },
            { label: 'Subassemblies', href: '/admin/subassemblies', match: ['/admin/subassemblies'], lucide: 'component' },
        ],
    },
    {
        key: 'hr',
        label: 'HR',
        icon: 'hr',
        lucide: 'users-round',
        match: [
            '/admin/workers', '/admin/personnel-classes', '/admin/crews',
            '/admin/skills', '/admin/wage-groups', '/admin/worker-absences',
            '/admin/crew-break-windows',
        ],
        children: [
            { label: 'Workers', href: '/admin/workers', match: ['/admin/workers'], lucide: 'users' },
            { label: 'Absences', href: '/admin/worker-absences', match: ['/admin/worker-absences'], lucide: 'calendar-off' },
            { label: 'Personnel Classes', href: '/admin/personnel-classes', match: ['/admin/personnel-classes'], lucide: 'id-card' },
            { label: 'Crews', href: '/admin/crews', match: ['/admin/crews'], lucide: 'users-round' },
            { label: 'Break Windows', href: '/admin/crew-break-windows', match: ['/admin/crew-break-windows'], lucide: 'coffee' },
            { label: 'Skills', href: '/admin/skills', match: ['/admin/skills'], lucide: 'award' },
            { label: 'Wage Groups', href: '/admin/wage-groups', match: ['/admin/wage-groups'], lucide: 'wallet' },
        ],
    },
    {
        key: 'maintenance',
        label: 'Maintenance',
        icon: 'cog',
        lucide: 'wrench',
        match: [
            '/admin/maintenance-events', '/admin/maintenance-schedules', '/admin/tools',
            '/admin/cost-sources', '/admin/production-anomalies', '/inspections',
            '/admin/inspection-plans', '/admin/oee',
        ],
        children: [
            { label: 'Maintenance Events', href: '/admin/maintenance-events', match: ['/admin/maintenance-events'], lucide: 'calendar-clock' },
            { label: 'Maintenance Schedules', href: '/admin/maintenance-schedules', match: ['/admin/maintenance-schedules'], lucide: 'calendar-check' },
            { label: 'Tools', href: '/admin/tools', match: ['/admin/tools'], lucide: 'wrench' },
            { label: 'Cost Sources', href: '/admin/cost-sources', match: ['/admin/cost-sources'], lucide: 'receipt' },
            { label: 'Anomalies', href: '/admin/production-anomalies', match: ['/admin/production-anomalies'], lucide: 'triangle-alert' },
            { label: 'Inbound Inspections', href: '/inspections', match: ['/inspections'], lucide: 'clipboard-check' },
            { label: 'Inspection Plans', href: '/admin/inspection-plans', match: ['/admin/inspection-plans'], lucide: 'list-checks' },
            { label: 'OEE', href: '/admin/oee', match: ['/admin/oee'], lucide: 'gauge' },
        ],
    },
    {
        key: 'connectivity',
        label: 'Connectivity',
        icon: 'wifi',
        lucide: 'wifi',
        match: ['/admin/connectivity'],
        children: [
            { label: 'Overview', href: '/admin/connectivity', match: ['/admin/connectivity'], exact: true, lucide: 'radio' },
            { label: 'MQTT', href: '/admin/connectivity/mqtt', match: ['/admin/connectivity/mqtt'], lucide: 'antenna' },
            { label: 'Modbus', href: '/admin/connectivity/modbus', match: ['/admin/connectivity/modbus'], lucide: 'cable' },
            { label: 'OPC UA', href: '/admin/connectivity/opcua', match: ['/admin/connectivity/opcua'], lucide: 'plug' },
            { label: 'Machine Monitor', href: '/admin/machine-monitor', match: ['/admin/machine-monitor'], lucide: 'activity' },
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
        match: ['/admin/users', '/admin/logs', '/admin/audit-logs', '/admin/trash'],
        children: [
            { label: 'Users', href: '/admin/users', match: ['/admin/users'], lucide: 'users' },
            { label: 'Activity Logs', href: '/admin/logs/activity', match: ['/admin/logs/activity'], lucide: 'scroll-text' },
            { label: 'System Logs', href: '/admin/logs/system', match: ['/admin/logs/system'], lucide: 'file-text' },
            { label: 'Audit Logs', href: '/admin/audit-logs', match: ['/admin/audit-logs'], lucide: 'file-search' },
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
            { label: 'Installed', href: '/admin/modules', match: ['/admin/modules'], exact: true, lucide: 'blocks' },
            { label: 'Install', href: '/admin/modules/install', match: ['/admin/modules/install'], lucide: 'download' },
            // Disabled "coming soon" entry — parity with the Blade sidebar's Store item.
            { label: 'Store', disabled: true, badge: 'soon', title: 'Coming soon', lucide: 'store' },
        ],
    },
    // Packaging — a built-in feature whose nav used to be fed via MenuRegistry
    // (removed in the React migration). Hardcoded here like the other groups.
    // Ported from the original AppServiceProvider packaging menu registration.
    {
        key: 'packaging',
        label: 'Packaging',
        icon: 'packaging',
        lucide: 'package',
        href: '/packaging',
        match: ['/packaging', '/admin/pallets', '/admin/pallet-movements', '/logistics', '/supervisor/shift-handover'],
        children: [
            { label: 'Scanning Station', href: '/packaging/station', match: ['/packaging/station'], lucide: 'scan-barcode' },
            { label: 'Packaging Overview', href: '/packaging', match: ['/packaging'], exact: true, lucide: 'package' },
            { label: 'Shift Handover', href: '/supervisor/shift-handover', match: ['/supervisor/shift-handover'], lucide: 'arrow-left-right' },
            { label: 'Pallets', href: '/admin/pallets', match: ['/admin/pallets'], lucide: 'container' },
            { label: 'Pallet Logistics', href: '/logistics/pallets', match: ['/logistics/pallets'], lucide: 'truck' },
            { label: 'Move Pallet', href: '/logistics/move-pallet', match: ['/logistics/move-pallet'], lucide: 'move' },
            { label: 'Pallet Movements', href: '/admin/pallet-movements', match: ['/admin/pallet-movements'], lucide: 'list' },
            { label: 'EAN Management', href: '/packaging/eans', match: ['/packaging/eans'], lucide: 'barcode' },
            { label: 'Label Templates', href: '/packaging/label-templates', match: ['/packaging/label-templates'], lucide: 'tag' },
        ],
    },
];
