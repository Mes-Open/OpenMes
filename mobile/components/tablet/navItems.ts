/**
 * Tablet sidebar navigation tree — a 1:1 port of the web admin sidebar
 * (backend/resources/js/layouts/adminNav.js): same links, same collapsible
 * groups (and the nested Production → Lines subgroup), same labels and order.
 *
 * `route` is the expo-router path to push. Web destinations that have no mobile
 * screen yet are marked `disabled` (shown dimmed, like the web's "Store" item)
 * so the menu structure stays identical without dead links. `match` (a runtime
 * pathname prefix) is derived from `route` unless given explicitly.
 */
import type { HeroIconKey } from '@/components/ui/HeroIcon';

export interface NavNode {
  key: string;
  label: string;
  icon?: HeroIconKey; // top-level links + group headers
  route?: string; // leaf destination
  match?: string; // pathname prefix for active state (else derived from route)
  alert?: boolean; // show the alerts badge
  disabled?: boolean; // no mobile screen yet
  /**
   * TabRegistry tab key gating this top-level entry, mirroring the web
   * adminNav's `key`/`tab` (see filterByTabs). Only set on top-level links and
   * groups — children inherit their parent's visibility.
   */
  tab?: string;
  children?: NavNode[]; // group
}

/** Strip expo-router group segments — `/(drawer)/admin/x` → `/admin/x`. */
export function toMatch(route: string): string {
  return route.replace(/\/\([^)]+\)/g, '') || '/';
}

