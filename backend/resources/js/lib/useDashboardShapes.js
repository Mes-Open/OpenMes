import { useLiveQuery } from '@tanstack/react-db';
import { useSyncedShape } from './useSyncedShape';

/**
 * Data for the admin dashboard. See CLAUDE.md → "Electric connection budget".
 *
 *  - `work_orders_active`, `issues_open` — the SHARED live collections from
 *    LiveShapesProvider (the alert badge already holds them; no extra connection).
 *  - `lines_active`, `issue_types`, `oee_records_recent` — ADAPTIVE: live on
 *    HTTP/2, polled every 5s on HTTP/1.1. So on a plain-HTTP LAN the dashboard
 *    holds just the 2 shared connections; on HTTP/2 it's all live.
 *
 * `hot` (shared collections) and `configs` must both be ready before this runs.
 */
export const DASHBOARD_SHAPES = ['lines_active', 'issue_types', 'oee_records_recent'];

export function useDashboardShapes(configs, hot) {
    const { data: workOrders = [], isLoading: wl } = useLiveQuery((q) => q.from({ r: hot.workOrdersActive }));
    const { data: issues = [], isLoading: il } = useLiveQuery((q) => q.from({ r: hot.issuesOpen }));

    const lines = useSyncedShape('lines_active', configs.lines_active);
    const issueTypes = useSyncedShape('issue_types', configs.issue_types);
    const oeeRecords = useSyncedShape('oee_records_recent', configs.oee_records_recent);

    return {
        workOrders,
        issues,
        lines: lines.data,
        issueTypes: issueTypes.data,
        oeeRecords: oeeRecords.data,
        isLoading: wl || il || lines.isLoading || issueTypes.isLoading || oeeRecords.isLoading,
        error: null,
    };
}
