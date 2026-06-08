import { useLiveQuery } from '@tanstack/react-db';
import { useSyncedShape } from './useSyncedShape';

/**
 * Data for the admin dashboard. All collections ride the single Reverb
 * WebSocket:
 *
 *  - `work_orders_active`, `issues_open` — the SHARED live collections from
 *    LiveShapesProvider (the alert badge already holds them).
 *  - `lines_active`, `issue_types`, `oee_records_recent` — the dashboard's own
 *    lookups.
 *
 * `hot` (the shared collections) must be ready before this runs.
 */
export function useDashboardShapes(hot) {
    const { data: workOrders = [], isLoading: wl } = useLiveQuery((q) => q.from({ r: hot.workOrdersActive }));
    const { data: issues = [], isLoading: il } = useLiveQuery((q) => q.from({ r: hot.issuesOpen }));

    const lines = useSyncedShape('lines_active');
    const issueTypes = useSyncedShape('issue_types');
    const oeeRecords = useSyncedShape('oee_records_recent');

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
