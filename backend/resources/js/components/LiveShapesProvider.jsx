import { createContext, useContext, useMemo } from 'react';
import { useShapeConfigs } from '../lib/useShapeConfigs';
import { electricCollection } from '../lib/electricCollection';
import { useLiveShapeBudget } from '../lib/liveShapeBudget';

/**
 * App-wide live shapes, subscribed ONCE at the layout level and shared via
 * context. `work_orders_active` and `issues_open` are needed almost everywhere
 * (the alert badge on every page, plus the dashboard, planner, etc.). Without
 * sharing, each consumer opened its own Electric stream — e.g. the badge AND the
 * dashboard each subscribing, doubling held connections toward the browser's
 * ~6-per-origin HTTP/1.1 limit. Mounted once here, every consumer reads the same
 * collection, so the whole admin app holds just these two live connections.
 *
 * We expose the COLLECTIONS (stable refs), not their data — so pages that don't
 * read them don't re-render when work orders/issues change. Consumers run their
 * own useLiveQuery against the shared collection.
 */
const HOT_SHAPES = ['work_orders_active', 'issues_open'];
const LiveShapesContext = createContext(null);

/** Returns `{ workOrdersActive, issuesOpen }` collections, or null until ready. */
export function useHotShapes() {
    return useContext(LiveShapesContext);
}

export function LiveShapesProvider({ children }) {
    const { configs } = useShapeConfigs(HOT_SHAPES);
    useLiveShapeBudget(HOT_SHAPES);

    // IMPORTANT: render ONE stable tree and only flip the context VALUE from null
    // → collections. A structural branch here (loading wrapper vs ready wrapper)
    // would reparent `children`, remounting the whole page when configs arrive —
    // which double-fires every page's fetches/streams and cancels the first set.
    const value = useMemo(() => {
        if (!configs) return null;
        return {
            workOrdersActive: electricCollection('work_orders_active', configs.work_orders_active, (r) => r.id),
            issuesOpen: electricCollection('issues_open', configs.issues_open, (r) => r.id),
        };
    }, [configs]);

    return <LiveShapesContext.Provider value={value}>{children}</LiveShapesContext.Provider>;
}
