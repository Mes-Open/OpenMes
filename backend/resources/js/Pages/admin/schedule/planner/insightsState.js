// Pure state helpers for the Production Planner's insights drawer — what the
// drawer shows for a filter, the counts on its tiles and toolbar button, and
// which board cells "Show on plan" highlights. Kept free of React so vitest
// covers them.

export const FILTERS = ['all', 'people', 'skills', 'plan'];

/** "lineId|date|shift" — how the board addresses a line × shift cell. */
export const cellKey = (lineId, date, shift) => `${lineId}|${date}|${shift}`;

/**
 * The drawer's groups for a filter: open insights needing action (bad), open
 * ones to watch (warn/info), and what was resolved today. Empty groups drop out.
 */
export function groupInsights(drawer, filter = 'all') {
    if (!drawer) return [];
    const match = (category) => filter === 'all' || category === filter;
    const open = (drawer.insights ?? []).filter((i) => match(i.category));
    const resolved = (drawer.resolved ?? []).filter((r) => match(r.category));
    return [
        { key: 'action', items: open.filter((i) => i.severity === 'bad') },
        { key: 'watch', items: open.filter((i) => i.severity !== 'bad') },
        { key: 'resolved', items: resolved },
    ].filter((g) => g.items.length > 0);
}

/** How many open insights each filter chip would show. */
export function filterCounts(drawer) {
    const counts = { all: 0, people: 0, skills: 0, plan: 0 };
    (drawer?.insights ?? []).forEach((i) => {
        counts.all += 1;
        if (counts[i.category] != null) counts[i.category] += 1;
    });
    return counts;
}

/** Open insights — the number on the toolbar's Insights button. */
export const openCount = (drawer) => (drawer?.insights ?? []).length;

/** The cells an insight is about, as a Set of cellKey()s. */
export function highlightOf(insight) {
    return new Set((insight?.cells ?? []).map((c) => cellKey(c.line_id, c.date, c.shift)));
}

/** Staffing cells indexed by cellKey(), and the cells short of people. */
export function indexStaffing(staffing) {
    const byKey = {};
    (staffing?.cells ?? []).forEach((c) => { byKey[cellKey(c.line_id, c.date, c.shift)] = c; });
    return byKey;
}

/** Expand one insight, collapse it when it is already open. */
export const toggle = (openKey, key) => (openKey === key ? null : key);

/**
 * Decides which insights responses may update the drawer, so an older answer
 * never overwrites a newer one — e.g. a poll started before "Acknowledge"
 * that returns after it would otherwise resurrect the insight just resolved.
 *
 * Every request takes a ticket: startFetch() for a read, startAction() for a
 * write (whose response carries the board after it). A response is applied
 * only when accept(ticket) says so:
 *  - nothing newer was applied already;
 *  - a read is the latest read, no write was running when it started, and
 *    none started after it or is still running (the write's own response is
 *    the fresher state);
 *  - a write is the latest write.
 * Call endAction(ticket) when a write settles, whatever the outcome.
 */
export function createInsightsGate() {
    let seq = 0;
    let latestFetch = 0;
    let latestAction = 0;
    let applied = 0;
    const running = new Set();
    return {
        startFetch() { seq += 1; latestFetch = seq; return { id: seq, kind: 'fetch', duringAction: running.size > 0 }; },
        startAction() { seq += 1; latestAction = seq; running.add(seq); return { id: seq, kind: 'action' }; },
        endAction(ticket) { running.delete(ticket.id); },
        accept(ticket) {
            if (ticket.id <= applied) return false;
            if (ticket.kind === 'fetch' && (ticket.duringAction || ticket.id !== latestFetch || latestAction > ticket.id || running.size > 0)) return false;
            if (ticket.kind === 'action' && ticket.id !== latestAction) return false;
            applied = ticket.id;
            return true;
        },
    };
}
