import { useMemo } from 'react';
import { useLiveQuery } from '@tanstack/react-db';
import { useHotShapes } from './LiveShapesProvider';
import { countAlerts } from '../lib/alerts';
import { usePlantToday } from '../lib/usePlantToday';

/**
 * Live alert count for the sidebar badge — the live-synced replacement for the
 * server-computed `nav.alertCount` shared prop (which only refreshed on
 * navigation). Mirrors AlertController::totalCount:
 *   open issues (OPEN/ACKNOWLEDGED) + overdue work orders + blocked work orders.
 *
 * Reads the SHARED `work_orders_active` / `issues_open` collections from
 * LiveShapesProvider — the same streams the dashboard and other pages use — so
 * the badge never opens its own duplicate connections. "Overdue" is judged
 * against today in the plant timezone (lib/alerts) — the server's day boundary.
 * Render-prop so the hook rules hold: while the shapes are still connecting it
 * yields the server `fallback`, then swaps to the live count.
 *
 *   <LiveAlertCount fallback={nav.alertCount}>{(n) => <Badge n={n} />}</LiveAlertCount>
 */
export default function LiveAlertCount({ fallback = 0, children }) {
    const hot = useHotShapes();
    if (!hot) return children(fallback);
    return <Live hot={hot} fallback={fallback}>{children}</Live>;
}

function Live({ hot, fallback, children }) {
    const { data: issues = [], isLoading: il } = useLiveQuery((q) => q.from({ r: hot.issuesOpen }));
    const { data: orders = [], isLoading: ol } = useLiveQuery((q) => q.from({ r: hot.workOrdersActive }));

    const today = usePlantToday();

    const count = useMemo(() => countAlerts({ issues, orders, today }).total, [issues, orders, today]);

    // Until the first sync settles, keep showing the server fallback so the
    // badge never flashes 0.
    return children(il || ol ? fallback : count);
}
