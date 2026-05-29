import { useMemo } from 'react';
import { useLiveQuery } from '@tanstack/react-db';
import { useShapeConfigs } from '../lib/useShapeConfigs';
import { electricCollection } from '../lib/electricCollection';

/**
 * Live alert count for the sidebar badge — the Electric-backed replacement for
 * the server-computed `nav.alertCount` shared prop (which only refreshed on
 * navigation). Mirrors AlertController::totalCount:
 *   open issues (OPEN/ACKNOWLEDGED) + overdue work orders + blocked work orders.
 *
 * Render-prop so the hook rules hold: while the shapes are still connecting it
 * yields the server `fallback`, then swaps to the live count.
 *
 *   <LiveAlertCount fallback={nav.alertCount}>{(n) => <Badge n={n} />}</LiveAlertCount>
 *
 * Shapes: reuses the SAME shapes the admin dashboard subscribes to
 * (`issues_open` = OPEN+ACKNOWLEDGED, `work_orders_active` = non-terminal), so
 * on the dashboard no extra streams are opened just for the badge. Both fully
 * cover AlertController::totalCount (open issues + overdue + blocked).
 */
const SHAPES = ['issues_open', 'work_orders_active'];
const OPEN_STATUSES = ['OPEN', 'ACKNOWLEDGED'];
const TERMINAL_STATUSES = ['DONE', 'REJECTED', 'CANCELLED'];

export default function LiveAlertCount({ fallback = 0, children }) {
    const { configs } = useShapeConfigs(SHAPES);
    if (!configs) return children(fallback);
    return <Live configs={configs} fallback={fallback}>{children}</Live>;
}

function Live({ configs, fallback, children }) {
    const issuesC = useMemo(() => electricCollection('issues_open', configs.issues_open, (r) => r.id), [configs]);
    const ordersC = useMemo(() => electricCollection('work_orders_active', configs.work_orders_active, (r) => r.id), [configs]);

    const { data: issues = [], isLoading: il } = useLiveQuery((q) => q.from({ r: issuesC }));
    const { data: orders = [], isLoading: ol } = useLiveQuery((q) => q.from({ r: ordersC }));

    const count = useMemo(() => {
        const todayStr = new Date().toISOString().slice(0, 10);
        const openIssues = issues.filter((i) => OPEN_STATUSES.includes(i.status)).length;
        const overdue = orders.filter(
            (o) => o.due_date && String(o.due_date).slice(0, 10) < todayStr && !TERMINAL_STATUSES.includes(o.status),
        ).length;
        const blocked = orders.filter((o) => o.status === 'BLOCKED').length;
        return openIssues + overdue + blocked;
    }, [issues, orders]);

    // Until the first sync settles, keep showing the server fallback so the
    // badge never flashes 0.
    return children(il || ol ? fallback : count);
}
