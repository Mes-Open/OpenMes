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

// ── Finding the drawer: edge handle, auto-open, badge → insight, shortcut ────

const SEVERITY_RANK = { bad: 0, warn: 1, info: 2 };
const bySeverity = (a, b) => (SEVERITY_RANK[a.severity] ?? 3) - (SEVERITY_RANK[b.severity] ?? 3)
    || (a.type === 'short_shift' ? -1 : 0) - (b.type === 'short_shift' ? -1 : 0);

/** The handle's colour: 'bad' when something needs action, 'warn' when only to watch, else 'neutral'. */
export function handleTone(drawer) {
    const insights = drawer?.insights ?? [];
    if (insights.some((i) => i.severity === 'bad')) return 'bad';
    if (insights.length > 0) return 'warn';
    return 'neutral';
}

/** The most pressing open insight about one line × shift cell, or null. */
export function insightForCell(drawer, lineId, date, shift) {
    const key = cellKey(lineId, date, shift);
    const hits = (drawer?.insights ?? []).filter((i) => (i.cells ?? []).some((c) => cellKey(c.line_id, c.date, c.shift) === key));
    return hits.sort(bySeverity)[0] ?? null;
}

/** The most pressing open insight that lists a work order among its affected orders, or null. */
export function insightForOrder(drawer, orderId) {
    const hits = (drawer?.insights ?? []).filter((i) => (i.affected ?? []).some((a) => a.work_order_id === orderId));
    return hits.sort(bySeverity)[0] ?? null;
}

/**
 * Drawer state for "open on this insight": every filter (so the insight is
 * not hidden by a chip), the insight expanded and its cells highlighted.
 * Without an insight the drawer simply opens as it was.
 */
export function openOn(state, insight) {
    if (!insight) return { ...state, open: true };
    return { ...state, open: true, filter: 'all', openKey: insight.key, highlight: highlightOf(insight), focusKey: insight.key };
}

// Preferences — storage may be missing or throw (private mode, quota, policy).
const prefKey = (userId) => `openmes.planner.insights.autoOpen.${userId ?? 'guest'}`;
const sessionKey = (userId) => `openmes.planner.insights.autoOpened.${userId ?? 'guest'}`;

/** Whether the drawer may open by itself for this user (default yes). */
export function readAutoOpen(storage, userId) {
    try { return storage?.getItem(prefKey(userId)) !== 'off'; } catch { return true; }
}

/** Remember the user's "Don't open automatically" choice. */
export function writeAutoOpen(storage, userId, enabled) {
    try {
        if (enabled) storage?.removeItem(prefKey(userId)); else storage?.setItem(prefKey(userId), 'off');
    } catch { /* storage unavailable — the choice lasts for this page only */ }
}

/** Whether this browser session already had its first planner visit. */
export function sessionSeen(storage, userId) {
    try { return storage?.getItem(sessionKey(userId)) === '1'; } catch { return false; }
}

export function markSessionSeen(storage, userId) {
    try { storage?.setItem(sessionKey(userId), '1'); } catch { /* ignore */ }
}

/**
 * Open the drawer by itself? Only on the session's first planner visit, once
 * the insights have arrived, when something needs action, the user has not
 * turned it off and the drawer is not open already.
 */
export function shouldAutoOpen({ drawer, enabled, seen, open }) {
    if (!drawer || !enabled || seen || open) return false;
    return (drawer.summary?.needs_action ?? (drawer.insights ?? []).filter((i) => i.severity === 'bad').length) > 0;
}

/** Is the keyboard focus in something the user types into? */
export function isTypingTarget(el) {
    if (!el) return false;
    const tag = (el.tagName || '').toLowerCase();
    if (tag === 'input') return !['checkbox', 'radio', 'button', 'submit', 'reset'].includes((el.type || '').toLowerCase());
    if (tag === 'textarea' || tag === 'select') return true;
    return el.isContentEditable === true;
}

/** Does this keydown toggle the drawer? Plain `I`, not typing, no modal open. */
export function isToggleShortcut(event, { modalOpen = false } = {}) {
    if (!event || event.defaultPrevented || event.repeat) return false;
    if (event.ctrlKey || event.metaKey || event.altKey) return false;
    if (event.key !== 'i' && event.key !== 'I') return false;
    if (modalOpen) return false;
    return !isTypingTarget(event.target);
}

/** Does this keydown close the drawer? Esc while it is open — never over a dialog, which Esc closes first. */
export function isCloseShortcut(event, { open = false, modalOpen = false } = {}) {
    if (!event || event.defaultPrevented || event.repeat) return false;
    if (event.key !== 'Escape' && event.key !== 'Esc') return false;
    return open && !modalOpen;
}

/**
 * Whether a line × shift badge opens the drawer: only one some open insight
 * is about. A badge with exactly enough people — or a shortfall that raised
 * nothing, like a shift that is over — only shows its tooltip.
 */
export const badgeOpens = (drawer, lineId, date, shift) => insightForCell(drawer, lineId, date, shift) !== null;

const dayMonth = (date) => `${date.slice(8, 10)}.${date.slice(5, 7)}`;

/**
 * The drawer header's range, as a translation key and its values: "Week
 * 07.12 – 13.12" for the weekly view, "Day 24.11" for the daily one. Null
 * until the drawer has loaded.
 */
export function rangeCaption(drawer, viewMode) {
    const range = drawer?.range;
    if (!range?.start) return null;
    if (viewMode === 'daily' || !range.end || range.start === range.end) {
        return { key: 'Day :date', params: { date: dayMonth(range.start) } };
    }
    return { key: 'Week :from – :to', params: { from: dayMonth(range.start), to: dayMonth(range.end) } };
}

/**
 * Why the drawer has nothing open for a filter, as a translation key — or
 * null when something is open. Resolved entries may still be listed below.
 */
export function emptyReason(drawer, filter = 'all', viewMode = 'weekly') {
    if (!drawer) return null;
    const open = (drawer.insights ?? []).filter((i) => filter === 'all' || i.category === filter);
    if (open.length > 0) return null;
    if (filter === 'skills' || filter === 'plan') return 'Nothing here.';
    return viewMode === 'daily'
        ? 'Nothing needs attention on this day: every shift has the people its work needs.'
        : 'Nothing needs attention in this week: every shift has the people its work needs.';
}
