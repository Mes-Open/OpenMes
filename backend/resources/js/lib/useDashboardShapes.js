import { useShape } from '@electric-sql/react';

/**
 * Subscribe to every shape the admin dashboard needs, given pre-fetched signed
 * configs from the gatekeeper (see useShapeConfigs). Each config is
 * { url, params } where params carries the HMAC-signed table/columns/where —
 * the browser streams these from Electric through Caddy, never through PHP.
 *
 * Must be rendered only once `configs` is non-null so these hooks are called
 * unconditionally (rules of hooks).
 */
export const DASHBOARD_SHAPES = [
    'work_orders_active',
    'lines_active',
    'issues_open',
    'issue_types',
    'oee_records_recent',
];

export function useDashboardShapes(configs) {
    const workOrders = useShape(configs.work_orders_active);
    const lines = useShape(configs.lines_active);
    const issues = useShape(configs.issues_open);
    const issueTypes = useShape(configs.issue_types);
    const oeeRecords = useShape(configs.oee_records_recent);

    return {
        workOrders: workOrders.data ?? [],
        lines: lines.data ?? [],
        issues: issues.data ?? [],
        issueTypes: issueTypes.data ?? [],
        oeeRecords: oeeRecords.data ?? [],
        isLoading:
            workOrders.isLoading ||
            lines.isLoading ||
            issues.isLoading ||
            issueTypes.isLoading ||
            oeeRecords.isLoading,
        error:
            workOrders.error ||
            lines.error ||
            issues.error ||
            issueTypes.error ||
            oeeRecords.error,
    };
}
