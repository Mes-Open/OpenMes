import { createCollection } from '@tanstack/react-db';
import { echo } from './echo';

/**
 * A read-only TanStack DB collection synced over Reverb. Used directly by
 * ResourceTable, the shared LiveShapesProvider, and the useSyncedShape hooks.
 *
 *   1. fetch the snapshot from /api/collections/{name}; the response also names
 *      the channel to subscribe to (the server decides tenant-vs-global scoping),
 *   2. subscribe to that channel and apply live deltas — "upsert" / "delete",
 *   3. on subscription-confirmed (and every reconnect/resubscribe) re-fetch the
 *      snapshot, so any write that landed before we were subscribed is caught.
 *
 * Tracks its own key set so the server can send a plain "upsert" without knowing
 * whether the client already holds the row. Read-only: writes go through Laravel.
 */
export function realtimeCollection(name, getKey = (row) => row.id) {
    return createCollection({
        id: name,
        getKey,
        gcTime: 1000,
        sync: {
            sync: ({ begin, write, commit, markReady }) => {
                let alive = true;
                let channelName = null;
                const keys = new Set();

                const subscribe = (ch) => {
                    channelName = ch;
                    const channel = echo.private(ch);
                    channel.listen('.changed', (e) => {
                        if (!alive || !e?.row) return;
                        const key = getKey(e.row);
                        begin();
                        if (e.op === 'delete') {
                            if (keys.has(key)) {
                                write({ type: 'delete', value: e.row });
                                keys.delete(key);
                            }
                        } else {
                            write({ type: keys.has(key) ? 'update' : 'insert', value: e.row });
                            keys.add(key);
                        }
                        commit();
                    });
                    // Re-fetch once subscribed (and on every reconnect) so writes
                    // that landed before subscription aren't lost.
                    channel.subscribed(() => { if (alive) load(); });
                };

                const apply = (rows) => {
                    const fresh = new Set();
                    begin();
                    for (const row of rows) {
                        const key = getKey(row);
                        write({ type: keys.has(key) ? 'update' : 'insert', value: row });
                        fresh.add(key);
                    }
                    for (const key of keys) {
                        if (!fresh.has(key)) write({ type: 'delete', value: { id: key } });
                    }
                    commit();
                    keys.clear();
                    for (const k of fresh) keys.add(k);
                };

                const load = async () => {
                    try {
                        const res = await fetch(`/api/collections/${name}`, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!res.ok) throw new Error(`snapshot ${name}: HTTP ${res.status}`);
                        const { rows, channel } = await res.json();
                        if (!alive) return;
                        if (!channelName && channel) subscribe(channel);
                        apply(rows);
                    } catch {
                        // leave whatever we have; don't hang the UI
                    } finally {
                        if (alive) markReady();
                    }
                };

                load();

                return () => {
                    alive = false;
                    if (channelName) echo.leave(channelName);
                };
            },
        },
    });
}
