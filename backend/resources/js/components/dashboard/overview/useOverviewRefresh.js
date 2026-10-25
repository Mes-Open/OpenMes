import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Whether an Inertia visit is this hook's own background reload: `reload()`
 * marks its visits `async`, and ours asks for the `overview` prop alone. The
 * `start` / `finish` events carry the visit's params as `detail.visit`.
 */
export const isOverviewReload = (visit) =>
    !!visit?.async && Array.isArray(visit.only) && visit.only.length === 1 && visit.only[0] === 'overview';

/**
 * Keeps the dashboard's `overview` prop fresh: a partial reload every
 * `intervalMs`, only while the browser tab is visible, plus one straight away
 * when the tab comes back after being hidden for longer than that.
 *
 * Returns `liveOk` — false after a refresh failed (server error, expired
 * session, network down), true again after the next one succeeds. Failures are
 * handled quietly (no Inertia error modal); the header greys its "Live" dot.
 */
export default function useOverviewRefresh(intervalMs = 60_000) {
    const [liveOk, setLiveOk] = useState(true);

    useEffect(() => {
        let visiting = 0; // user navigations / writes in flight
        let inFlight = false; // our own reload is in flight
        let lastAttempt = Date.now();

        const fail = () => {
            setLiveOk(false);
            return false; // suppress Inertia's error modal for a background poll
        };

        const refresh = () => {
            if (document.hidden || visiting || inFlight) return;
            inFlight = true;
            lastAttempt = Date.now();
            router.reload({
                only: ['overview'],
                onSuccess: () => setLiveOk(true),
                onHttpException: fail,
                onNetworkError: fail,
                onFinish: () => { inFlight = false; },
            });
        };

        // A background refresh must never cancel the user's own visit, so none
        // is started while one is in flight. Every visit that isn't our reload
        // counts — including one the user starts while our reload is running —
        // and our reload's own start/finish never touches the count. A counter,
        // not a flag: a visit cancelled by the next one fires its `finish`
        // after the next one's `start`.
        const offStart = router.on('start', (e) => { if (!isOverviewReload(e.detail.visit)) visiting += 1; });
        const offFinish = router.on('finish', (e) => {
            if (!isOverviewReload(e.detail.visit)) visiting = Math.max(0, visiting - 1);
        });

        const timer = setInterval(refresh, intervalMs);
        const onVisible = () => {
            if (!document.hidden && Date.now() - lastAttempt >= intervalMs) refresh();
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            clearInterval(timer);
            offStart();
            offFinish();
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [intervalMs]);

    return liveOk;
}
