// Run a cheap check when the user comes back to a tab — the window regains
// focus or the page becomes visible again — e.g. whether a what-if schedule
// went stale while the planner was in another tab. Focus and visibility often
// fire together, so calls closer than `minGapMs` collapse into one.

/**
 * @param {() => void} check
 * @param {{ win?: EventTarget, doc?: EventTarget & { visibilityState?: string }, minGapMs?: number, now?: () => number }} [options]
 * @returns {() => void} removes the listeners
 */
export function refreshOnReturn(check, { win = globalThis.window, doc = globalThis.document, minGapMs = 2000, now = () => Date.now() } = {}) {
    let last = -Infinity;
    const run = () => {
        if (doc?.visibilityState === 'hidden') return;
        const t = now();
        if (t - last < minGapMs) return;
        last = t;
        check();
    };
    win?.addEventListener?.('focus', run);
    doc?.addEventListener?.('visibilitychange', run);
    return () => {
        win?.removeEventListener?.('focus', run);
        doc?.removeEventListener?.('visibilitychange', run);
    };
}
