import { __, formatDate, formatNumber, formatTime } from '../../lib/i18n';

// Shared vocabulary of the operator shell (design ref: OpenMES Operator.dc.html):
// how a work order's status reads, which group of the list it sits in, which
// filter pill shows it, and how its due date and priority are worded.

export function fmtQty(v) {
    const n = parseFloat(v);
    return formatNumber(Number.isNaN(n) ? 0 : n, { maximumFractionDigits: 2 });
}

/** Status → word + colour classes (`text` for the label/dot, `pill` for a filled chip). */
const STATUS = {
    IN_PROGRESS: { label: 'In progress', text: 'text-om-running', dot: 'bg-om-running', pill: 'bg-om-running-bg text-om-running' },
    PENDING: { label: 'Pending', text: 'text-om-muted', dot: 'bg-om-faintest', pill: 'bg-om-chip text-om-muted' },
    ACCEPTED: { label: 'Accepted', text: 'text-om-accepted', dot: 'bg-om-accepted', pill: 'bg-om-accepted-bg text-om-accepted' },
    PAUSED: { label: 'Paused', text: 'text-om-maint', dot: 'bg-om-maint', pill: 'bg-om-maint-bg text-om-maint' },
    CHANGE_HOLD: { label: 'On Hold', text: 'text-om-maint', dot: 'bg-om-maint', pill: 'bg-om-maint-bg text-om-maint' },
    ON_HOLD: { label: 'On Hold', text: 'text-om-maint', dot: 'bg-om-maint', pill: 'bg-om-maint-bg text-om-maint' },
    BLOCKED: { label: 'Blocked', text: 'text-om-blocked', dot: 'bg-om-blocked', pill: 'bg-om-blocked-bg text-om-blocked' },
    DONE: { label: 'Done', text: 'text-om-muted', dot: 'bg-om-done', pill: 'bg-om-chip text-om-muted' },
    CANCELLED: { label: 'Cancelled', text: 'text-om-faint', dot: 'bg-om-faintest', pill: 'bg-om-chip text-om-faint' },
    REJECTED: { label: 'Rejected', text: 'text-om-rejected', dot: 'bg-om-rejected', pill: 'bg-om-rejected-bg text-om-rejected' },
};

export function statusMeta(status) {
    const m = STATUS[status] ?? STATUS.PENDING;
    return { ...m, label: __(m.label) };
}

/** The list's groups, in the order the operator works them. */
const GROUPS = [
    { key: 'running', label: 'Running', statuses: ['IN_PROGRESS'], dot: 'bg-om-running' },
    { key: 'blocked', label: 'Blocked', statuses: ['BLOCKED'], dot: 'bg-om-blocked' },
    { key: 'paused', label: 'Paused', statuses: ['PAUSED', 'CHANGE_HOLD', 'ON_HOLD'], dot: 'bg-om-maint' },
    { key: 'next', label: 'Up next', statuses: ['PENDING', 'ACCEPTED'], dot: 'bg-om-faintest' },
    { key: 'done', label: 'Done', statuses: ['DONE'], dot: 'bg-om-done' },
];

export function groupOrders(orders) {
    const known = GROUPS.flatMap((g) => g.statuses);
    const groups = GROUPS.map((g) => ({ ...g, label: __(g.label), rows: orders.filter((o) => g.statuses.includes(o.status)) }));
    const rest = orders.filter((o) => !known.includes(o.status));
    if (rest.length) groups.push({ key: 'other', label: __('Other'), statuses: [], dot: 'bg-om-faintest', rows: rest });
    return groups.filter((g) => g.rows.length > 0);
}

/** The filter pills above the list. */
export const FILTERS = [
    { key: 'active', label: 'Active', statuses: ['IN_PROGRESS', 'PAUSED', 'BLOCKED', 'CHANGE_HOLD', 'ON_HOLD'] },
    { key: 'pending', label: 'Pending', statuses: ['PENDING', 'ACCEPTED'] },
    { key: 'done', label: 'Done', statuses: ['DONE'] },
    { key: 'all', label: 'All', statuses: null },
];

export function applyFilter(orders, filterKey, query, textOf) {
    const f = FILTERS.find((x) => x.key === filterKey) ?? FILTERS[3];
    const q = (query ?? '').trim().toLowerCase();
    return orders
        .filter((o) => !f.statuses || f.statuses.includes(o.status))
        .filter((o) => !q || textOf(o).toLowerCase().includes(q));
}

/** Text a search box matches an order against. */
export function orderSearchText(wo) {
    return [wo.order_no, wo.product_type?.name, wo.description, wo.line_status?.name, JSON.stringify(wo.extra_data ?? '')]
        .filter(Boolean)
        .join(' ');
}

const SETTLED = ['DONE', 'CANCELLED', 'REJECTED'];

/**
 * Due date as the operator reads it: a time when it is due today (or
 * "Today" when no time was set), the date otherwise. `hot` marks today and
 * overdue — the orders the list paints red.
 */
export function dueInfo(due, status) {
    if (!due) return { label: '—', hot: false };
    const d = new Date(due);
    if (Number.isNaN(d.getTime())) return { label: '—', hot: false };
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    const settled = SETTLED.includes(status);
    const hasTime = d.getHours() !== 0 || d.getMinutes() !== 0;
    const label = sameDay
        ? (hasTime ? formatTime(d, { hour: '2-digit', minute: '2-digit' }) : __('Today'))
        : formatDate(d, { day: '2-digit', month: 'short' });
    return { label, hot: !settled && (sameDay || d < now) };
}

/** Priority on the OpenMES 1–5 scale as a short tag (0 / empty reads as none). */
export function priorityTag(p) {
    const n = Number(p) || 0;
    if (n >= 5) return { label: __('URGENT'), cls: 'text-om-blocked border-om-blocked/40' };
    if (n === 4) return { label: __('HIGH'), cls: 'text-om-accent border-om-accent/40' };
    if (n === 3) return { label: __('MED'), cls: 'text-om-downtime border-om-downtime/40' };
    if (n >= 1) return { label: __('LOW'), cls: 'text-om-faint border-om-faint/40' };
    return null;
}

/** The batch being worked: the first unfinished one, else the last. */
export function currentBatch(wo) {
    const batches = wo.batches ?? [];
    return batches.find((b) => !['DONE', 'CANCELLED'].includes(b.status)) ?? batches[batches.length - 1] ?? null;
}

export function batchLabel(batch) {
    if (!batch) return __('No batch yet');
    return [batch.lot_number ? `LOT ${batch.lot_number}` : null, `B#${batch.batch_number}`].filter(Boolean).join(' · ');
}

/** Planned / produced / remaining / percent of an order. */
export function progressOf(wo) {
    const planned = parseFloat(wo.planned_qty) || 0;
    const produced = parseFloat(wo.produced_qty) || 0;
    const remaining = Math.max(0, planned - produced);
    const pct = planned > 0 ? Math.min(100, (produced / planned) * 100) : 0;
    return { planned, produced, remaining, pct };
}

/** Initials for the avatar: first letters of the first two words of a name. */
export function initialsOf(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase();
}

/** A pill in the shell's segmented controls (views, filters, theme). */
export function pillCls(active) {
    return `px-3.5 py-1.5 rounded-[7px] text-[12.5px] font-medium cursor-pointer transition-colors whitespace-nowrap ${
        active ? 'bg-om-ink text-om-on-ink' : 'text-om-muted hover:text-om-ink'
    }`;
}

export const monoLabelCls = 'font-mono text-[9px] uppercase tracking-[0.1em] text-om-faint';
