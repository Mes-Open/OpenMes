import { useEffect } from 'react';
import { MULTIPLEXED } from './transport';

/**
 * Dev-only guardrail for the browser's ~6-concurrent-connections-per-origin
 * limit (HTTP/1.1). Each LIVE Electric shape holds one connection for its
 * long-poll; too many on one screen and live updates stall. Components that
 * open live shapes declare them here; in development we warn if the total
 * across the mounted tree exceeds a safe budget, so an over-subscribing screen
 * is caught before it ships. When a screen warns, move its least-critical
 * shapes to usePolledShape (a 5s poll doesn't hold a connection) — see
 * CLAUDE.md → "Electric connection budget".
 *
 * No-op in production builds — purely a development aid, never touches runtime.
 */
const SAFE_LIVE_BUDGET = 5; // ~6 cap minus 1 slot for navigation/asset requests
const active = [];

function check() {
    const names = active.flat();
    if (names.length > SAFE_LIVE_BUDGET) {
        // eslint-disable-next-line no-console
        console.warn(
            `[OpenMES] ${names.length} live Electric shapes subscribed at once ` +
                `(${names.join(', ')}). HTTP/1.1 allows only ~6 connections per origin. Move the ` +
                `least-critical shapes on this screen to usePolledShape() (a 5s poll holds no ` +
                `connection). See CLAUDE.md → "Electric connection budget".`,
        );
    }
}

/**
 * Declare the live shapes a component subscribes to (by name). Pass a stable
 * array or list the names inline — re-registration is keyed on the names.
 */
export function useLiveShapeBudget(names) {
    const key = names.join(',');
    useEffect(() => {
        // Only matters on HTTP/1.1; HTTP/2 multiplexes so there's no limit to bust.
        if (!import.meta.env?.DEV || MULTIPLEXED) return undefined;
        active.push(names);
        check();
        return () => {
            const i = active.indexOf(names);
            if (i >= 0) active.splice(i, 1);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);
}