// ── Admin: full 1:1 tree ─────────────────────────────────────────────────────
const ADMIN: NavNode[] = [
  { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', route: '/(drawer)/admin/dashboard', tab: 'dashboard' },
  { key: 'alerts', label: 'Alerts', icon: 'bell', route: '/(drawer)/admin/alerts-dashboard', alert: true, tab: 'alerts' },
  { key: 'schedule', label: 'Schedule', icon: 'calendar', route: '/(drawer)/admin/schedule', tab: 'schedule' },

  {
    key: 'scheduleGroup',
    label: 'Schedule',
    icon: 'calendar',
    tab: 'schedule',
    children: [
      { key: 'planner', label: 'Planner', route: '/(drawer)/admin/schedule' },
      { key: 'capacity', label: 'Capacity', route: '/(drawer)/admin/schedule-capacity' },
      { key: 'employee', label: 'Employee', route: '/(drawer)/admin/employee-schedule' },
    ],
  },
  {
    key: 'orders',
    label: 'Orders',
    icon: 'clipboard',
    tab: 'orders',
    children: [
      { key: 'all-orders', label: 'All Orders', route: '/(drawer)/admin/work-orders' },
      { key: 'csv-import', label: 'CSV Import', route: '/(drawer)/orders/imports' },
    ],
  },
  {
    key: 'production',
    label: 'Production',
    icon: 'beaker',
    tab: 'production',
    children: [
      { key: 'product-types', label: 'Product Types', route: '/(drawer)/production/product-types' },
      { key: 'materials', label: 'Materials', route: '/(drawer)/admin/materials' },
      { key: 'material-lots', label: 'Material Lots', route: '/(drawer)/admin/material-lots' },
      { key: 'traceability', label: 'Traceability', route: '/(drawer)/admin/lot-genealogy' },
      { key: 'lot-sequences', label: 'LOT Sequences', route: '/(drawer)/admin/lot-sequences' },
      { key: 'process-segments', label: 'Process Segments', route: '/(drawer)/production/process-segments' },
      {
        key: 'linesGroup',
        label: 'Production Lines',
        children: [
          { key: 'all-lines', label: 'All Lines', route: '/(drawer)/structure/lines' },
          { key: 'line-statuses', label: 'Line Statuses', route: '/(drawer)/admin/line-statuses' },
          { key: 'view-templates', label: 'View Templates', route: '/(drawer)/admin/view-templates' },
          { key: 'shifts', label: 'Shifts', route: '/(drawer)/production/shifts' },
        ],
      },
      { key: 'issues', label: 'Issues', route: '/(drawer)/admin/issues' },
      { key: 'companies', label: 'Companies', route: '/(drawer)/admin/companies' },
      { key: 'anomaly-reasons', label: 'Anomaly Reasons', route: '/(drawer)/admin/anomaly-reasons' },
      { key: 'scrap-reasons', label: 'Scrap Reasons', route: '/(drawer)/admin/scrap-reasons' },
    ],
  },
  {
    key: 'reports',
    label: 'Reports',
    icon: 'chart',
    tab: 'reports',
    children: [
      { key: 'wo-history', label: 'Work Order History', route: '/(drawer)/admin/reports' },
      { key: 'cost-reports', label: 'Production Cost', route: '/(drawer)/admin/reports?tab=production_cost' },
      { key: 'scrap-reports', label: 'Scrap Reports', route: '/(drawer)/admin/reports?tab=scrap' },
      { key: 'nc-reports', label: 'Non-conformance', route: '/(drawer)/admin/reports?tab=non_conformance' },
      { key: 'net-requirements', label: 'Net requirements', route: '/(drawer)/admin/reports?tab=net_requirements' },
    ],
  },
  {
    key: 'structure',
    label: 'Structure',
    icon: 'office',
    tab: 'structure',
    children: [
      { key: 'sites', label: 'Sites', route: '/(drawer)/structure/sites' },
      { key: 'areas', label: 'Areas', route: '/(drawer)/structure/sites' },
      { key: 'factories', label: 'Factories', route: '/(drawer)/structure/factories' },
      { key: 'divisions', label: 'Divisions', route: '/(drawer)/structure/divisions' },
      { key: 'workstation-types', label: 'Workstation Types', route: '/(drawer)/structure/workstation-types' },
      { key: 'subassemblies', label: 'Subassemblies', route: '/(drawer)/admin/subassemblies' },
    ],
  },
  {
    key: 'hr',
    label: 'HR',
    icon: 'hr',
    tab: 'hr',
    children: [
      { key: 'workers', label: 'Workers', route: '/(drawer)/hr/workers' },
      { key: 'absences', label: 'Absences', route: '/(drawer)/hr/absences' },
      { key: 'personnel-classes', label: 'Personnel Classes', route: '/(drawer)/hr/personnel-classes' },
      { key: 'crews', label: 'Crews', route: '/(drawer)/hr/crews' },
      { key: 'break-windows', label: 'Break Windows', route: '/(drawer)/hr/break-windows' },
      { key: 'skills', label: 'Skills', route: '/(drawer)/hr/skills' },
      { key: 'wage-groups', label: 'Wage Groups', route: '/(drawer)/hr/wage-groups' },
    ],
  },
  {
    key: 'maintenance',
    label: 'Maintenance',
    icon: 'cog',
    tab: 'maintenance',
    children: [
      { key: 'maint-events', label: 'Maintenance Events', route: '/(drawer)/admin/maintenance-events' },
      { key: 'maint-schedules', label: 'Maintenance Schedules', route: '/(drawer)/maintenance/schedules' },
      { key: 'tools', label: 'Tools', route: '/(drawer)/maintenance/tools' },
      { key: 'cost-sources', label: 'Cost Sources', route: '/(drawer)/admin/cost-sources' },
      { key: 'anomalies', label: 'Anomalies', route: '/(drawer)/admin/production-anomalies' },
      { key: 'inbound-inspections', label: 'Inbound Inspections', route: '/(drawer)/quality/inspections' },
      { key: 'inspection-plans', label: 'Inspection Plans', route: '/(drawer)/admin/inspection-plans' },
      { key: 'qc-triggers', label: 'QC Triggers', route: '/(drawer)/admin/qc-triggers' },
      { key: 'oee', label: 'OEE', route: '/(drawer)/admin/oee' },
    ],
  },
  {
    key: 'connectivity',
    label: 'Connectivity',
    icon: 'wifi',
    tab: 'connectivity',
    children: [
      { key: 'conn-overview', label: 'Overview', route: '/(drawer)/connectivity' },
      { key: 'mqtt', label: 'MQTT', route: '/(drawer)/connectivity/mqtt' },
      { key: 'modbus', label: 'Modbus', route: '/(drawer)/connectivity/modbus' },
      { key: 'opcua', label: 'OPC UA', route: '/(drawer)/connectivity/opcua' },
      { key: 'machine-monitor', label: 'Machine Monitor', route: '/(drawer)/supervisor/machine-monitor' },
    ],
  },
  {
    key: 'webhooks',
    label: 'Webhooks',
    icon: 'webhook',
    tab: 'webhooks',
    children: [{ key: 'endpoints', label: 'Endpoints', route: '/(drawer)/admin/webhooks' }],
  },
  {
    key: 'adminGroup',
    label: 'Admin',
    icon: 'shield',
    tab: 'admin',
    children: [
      { key: 'users', label: 'Users', route: '/(drawer)/admin/users' },
      { key: 'integrations', label: 'Integrations', route: '/(drawer)/admin/integrations' },
      { key: 'custom-fields', label: 'Custom Fields', route: '/(drawer)/admin/custom-fields' },
      { key: 'activity-logs', label: 'Activity Logs', route: '/(drawer)/admin/activity-logs' },
      { key: 'system-logs', label: 'System Logs', route: '/(drawer)/admin/system-logs' },
      { key: 'audit-logs', label: 'Audit Logs', route: '/(drawer)/admin/audit-logs' },
      { key: 'trash', label: 'Trash', route: '/(drawer)/admin/trash' },
    ],
  },
  {
    key: 'modulesGroup',
    label: 'Modules',
    icon: 'cube',
    tab: 'modules',
    children: [
      { key: 'installed', label: 'Installed', route: '/(drawer)/admin/modules' },
      { key: 'install', label: 'Install', disabled: true },
      { key: 'store', label: 'Store', disabled: true },
    ],
  },
  {
    key: 'packaging',
    label: 'Packaging',
    icon: 'packaging',
    tab: 'packaging',
    children: [
      { key: 'scan-station', label: 'Scanning Station', disabled: true },
      { key: 'pkg-overview', label: 'Packaging Overview', route: '/(drawer)/pakowanie' },
      { key: 'shift-handover', label: 'Shift Handover', route: '/(drawer)/supervisor/shift-handover' },
      { key: 'pallets', label: 'Pallets', route: '/(drawer)/admin/pallets' },
      { key: 'eans', label: 'EAN Management', route: '/(drawer)/pakowanie/eans' },
      { key: 'label-templates', label: 'Label Templates', route: '/(drawer)/pakowanie/label-templates' },
    ],
  },
];

// ── Operator: curated flat list (obsolete — operators move to a no-sidebar
// layout, but kept so any operator code path still compiles) ─────────────────
const OPERATOR: NavNode[] = [
  { key: 'orders', label: 'Queue', icon: 'clipboard', route: '/(drawer)/orders/work-orders' },
];

/**
 * Admins and supervisors share the one ADMIN tree, filtered by the user's
 * accessibleTabs (see filterByTabs) exactly like the web — a supervisor's tree
 * collapses to just the tabs their role grants. Operators keep their minimal
 * list.
 */
export function navForRole(role: 'Admin' | 'Supervisor' | 'Operator' | null): NavNode[] {
  if (role === 'Operator') return OPERATOR;
  return ADMIN;
}

/**
 * Keep only the top-level links/groups whose `tab` the backend lists as
 * accessible — mirrors the web sidebar's `showTab` (AppLayout.jsx). A node with
 * no `tab` is always kept; a null/absent `tabs` list falls back to show-all
 * (older server that doesn't send accessible_tabs).
 */
export function filterByTabs(nodes: NavNode[], tabs: string[] | null): NavNode[] {
  if (!tabs) return nodes;
  return nodes.filter((n) => !n.tab || tabs.includes(n.tab));
}

/** Flatten to navigable leaves (has route, not disabled) for active-state calc. */
export function leavesOf(nodes: NavNode[]): NavNode[] {
  const out: NavNode[] = [];
  const walk = (ns: NavNode[]) => {
    for (const n of ns) {
      if (n.children) walk(n.children);
      else if (n.route && !n.disabled) out.push(n);
    }
  };
  walk(nodes);
  return out;
}

/** First navigable descendant route (for tapping a collapsed group icon). */
export function firstRoute(node: NavNode): string | null {
  if (node.route && !node.disabled) return node.route;
  for (const c of node.children ?? []) {
    const r = firstRoute(c);
    if (r) return r;
  }
  return null;
}

/** Longest-prefix-wins active match across all leaves. */
export function activeMatch(pathname: string, nodes: NavNode[]): string | null {
  let best: string | null = null;
  for (const leaf of leavesOf(nodes)) {
    const m = leaf.match ?? toMatch(leaf.route as string);
    const hit = m === '/' ? pathname === '/' : pathname === m || pathname.startsWith(m + '/');
    if (hit && (best == null || m.length > best.length)) best = m;
  }
  return best;
}

export interface NavSearchHit {
  key: string;
  label: string;
  route: string;
  trail: string[];
}

/** Flatten to navigable leaves with their group trail, for the sidebar search. */
export function searchHits(nodes: NavNode[]): NavSearchHit[] {
  const out: NavSearchHit[] = [];
  const walk = (ns: NavNode[], trail: string[]) => {
    for (const n of ns) {
      if (n.children) walk(n.children, [...trail, n.label]);
      else if (n.route && !n.disabled) out.push({ key: n.key, label: n.label, route: n.route, trail });
    }
  };
  walk(nodes, []);
  return out;
}

/** Does this node's subtree contain the active leaf? */
export function containsActive(node: NavNode, am: string | null): boolean {
  if (!am) return false;
  if (node.route && !node.disabled && (node.match ?? toMatch(node.route)) === am) return true;
  return (node.children ?? []).some((c) => containsActive(c, am));
}
