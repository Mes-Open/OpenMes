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
};

/**
 * Top-level items rendered as single links (no children).
 * `alert: true` marks the Alerts item so it can show the badge + red active.
 */
export const ADMIN_LINKS = [
    { label: 'Dashboard', href: '/admin/dashboard', icon: 'dashboard', match: ['/admin/dashboard'] },
    { label: 'Alerts', href: '/admin/alerts', icon: 'bell', match: ['/admin/alerts'], alert: true },
    { label: 'Schedule', href: '/admin/schedule', icon: 'calendar', match: ['/admin/schedule'], exact: true },
    // Hidden for now — re-enable to restore the Employees tab in the sidebar.
    // { label: 'Employees', href: '/admin/schedule/employees', icon: 'users', match: ['/admin/schedule/employees'] },
];

/**
 * Collapsible groups. `key` is the persisted expand-state id; `match` decides
 * whether the group auto-expands and highlights based on the current path.
 */
export const ADMIN_GROUPS = [
    {
        key: 'orders',
        label: 'Orders',
        icon: 'clipboard',
        href: '/admin/work-orders',
        match: ['/admin/work-orders', '/admin/csv-import'],
        children: [
            { label: 'All Orders', href: '/admin/work-orders', match: ['/admin/work-orders'] },
            { label: 'CSV Import', href: '/admin/csv-import', match: ['/admin/csv-import'] },
        ],
    },
    {
        key: 'production',
        label: 'Production',
        icon: 'beaker',
        match: [
            '/admin/product-types', '/admin/materials', '/admin/material-lots',
            '/admin/traceability', '/admin/lot-sequences', '/admin/process-segments', '/admin/lines',
            '/admin/line-statuses', '/admin/view-templates', '/admin/shifts',
            '/admin/issues', '/admin/companies', '/admin/anomaly-reasons', '/admin/scrap-reasons',
        ],
        children: [
            { label: 'Product Types', href: '/admin/product-types', match: ['/admin/product-types'] },
            { label: 'Materials', href: '/admin/materials', match: ['/admin/materials'] },
            { label: 'Material Lots', href: '/admin/material-lots', match: ['/admin/material-lots'] },
            { label: 'Traceability', href: '/admin/traceability', match: ['/admin/traceability'] },
            { label: 'LOT Sequences', href: '/admin/lot-sequences', match: ['/admin/lot-sequences'] },
            { label: 'Process Segments', href: '/admin/process-segments', match: ['/admin/process-segments'] },
            {
                key: 'linesGroup',
                label: 'Production Lines',
                match: ['/admin/lines', '/admin/line-statuses', '/admin/view-templates', '/admin/shifts'],
                children: [
                    { label: 'All Lines', href: '/admin/lines', match: ['/admin/lines'] },
                    { label: 'Line Statuses', href: '/admin/line-statuses', match: ['/admin/line-statuses'] },
                    { label: 'View Templates', href: '/admin/view-templates', match: ['/admin/view-templates'] },
                    { label: 'Shifts', href: '/admin/shifts', match: ['/admin/shifts'] },
                ],
            },
            { label: 'Issues', href: '/admin/issues', match: ['/admin/issues'] },
            { label: 'Companies', href: '/admin/companies', match: ['/admin/companies'] },
            { label: 'Anomaly Reasons', href: '/admin/anomaly-reasons', match: ['/admin/anomaly-reasons'] },
            { label: 'Scrap Reasons', href: '/admin/scrap-reasons', match: ['/admin/scrap-reasons'] },
        ],
    },
    {
        key: 'reports',
        label: 'Reports',
        icon: 'chart',
        match: ['/admin/reports', '/admin/cost-reports', '/admin/scrap-reports', '/admin/net-requirements'],
        children: [
            { label: 'Work Order History', href: '/admin/reports', match: ['/admin/reports'] },
            { label: 'Production Cost', href: '/admin/cost-reports', match: ['/admin/cost-reports'] },
            { label: 'Scrap Reports', href: '/admin/scrap-reports', match: ['/admin/scrap-reports'] },
            { label: 'Net requirements', href: '/admin/net-requirements', match: ['/admin/net-requirements'] },
        ],
    },
    {
        key: 'structure',
        label: 'Structure',
        icon: 'office',
        match: [
            '/admin/sites', '/admin/areas', '/admin/factories', '/admin/divisions',
            '/admin/workstation-types', '/admin/subassemblies',
        ],
        children: [
            { label: 'Sites', href: '/admin/sites', match: ['/admin/sites'] },
            { label: 'Areas', href: '/admin/areas', match: ['/admin/areas'] },
            { label: 'Factories', href: '/admin/factories', match: ['/admin/factories'] },
            { label: 'Divisions', href: '/admin/divisions', match: ['/admin/divisions'] },
            { label: 'Workstation Types', href: '/admin/workstation-types', match: ['/admin/workstation-types'] },
            { label: 'Subassemblies', href: '/admin/subassemblies', match: ['/admin/subassemblies'] },
        ],
    },
    {
        key: 'hr',
        label: 'HR',
        icon: 'hr',
        match: [
            '/admin/workers', '/admin/personnel-classes', '/admin/crews',
            '/admin/skills', '/admin/wage-groups', '/admin/worker-absences',
            '/admin/crew-break-windows',
        ],
        children: [
            { label: 'Workers', href: '/admin/workers', match: ['/admin/workers'] },
            { label: 'Absences', href: '/admin/worker-absences', match: ['/admin/worker-absences'] },
            { label: 'Personnel Classes', href: '/admin/personnel-classes', match: ['/admin/personnel-classes'] },
            { label: 'Crews', href: '/admin/crews', match: ['/admin/crews'] },
            { label: 'Break Windows', href: '/admin/crew-break-windows', match: ['/admin/crew-break-windows'] },
            { label: 'Skills', href: '/admin/skills', match: ['/admin/skills'] },
            { label: 'Wage Groups', href: '/admin/wage-groups', match: ['/admin/wage-groups'] },
        ],
    },
    {
        key: 'maintenance',
        label: 'Maintenance',
        icon: 'cog',
        match: [
            '/admin/maintenance-events', '/admin/maintenance-schedules', '/admin/tools',
            '/admin/cost-sources', '/admin/production-anomalies', '/inspections',
            '/admin/inspection-plans', '/admin/oee',
        ],
        children: [
            { label: 'Maintenance Events', href: '/admin/maintenance-events', match: ['/admin/maintenance-events'] },
            { label: 'Maintenance Schedules', href: '/admin/maintenance-schedules', match: ['/admin/maintenance-schedules'] },
            { label: 'Tools', href: '/admin/tools', match: ['/admin/tools'] },
            { label: 'Cost Sources', href: '/admin/cost-sources', match: ['/admin/cost-sources'] },
            { label: 'Anomalies', href: '/admin/production-anomalies', match: ['/admin/production-anomalies'] },
            { label: 'Inbound Inspections', href: '/inspections', match: ['/inspections'] },
            { label: 'Inspection Plans', href: '/admin/inspection-plans', match: ['/admin/inspection-plans'] },
            { label: 'OEE', href: '/admin/oee', match: ['/admin/oee'] },
        ],
    },
    {
        key: 'connectivity',
        label: 'Connectivity',
        icon: 'wifi',
        match: ['/admin/connectivity'],
        children: [
            { label: 'Overview', href: '/admin/connectivity', match: ['/admin/connectivity'], exact: true },
            { label: 'MQTT', href: '/admin/connectivity/mqtt', match: ['/admin/connectivity/mqtt'] },
            { label: 'Modbus', href: '/admin/connectivity/modbus', match: ['/admin/connectivity/modbus'] },
            { label: 'OPC UA', href: '/admin/connectivity/opcua', match: ['/admin/connectivity/opcua'] },
            { label: 'Machine Monitor', href: '/admin/machine-monitor', match: ['/admin/machine-monitor'] },
        ],
    },
    {
        key: 'adminGroup',
        label: 'Admin',
        icon: 'shield',
        match: ['/admin/users', '/admin/logs', '/admin/audit-logs', '/admin/trash'],
        children: [
            { label: 'Users', href: '/admin/users', match: ['/admin/users'] },
            { label: 'Activity Logs', href: '/admin/logs/activity', match: ['/admin/logs/activity'] },
            { label: 'System Logs', href: '/admin/logs/system', match: ['/admin/logs/system'] },
            { label: 'Audit Logs', href: '/admin/audit-logs', match: ['/admin/audit-logs'] },
            { label: 'Trash', href: '/admin/trash', match: ['/admin/trash'] },
        ],
    },
    {
        key: 'modulesGroup',
        label: 'Modules',
        icon: 'cube',
        href: '/admin/modules',
        match: ['/admin/modules'],
        children: [
            { label: 'Installed', href: '/admin/modules', match: ['/admin/modules'], exact: true },
            { label: 'Install', href: '/admin/modules/install', match: ['/admin/modules/install'] },
            // Disabled "coming soon" entry — parity with the Blade sidebar's Store item.
            { label: 'Store', disabled: true, badge: 'soon', title: 'Coming soon' },
        ],
    },
    // Packaging — a built-in feature whose nav used to be fed via MenuRegistry
    // (removed in the React migration). Hardcoded here like the other groups.
    // Ported from the original AppServiceProvider packaging menu registration.
    {
        key: 'packaging',
        label: 'Packaging',
        icon: 'packaging',
        href: '/packaging',
        match: ['/packaging', '/admin/pallets', '/supervisor/shift-handover'],
        children: [
            { label: 'Scanning Station', href: '/packaging/station', match: ['/packaging/station'] },
            { label: 'Packaging Overview', href: '/packaging', match: ['/packaging'], exact: true },
            { label: 'Shift Handover', href: '/supervisor/shift-handover', match: ['/supervisor/shift-handover'] },
            { label: 'Pallets', href: '/admin/pallets', match: ['/admin/pallets'] },
            { label: 'EAN Management', href: '/packaging/eans', match: ['/packaging/eans'] },
            { label: 'Label Templates', href: '/packaging/label-templates', match: ['/packaging/label-templates'] },
        ],
    },
];
