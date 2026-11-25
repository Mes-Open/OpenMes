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
    { key: 'dashboard', label: '工作台', href: '/admin/dashboard', icon: 'dashboard', match: ['/admin/dashboard'] },
    { key: 'alerts', label: '预警中心', href: '/admin/alerts', icon: 'bell', match: ['/admin/alerts'], alert: true },
];

/**
 * 业务流程分组：订单 → 计划 → 采购/来料 → 品控 → 生产 → 仓储。
 * 支撑功能放在流程组之后，基础档案只在页面内互相引用，不重复占用侧栏。
 * `key` 保持稳定，模块注入和侧栏展开状态都依赖它。
 */
export const ADMIN_GROUPS = [
    {
        key: 'orders',
        order: 20,
        label: '订单与工单',
        icon: 'clipboard',
        lucide: 'clipboard-list',
        href: '/admin/work-orders',
        match: ['/admin/work-orders', '/admin/work-order-change-requests', '/admin/customers', '/admin/priority-rules'],
        children: [
            { label: '全部订单', href: '/admin/work-orders', match: ['/admin/work-orders'], tab: 'orders', lucide: 'list' },
            { label: '变更与取消', href: '/admin/work-order-change-requests', match: ['/admin/work-order-change-requests'], tab: 'orders', lucide: 'git-pull-request' },
            { label: '客户档案', href: '/admin/customers', match: ['/admin/customers'], tab: 'order_data', lucide: 'users' },
            { label: '订单优先级', href: '/admin/priority-rules', match: ['/admin/priority-rules'], tab: 'order_data', lucide: 'sliders-horizontal' },
        ],
    },
    {
        key: 'planning',
        order: 25,
        label: '计划与排程',
        icon: 'calendar',
        lucide: 'calendar-days',
        match: ['/admin/schedule', '/admin/shifts'],
        children: [
            { label: '排产计划', href: '/admin/schedule', match: ['/admin/schedule'], tab: 'schedule', lucide: 'calendar-days' },
            { label: '计划列表', href: '/admin/schedule/list', match: ['/admin/schedule/list'], tab: 'schedule', lucide: 'list' },
            { label: '产能负荷', href: '/admin/schedule/capacity', match: ['/admin/schedule/capacity'], tab: 'schedule', lucide: 'bar-chart-3' },
            { label: '自动排产', href: '/admin/schedule/auto', match: ['/admin/schedule/auto'], tab: 'schedule', lucide: 'sparkles' },
            { label: '人员排班', href: '/admin/schedule/employees', match: ['/admin/schedule/employees'], tab: 'schedule', lucide: 'users-round' },
            { label: '班次定义', href: '/admin/shifts', match: ['/admin/shifts'], tab: 'production', lucide: 'clock' },
        ],
    },
    {
        key: 'purchasing',
        order: 27,
        label: '采购与来料',
        icon: 'cube',
        lucide: 'truck',
        match: ['/admin/purchasing', '/admin/net-requirements', '/admin/companies'],
        children: [
            { label: '净需求与缺口', href: '/admin/net-requirements', match: ['/admin/net-requirements'], tab: 'advanced_reports', lucide: 'clipboard-list' },
            { label: '采购订单', href: '/admin/purchasing/orders', match: ['/admin/purchasing/orders'], tab: 'purchasing', lucide: 'file-text' },
            { label: '到货登记', href: '/admin/purchasing/receipts', match: ['/admin/purchasing/receipts'], tab: 'purchasing', lucide: 'package-check' },
            { label: '供应商对账', href: '/admin/purchasing/reconciliation', match: ['/admin/purchasing/reconciliation'], tab: 'purchasing', lucide: 'scale' },
            { label: '供应商与往来单位', href: '/admin/companies', match: ['/admin/companies'], tab: 'companies', lucide: 'building-2' },
        ],
    },
    {
        key: 'quality',
        order: 29,
        label: '品控',
        icon: 'shield',
        lucide: 'badge-check',
        match: ['/admin/quality', '/admin/inspection-plans', '/admin/quality-control-triggers', '/admin/issues', '/admin/coa', '/admin/supplier-quality', '/admin/tea-bases', '/admin/flower-lots', '/admin/blend-formulas', '/admin/sensory', '/admin/customer-flavor-standards', '/admin/scrap-reasons'],
        children: [
            { key: 'qualityExecution', label: '检验与放行', lucide: 'clipboard-check', match: ['/admin/quality', '/admin/inspection-plans', '/admin/quality-control-triggers', '/admin/issues', '/admin/coa', '/admin/supplier-quality'], children: [
                { label: '质量指挥台', href: '/admin/quality', match: ['/admin/quality'], tab: 'quality', lucide: 'gauge' },
                { label: '来料检验计划', href: '/admin/inspection-plans', match: ['/admin/inspection-plans'], tab: 'quality', lucide: 'clipboard-check' },
                { label: '质检触发', href: '/admin/quality-control-triggers', match: ['/admin/quality-control-triggers'], tab: 'quality', lucide: 'zap' },
                { label: '质量异常', href: '/admin/issues', match: ['/admin/issues'], tab: 'quality', lucide: 'circle-alert' },
                { label: 'COA 与放行', href: '/admin/coa', match: ['/admin/coa'], tab: 'quality', lucide: 'file-check' },
                { label: '供应商质量', href: '/admin/supplier-quality', match: ['/admin/supplier-quality'], tab: 'quality', lucide: 'building-2' },
            ] },
            { key: 'qualityStandards', label: '原料与感官', lucide: 'flask-conical', match: ['/admin/tea-bases', '/admin/flower-lots', '/admin/blend-formulas', '/admin/sensory', '/admin/customer-flavor-standards'], children: [
                { label: '茶底档案', href: '/admin/tea-bases', match: ['/admin/tea-bases'], tab: 'quality', lucide: 'archive' },
                { label: '鲜花批次', href: '/admin/flower-lots', match: ['/admin/flower-lots'], tab: 'quality', lucide: 'timer' },
                { label: '拼配版本', href: '/admin/blend-formulas', match: ['/admin/blend-formulas'], tab: 'quality', lucide: 'layers' },
                { label: '感官盲评', href: '/admin/sensory', match: ['/admin/sensory'], tab: 'quality', lucide: 'eye' },
                { label: '客户风味标准', href: '/admin/customer-flavor-standards', match: ['/admin/customer-flavor-standards'], tab: 'quality', lucide: 'book-open' },
            ] },
            { label: '报废原因', href: '/admin/scrap-reasons', match: ['/admin/scrap-reasons'], tab: 'quality', lucide: 'file-x' },
        ],
    },
    {
        key: 'production',
        order: 30,
        label: '生产制造',
        icon: 'beaker',
        lucide: 'factory',
        match: ['/admin/shift-monitor', '/admin/shift-board', '/admin/machine-monitor', '/admin/lines', '/admin/line-statuses', '/admin/view-templates', '/admin/product-types', '/admin/product-revisions', '/admin/process-segments', '/admin/lot-sequences', '/packaging/station', '/packaging/label-templates', '/packaging/eans', '/admin/downtime-reasons'],
        children: [
            { label: '班次监控', href: '/admin/shift-monitor', match: ['/admin/shift-monitor'], tab: 'shift_monitor', lucide: 'activity' },
            { label: '工厂看板', href: '/admin/shift-board', match: ['/admin/shift-board'], tab: 'shift_monitor', lucide: 'layout-grid' },
            { label: '设备监控', href: '/admin/machine-monitor', match: ['/admin/machine-monitor'], tab: 'connectivity', lucide: 'monitor' },
            { key: 'productionProcess', label: '产线与工艺', lucide: 'factory', match: ['/admin/lines', '/admin/line-statuses', '/admin/view-templates', '/admin/product-types', '/admin/product-revisions', '/admin/process-segments', '/admin/lot-sequences'], children: [
                { label: '产线管理', href: '/admin/lines', match: ['/admin/lines'], tab: 'production', lucide: 'factory' },
                { label: '产线状态', href: '/admin/line-statuses', match: ['/admin/line-statuses'], tab: 'production', lucide: 'activity' },
                { label: '视图模板', href: '/admin/view-templates', match: ['/admin/view-templates'], tab: 'production', lucide: 'layout-template' },
                { label: '产品类型', href: '/admin/product-types', match: ['/admin/product-types'], tab: 'production', lucide: 'box' },
                { label: '产品版本', href: '/admin/product-revisions', match: ['/admin/product-revisions'], tab: 'product_engineering', lucide: 'git-branch' },
                { label: '工序段', href: '/admin/process-segments', match: ['/admin/process-segments'], tab: 'product_engineering', lucide: 'workflow' },
                { label: '批号序列', href: '/admin/lot-sequences', match: ['/admin/lot-sequences'], tab: 'production', lucide: 'hash' },
            ] },
            { key: 'packagingGroup', label: '包装与标签', lucide: 'package', match: ['/packaging/station', '/packaging/label-templates', '/packaging/eans'], children: [
                { label: '包装工位', href: '/packaging/station', match: ['/packaging/station'], lucide: 'package' },
                { label: '标签模板', href: '/packaging/label-templates', match: ['/packaging/label-templates'], lucide: 'tag' },
                { label: 'EAN 管理', href: '/packaging/eans', match: ['/packaging/eans'], lucide: 'barcode' },
            ] },
            { label: '停机原因', href: '/admin/downtime-reasons', match: ['/admin/downtime-reasons'], tab: 'quality', lucide: 'octagon-pause' },
        ],
    },
    {
        key: 'warehouses',
        order: 40,
        tab: 'warehouse',
        label: '仓储与物流',
        icon: 'cube',
        lucide: 'warehouse',
        match: ['/admin/materials', '/admin/material-types', '/admin/material-lots', '/admin/pallets', '/admin/warehouses'],
        children: [
            { label: '库存台账', href: '/admin/warehouses/stock', match: ['/admin/warehouses/stock'], tab: 'warehouse', lucide: 'archive' },
            { key: 'locationGroup', label: '库位与移库', lucide: 'map-pin', match: ['/admin/warehouses/locations'], children: [
                { label: '库位管理', href: '/admin/warehouses/locations', match: ['/admin/warehouses/locations'], tab: 'warehouse', lucide: 'map-pin' },
                { label: '库位对账', href: '/admin/warehouses/locations/reconcile', match: ['/admin/warehouses/locations/reconcile'], tab: 'warehouse', lucide: 'scale' },
                { label: '移库记录', href: '/admin/warehouses/locations/moves', match: ['/admin/warehouses/locations/moves'], tab: 'warehouse', lucide: 'arrow-left-right' },
                { label: '库位标签', href: '/admin/warehouses/locations/labels', match: ['/admin/warehouses/locations/labels'], tab: 'warehouse', lucide: 'tag' },
            ] },
            { key: 'materialGroup', label: '物料档案', lucide: 'boxes', match: ['/admin/materials', '/admin/material-types', '/admin/material-lots'], children: [
                { label: '物料档案', href: '/admin/materials', match: ['/admin/materials'], tab: 'materials', lucide: 'boxes' },
                { label: '物料类型', href: '/admin/material-types', match: ['/admin/material-types'], tab: 'materials', lucide: 'tag' },
                { label: '物料批次', href: '/admin/material-lots', match: ['/admin/material-lots'], tab: 'materials', lucide: 'layers' },
            ] },
            { label: '标签平衡', href: '/admin/warehouses', match: ['/admin/warehouses'], exact: true, tab: 'warehouse', lucide: 'scale' },
            { label: '托盘管理', href: '/admin/pallets', match: ['/admin/pallets'], tab: 'packaging', lucide: 'container' },
        ],
    },
    {
        key: 'connectivity',
        order: 60,
        label: '设备与采集',
        icon: 'wifi',
        lucide: 'wifi',
        match: ['/admin/connectivity', '/admin/workstation-types', '/admin/workstation-devices'],
        children: [
            { label: '采集总览', href: '/admin/connectivity', match: ['/admin/connectivity'], exact: true, tab: 'connectivity', lucide: 'radio' },
            { label: 'MQTT', href: '/admin/connectivity/mqtt', match: ['/admin/connectivity/mqtt'], tab: 'connectivity', lucide: 'antenna' },
            { label: 'Modbus', href: '/admin/connectivity/modbus', match: ['/admin/connectivity/modbus'], tab: 'connectivity', lucide: 'cable' },
            { label: 'OPC UA', href: '/admin/connectivity/opcua', match: ['/admin/connectivity/opcua'], tab: 'connectivity', lucide: 'plug' },
            { label: '设备计数', href: '/admin/connectivity/counters', match: ['/admin/connectivity/counters'], tab: 'machine_counters', lucide: 'gauge' },
            { label: '工位类型', href: '/admin/workstation-types', match: ['/admin/workstation-types'], tab: 'structure', lucide: 'monitor-cog' },
            { label: '工位设备', href: '/admin/workstation-devices', match: ['/admin/workstation-devices'], tab: 'structure', lucide: 'monitor' },
        ],
    },
    {
        key: 'hr',
        order: 65,
        tab: 'hr',
        label: '人力资源',
        icon: 'hr',
        lucide: 'users-round',
        match: ['/admin/workers'],
        children: [
            { label: '员工档案', href: '/admin/workers', match: ['/admin/workers'], lucide: 'contact' },
        ],
    },
    {
        key: 'maintenance',
        order: 70,
        tab: 'maintenance',
        label: '设备运维',
        icon: 'cog',
        lucide: 'wrench',
        match: ['/admin/maintenance-events', '/admin/maintenance-schedules', '/admin/tools', '/admin/calibrations'],
        children: [
            { label: '保养事件', href: '/admin/maintenance-events', match: ['/admin/maintenance-events'], lucide: 'calendar-clock' },
            { label: '保养计划', href: '/admin/maintenance-schedules', match: ['/admin/maintenance-schedules'], lucide: 'calendar-check' },
            { label: '工具管理', href: '/admin/tools', match: ['/admin/tools'], lucide: 'wrench' },
            { label: '设备校准', href: '/admin/calibrations', match: ['/admin/calibrations'], lucide: 'gauge' },
        ],
    },
    {
        key: 'reports',
        order: 75,
        label: '报表与绩效',
        icon: 'chart',
        lucide: 'chart-column',
        match: ['/admin/reports', '/admin/cost-reports', '/admin/scrap-reports', '/admin/non-conformance-reports', '/admin/oee'],
        children: [
            { label: '工单历史', href: '/admin/reports', match: ['/admin/reports'], tab: 'reports', lucide: 'history' },
            { label: '生产成本', href: '/admin/cost-reports', match: ['/admin/cost-reports'], tab: 'advanced_reports', lucide: 'banknote' },
            { label: '报废分析', href: '/admin/scrap-reports', match: ['/admin/scrap-reports'], tab: 'advanced_reports', lucide: 'trash-2' },
            { label: '不合格分析', href: '/admin/non-conformance-reports', match: ['/admin/non-conformance-reports'], tab: 'advanced_reports', lucide: 'file-warning' },
            { label: 'OEE 报表', href: '/admin/oee', match: ['/admin/oee'], tab: 'maintenance', lucide: 'gauge' },
        ],
    },
    {
        key: 'webhooks',
        order: 90,
        tab: 'webhooks',
        label: '系统集成',
        icon: 'webhook',
        lucide: 'webhook',
        match: ['/admin/webhooks', '/admin/integrations'],
        children: [
            { label: 'Webhooks', href: '/admin/webhooks', match: ['/admin/webhooks'], lucide: 'webhook' },
            { label: '接口配置', href: '/admin/integrations', match: ['/admin/integrations'], lucide: 'plug' },
        ],
    },
    {
        key: 'adminGroup',
        order: 100,
        tab: 'admin',
        label: '系统管理',
        icon: 'shield',
        lucide: 'shield',
        match: ['/admin/users', '/admin/logs', '/admin/audit-logs', '/admin/import', '/admin/trash'],
        children: [
            { label: '用户与账号', href: '/admin/users', match: ['/admin/users'], lucide: 'users' },
            { label: '活动日志', href: '/admin/logs/activity', match: ['/admin/logs/activity'], lucide: 'scroll-text' },
            { label: '系统日志', href: '/admin/logs/system', match: ['/admin/logs/system'], lucide: 'file-text' },
            { label: '审计日志', href: '/admin/audit-logs', match: ['/admin/audit-logs'], lucide: 'file-search' },
            { label: '数据导入', href: '/admin/import', match: ['/admin/import'], tab: 'import', lucide: 'file-up' },
            { label: '回收站', href: '/admin/trash', match: ['/admin/trash'], lucide: 'trash-2' },
        ],
    },
    {
        key: 'modulesGroup',
        order: 110,
        tab: 'modules',
        label: '模块管理',
        icon: 'packaging',
        lucide: 'blocks',
        href: '/admin/modules',
        match: ['/admin/modules'],
        children: [
            { label: '已安装模块', href: '/admin/modules', match: ['/admin/modules'], exact: true, lucide: 'blocks' },
        ],
    },
    {
        key: 'settings',
        order: 120,
        alwaysVisible: true,
        label: '设置',
        icon: 'settings',
        lucide: 'settings',
        href: '/settings',
        match: ['/settings', '/admin/custom-fields', '/admin/unit-aliases'],
        children: [
            {
                key: 'systemSettingsGroup',
                label: '系统设置',
                lucide: 'sliders-horizontal',
                match: ['/settings/system'],
                children: [
                    { label: '常规', href: '/settings/system?tab=general', query: 'general', match: ['/settings/system'], lucide: 'settings' },
                    { label: '生产', href: '/settings/system?tab=production', query: 'production', match: ['/settings/system'], lucide: 'factory' },
                    { label: '排程', href: '/settings/system?tab=schedule', query: 'schedule', match: ['/settings/system'], lucide: 'calendar-days' },
                    { label: '安全', href: '/settings/system?tab=security', query: 'security', match: ['/settings/system'], lucide: 'shield' },
                    { label: '模块', href: '/settings/system?tab=modules', query: 'modules', match: ['/settings/system'], lucide: 'blocks' },
                    { label: '数据', href: '/settings/system?tab=data', query: 'data', match: ['/settings/system'], lucide: 'database' },
                ],
            },
            { label: 'API 密钥', href: '/settings/api-tokens', match: ['/settings/api-tokens'], lucide: 'key' },
            { label: '自定义字段', href: '/admin/custom-fields', match: ['/admin/custom-fields'], lucide: 'list-plus' },
            { label: '单位换算', href: '/admin/unit-aliases', match: ['/admin/unit-aliases'], lucide: 'ruler' },
            { label: '权限矩阵', href: '/settings/access', match: ['/settings/access'], lucide: 'lock' },
            { label: '个人资料', href: '/settings/profile', match: ['/settings/profile'], lucide: 'user-round' },
            { label: '修改密码', href: '/settings/change-password', match: ['/settings/change-password'], lucide: 'key-round' },
            { label: '双重验证', href: '/settings/two-factor/enable', match: ['/settings/two-factor'], lucide: 'smartphone' },
        ],
    },
];
