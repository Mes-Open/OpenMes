// Planner design tokens + pure layout/format helpers.
//
// Colours reference the OpenMES brand tokens (the `--om-*` CSS vars in
// resources/css/app.css), which are redefined under `.dark` — so referencing
// them in inline styles means dark mode "just works" with no per-component
// branching. This board follows the "OpenMES Schedule" design: technical
// Geist-Mono labelling, ACCEPTED = blue, maintenance = purple.
import { __, formatNumber } from '../../../../lib/i18n';
import { loadColorVar } from '../../../../lib/load';

// Scheduling maths is shared with the mobile planner via @openmes/ui — one
// implementation, so the same order can't land in different cells on tablet
// and desktop. Re-exported so this stays the planner's single import.
export {
    chainChipMeta,
    dayList,
    fmtKey,
    fmtTime,
    hourlyLanes,
    isoWeek,
    lineLoad,
    minuteOfDay,
    onLine,
    onMonthlyDay,
    parseDate,
    placementsOf,
    projectSegment,
    segmentChain,
    todayKey,
    weeklyPlacements,
    weeklySlot,
} from '@openmes/ui/planner';

export const MONO = 'var(--font-mono)';

// ── Work-order status → brand tokens ────────────────────────────────────────
export const STATUS = {
    PENDING:     { label: 'Pending',     solid: 'var(--om-pending)',  soft: 'var(--om-pending-bg)' },
    ACCEPTED:    { label: 'Accepted',    solid: 'var(--om-accepted)', soft: 'var(--om-accepted-bg)' },
    IN_PROGRESS: { label: 'Running',     solid: 'var(--om-running)',  soft: 'var(--om-running-bg)' },
    BLOCKED:     { label: 'Blocked',     solid: 'var(--om-blocked)',  soft: 'var(--om-blocked-bg)' },
    PAUSED:      { label: 'Paused',      solid: 'var(--om-downtime)', soft: 'var(--om-downtime-bg)' },
    // Held for a configuration change (#182) — nearer to blocked than to a break.
    CHANGE_HOLD: { label: 'Change hold', solid: 'var(--om-downtime)', soft: 'var(--om-downtime-bg)' },
    DONE:        { label: 'Done',        solid: 'var(--om-done)',     soft: 'var(--om-done-bg)' },
};
export function statusOf(s) { return STATUS[s] || STATUS.PENDING; }
export function statusLabel(s) { return __(statusOf(s).label); }

export const MAINT = 'var(--om-maint)';
export const MAINT_BG = 'var(--om-maint-bg)';

// Distinct shift accents for the day/shift column headers (decorative — they
// only tell the shift sub-columns apart, carrying no status meaning).
const SHIFT_COLORS = { 1: '#6366f1', 2: '#0ea5e9', 3: '#14b8a6', 4: '#8b5cf6' };
export function shiftColor(n) { return SHIFT_COLORS[n] || 'var(--om-accent)'; }

// Priority on the OpenMES 1–5 scale (0 reads as Lowest).
export function priorityMeta(p) {
    if (p >= 5) return { label: 'Urgent', color: 'var(--om-blocked)' };
    if (p === 4) return { label: 'High',   color: 'var(--om-accent)' };
    if (p === 3) return { label: 'Medium', color: 'var(--om-downtime)' };
    if (p === 2) return { label: 'Low',    color: 'var(--om-accepted)' };
    return         { label: 'Lowest', color: 'var(--om-faint)' };
}

// Load heat — single source of truth in lib/load (shared with the capacity view).
export const loadColor = loadColorVar;
export function loadLabel(pct) {
    if (pct > 100) return __('Overloaded');
    if (pct > 80) return __('Near capacity');
    return __('Healthy');
}
export function hhmm(t) { return t ? String(t).slice(0, 5) : ''; }
export function fmtQty(n) { return n == null ? '—' : formatNumber(n); }

// ── Multi-segment placements ────────────────────────────────────────────────
// An order runs one primary placement (the wo's own line/date columns — the
// minute plan lives there) plus any number of coarse extra segments
// (wo.placements). Each segment has a stable `key`: 'primary' or the extra
// placement's id.

const segStartKey = (p) => `${p.due_date}|${String(p.shift_number ?? 1).padStart(2, '0')}`;
const segEndKey = (p) => `${p.end_date || p.due_date}|${String(p.end_shift_number ?? p.shift_number ?? 1).padStart(2, '0')}`;
