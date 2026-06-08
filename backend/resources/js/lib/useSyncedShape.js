import { useMemo } from 'react';
import { useLiveQuery } from '@tanstack/react-db';
import { realtimeCollection } from './realtimeCollection';

/**
 * Collection-consumption hooks. Backed by the single Reverb WebSocket, so
 * everything is live — there's no connection budget and no HTTP/2-vs-1.1
 * adaptivity.
 *
 *   useLiveShape(name)   — live collection.
 *   useSyncedShape(name) — same; kept as a separate name for call-site intent.
 *
 * Both return { data, isLoading }, drop-in for each other.
 */
function useLiveCollection(name) {
    const collection = useMemo(() => realtimeCollection(name), [name]);
    const { data = [], isLoading } = useLiveQuery((q) => q.from({ r: collection }));
    return { data, isLoading };
}

export function useLiveShape(name) {
    return useLiveCollection(name);
}

export function useSyncedShape(name) {
    return useLiveCollection(name);
}
