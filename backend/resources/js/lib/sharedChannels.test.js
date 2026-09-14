import { describe, expect, it, vi } from 'vitest';
import { createSharedChannels } from './sharedChannels';

/**
 * A minimal stand-in for laravel-echo's Pusher connector: `private()` returns
 * one shared channel object per name (as the real one does), and `leave()`
 * unsubscribes it for everyone holding it.
 */
function fakeEcho() {
    const channels = new Map();
    const echo = {
        private: (name) => {
            if (!channels.has(name)) {
                const handlers = new Map();
                const bind = (event, cb) => {
                    if (!handlers.has(event)) handlers.set(event, new Set());
                    handlers.get(event).add(cb);
                };
                const unbind = (event, cb) => handlers.get(event)?.delete(cb);
                channels.set(name, {
                    subscribed: true,
                    listen: (event, cb) => bind(event, cb),
                    stopListening: (event, cb) => unbind(event, cb),
                    on: (event, cb) => bind(event, cb),
                    subscription: { unbind },
                    emit: (event, payload) => handlers.get(event)?.forEach((cb) => cb(payload)),
                    count: (event) => handlers.get(event)?.size ?? 0,
                });
            }
            return channels.get(name);
        },
        leave: vi.fn((name) => {
            const ch = channels.get(name);
            if (ch) ch.subscribed = false;
            channels.delete(name);
        }),
    };
    return { echo, channels };
}

describe('createSharedChannels', () => {
    it('keeps the channel for a remounted list when the old one cleans up — the stale work-order list', () => {
        const { echo } = fakeEcho();
        const shared = createSharedChannels(echo);
        const oldList = vi.fn();
        const newList = vi.fn();

        const releaseOld = shared.join('col.g.work_orders_all', '.changed', oldList, () => {});
        const releaseNew = shared.join('col.g.work_orders_all', '.changed', newList, () => {});
        const channel = echo.private('col.g.work_orders_all');

        // The old collection's gcTime cleanup runs after the new one mounted.
        releaseOld();

        expect(echo.leave).not.toHaveBeenCalled();
        expect(channel.subscribed).toBe(true);

        channel.emit('.changed', { op: 'upsert', row: { id: 1 } });
        expect(newList).toHaveBeenCalledTimes(1);
        expect(oldList).not.toHaveBeenCalled();

        releaseNew();
        expect(echo.leave).toHaveBeenCalledWith('col.g.work_orders_all');
    });

    it('detaches only the releasing user\'s handlers', () => {
        const { echo } = fakeEcho();
        const shared = createSharedChannels(echo);
        const onSubA = vi.fn();
        const onSubB = vi.fn();

        const releaseA = shared.join('col.g.issues_open', '.changed', () => {}, onSubA);
        shared.join('col.g.issues_open', '.changed', () => {}, onSubB);
        const channel = echo.private('col.g.issues_open');

        releaseA();
        expect(channel.count('.changed')).toBe(1);

        channel.emit('pusher:subscription_succeeded');
        expect(onSubA).not.toHaveBeenCalled();
        expect(onSubB).toHaveBeenCalledTimes(1);
    });

    it('ignores a second release so one user cannot drop another\'s count', () => {
        const { echo } = fakeEcho();
        const shared = createSharedChannels(echo);

        const release = shared.join('col.g.lines_all', '.changed', () => {}, () => {});
        shared.join('col.g.lines_all', '.changed', () => {}, () => {});

        release();
        release();

        expect(shared.userCount('col.g.lines_all')).toBe(1);
        expect(echo.leave).not.toHaveBeenCalled();
    });

    it('rejoins cleanly after the last user left', () => {
        const { echo } = fakeEcho();
        const shared = createSharedChannels(echo);
        const listener = vi.fn();

        shared.join('col.g.skills', '.changed', () => {}, () => {})();
        expect(echo.leave).toHaveBeenCalledTimes(1);

        shared.join('col.g.skills', '.changed', listener, () => {});
        echo.private('col.g.skills').emit('.changed', { op: 'upsert', row: { id: 2 } });

        expect(listener).toHaveBeenCalledTimes(1);
        expect(shared.userCount('col.g.skills')).toBe(1);
    });
});
