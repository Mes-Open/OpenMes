import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { echo } from '../lib/echo';

/** Private, line-authorized nudges; production data comes from the page's controller. */
export default function LineSync({ lineId, reloadOnly = [] }) {
    const only = useRef(reloadOnly);
    only.current = reloadOnly;

    useEffect(() => {
        if (!lineId) return;
        let timer;
        let visiting = false;
        let pending = false;
        const refresh = () => {
            clearTimeout(timer);
            pending = true;
            timer = setTimeout(() => {
                if (visiting || document.hidden) return;
                pending = false;
                router.reload({
                    preserveState: true,
                    preserveScroll: true,
                    ...(only.current.length ? { only: only.current } : {}),
                });
            }, 150);
        };
        // A nudge must not cancel the operator's in-flight write.
        const offStart = router.on('start', () => { visiting = true; });
        const offFinish = router.on('finish', () => {
            visiting = false;
            if (pending) refresh();
        });
        const channelName = `operator-line.${lineId}`;
        echo.private(channelName).listen('.changed', refresh).subscribed(refresh);
        // Recover missed changes after a network outage or returning to a hidden tab.
        const poll = setInterval(refresh, 30000);
        const onVisible = () => { if (!document.hidden) refresh(); };
        document.addEventListener('visibilitychange', onVisible);
        return () => {
            clearTimeout(timer);
            clearInterval(poll);
            offStart();
            offFinish();
            document.removeEventListener('visibilitychange', onVisible);
            echo.leave(channelName);
        };
    }, [lineId]);

    return null;
}
