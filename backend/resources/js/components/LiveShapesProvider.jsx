import { createContext, useContext, useMemo } from 'react';
import { realtimeCollection } from '../lib/realtimeCollection';

/**
 * App-wide live collections, subscribed ONCE at the layout level and shared via
 * context. `work_orders_active` and `issues_open` are needed almost everywhere
 * (the alert badge on every page, plus the dashboard, planner, etc.). They all
 * ride the single Reverb WebSocket, but sharing the collections still avoids
 * each consumer fetching its own snapshot and running a duplicate subscription.
 *
 * We expose the COLLECTIONS (stable refs), not their data — so pages that don't
 * read them don't re-render when work orders/issues change. Consumers run their
 * own useLiveQuery against the shared collection.
 */
const LiveShapesContext = createContext(null);

/** Returns `{ workOrdersActive, issuesOpen }` collections. */
export function useHotShapes() {
    return useContext(LiveShapesContext);
}

export function LiveShapesProvider({ children }) {
    const value = useMemo(
        () => ({
            workOrdersActive: realtimeCollection('work_orders_active', (r) => r.id),
            issuesOpen: realtimeCollection('issues_open', (r) => r.id),
        }),
        [],
    );

    return <LiveShapesContext.Provider value={value}>{children}</LiveShapesContext.Provider>;
}
