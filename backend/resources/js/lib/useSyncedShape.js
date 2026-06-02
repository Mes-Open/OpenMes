import { useMemo } from 'react';
import { useLiveQuery } from '@tanstack/react-db';
import { electricCollection } from './electricCollection';
import { usePolledShape } from './usePolledShape';
import { MULTIPLEXED } from './transport';
import { useLiveShapeBudget } from './liveShapeBudget';

/**
 * Shape-consumption hooks that respect the connection budget (see CLAUDE.md →
 * "Electric connection budget"). Both are built on electricCollection, so they
 * re-mint expired signatures automatically.
 *
 *   useLiveShape(name, config)   — ALWAYS live. For instant-critical data.
 *   useSyncedShape(name, config) — ADAPTIVE: live on HTTP/2 (no limit), polls on
 *                                  HTTP/1.1 (no held connection). For data that
 *                                  should be fresh but isn't instant-critical.
 *
 * Both return { data, isLoading } — drop-in for each other and for useShape.
 */

function useLiveCollection(name, config) {
    const collection = useMemo(() => electricCollection(name, config), [name, config]);
    const { data = [], isLoading } = useLiveQuery((q) => q.from({ r: collection }));
    return { data, isLoading };
}

/** Always-live Electric shape. Counts toward the HTTP/1.1 connection budget. */
export function useLiveShape(name, config) {
    useLiveShapeBudget([name]);
    return useLiveCollection(name, config);
}

/**
 * Adaptive: live when the transport multiplexes (HTTP/2+), polled otherwise.
 * MULTIPLEXED is a frozen per-session constant, so a given component takes the
 * same branch for its whole lifetime — hook order stays stable across renders.
 */
export function useSyncedShape(name, config, intervalMs = 5000) {
    // eslint-disable-next-line react-hooks/rules-of-hooks
    return MULTIPLEXED ? useLiveCollection(name, config) : usePolledShape(name, config, intervalMs);
}
