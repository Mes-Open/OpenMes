// Planner design tokens + pure layout/format helpers.
//
// Colours reference the OpenMES brand tokens (the `--om-*` CSS vars in
// resources/css/app.css), which are redefined under `.dark` — so referencing
// them in inline styles means dark mode "just works" with no per-component
// branching. This board follows the "OpenMES Schedule" design: technical
// Geist-Mono labelling, ACCEPTED = blue, maintenance = purple.
import { __, formatNumber } from '../../../../lib/i18n';
import { loadColorVar } from '../../../../lib/load';

export const MONO = 'var(--font-mono)';

// ── Work-order status → brand tokens ────────────────────────────────────────
export const STATUS = {
    PENDING:     { label: 'Pending',     solid: 'var(--om-pending)',  soft: 'var(--om-pending-bg)' },
    ACCEPTED:    { label: 'Accepted',    solid: 'var(--om-accepted)', soft: 'var(--om-accepted-bg)' },
    IN_PROGRESS: { label: 'Running',     solid: 'var(--om-running)',  soft: 'var(--om-running-bg)' },
    BLOCKED:     { label: 'Blocked',     solid: 'var(--om-blocked)',  soft: 'var(--om-blocked-bg)' },
    PAUSED:      { label: 'Paused',      solid: 'var(--om-downtime)', soft: 'var(--om-downtime-bg)' },
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

// ── Dates ───────────────────────────────────────────────────────────────────
export function parseDate(s) { // 'YYYY-MM-DD' -> local Date at noon (TZ-safe)
    if (!s) return null;
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d, 12, 0, 0);
}
export function fmtKey(date) {
    return date.getFullYear() + '-'
        + String(date.getMonth() + 1).padStart(2, '0') + '-'
        + String(date.getDate()).padStart(2, '0');
}
export function todayKey() { return fmtKey(new Date()); }

export function dayList(startStr, count, showWeekends) {
    const start = parseDate(startStr);
    if (!start) return [];
    const out = [];
    for (let i = 0; out.length < count && i < 90; i++) {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const dow = d.getDay();
        if (!showWeekends && (dow === 0 || dow === 6)) continue;
        out.push({ date: fmtKey(d), isWeekend: dow === 0 || dow === 6 });
    }
    return out;
}

// Read the wall-clock time straight from the ISO string (which carries the
// plant-timezone offset emitted by the server) rather than via `new Date()`,
// so the viewer's browser timezone can't shift the hourly layout or labels.
export function fmtTime(iso) {
    const m = /T(\d{2}):(\d{2})/.exec(iso || '');
    return m ? `${m[1]}:${m[2]}` : '';
}
export function hhmm(t) { return t ? String(t).slice(0, 5) : ''; }
export function minuteOfDay(iso) {
    const m = /T(\d{2}):(\d{2})/.exec(iso || '');
    return m ? (+m[1]) * 60 + (+m[2]) : 0;
}
// ISO week number for a 'YYYY-MM-DD' string.
export function isoWeek(dateStr) {
    const d = parseDate(dateStr);
    if (!d) return null;
    const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    const day = t.getUTCDay() || 7;
    t.setUTCDate(t.getUTCDate() + 4 - day);
    const yearStart = new Date(Date.UTC(t.getUTCFullYear(), 0, 1));
    return Math.ceil((((t - yearStart) / 86400000) + 1) / 7);
}
export function fmtQty(n) { return n == null ? '—' : formatNumber(n); }

// ── Weekly placement ────────────────────────────────────────────────────────
// The coarse (day + shift) slot an order occupies. Placed by due_date; minute-
// planned orders (which also carry planned_start_at) still appear, falling back
// to the planned date and deriving the shift from the planned hour when no
// explicit shift_number is set.
export function weeklySlot(wo, shiftsPerDay) {
    let date = wo.due_date;
    let shift = wo.shift_number;
    if (!date && wo.planned_start_at) date = wo.planned_start_at.slice(0, 10);
    if (!shift && wo.planned_start_at) {
        // Read the hour from the ISO string (plant-timezone offset), not via
        // new Date().getHours() which would shift by the viewer's browser TZ.
        const h = Math.floor(minuteOfDay(wo.planned_start_at) / 60);
        shift = Math.min(shiftsPerDay, Math.floor(h / (24 / shiftsPerDay)) + 1);
    }
    return { date, shift: Math.min(shift || 1, shiftsPerDay) };
}

// Which calendar day a work order shows on in the monthly view. Mirrors
// weeklySlot's precedence so coarsely-placed orders aren't dropped: explicit
// due_date, else the planned-start day, else the Monday of its ISO week, else
// (month-only) the 1st of its month.
export function onMonthlyDay(wo, iso, dayNum, monthNum) {
    if (wo.due_date) return wo.due_date === iso;
    if (wo.planned_start_at) return wo.planned_start_at.slice(0, 10) === iso;
    if (wo.week_number) {
        const d = parseDate(iso);
        return !!d && d.getDay() === 1 && isoWeek(iso) === wo.week_number;
    }
    if (wo.month_number) return wo.month_number === monthNum && dayNum === 1;
    return false;
}

// Lay a line's orders onto the day×shift columns as spanning blocks. Each item
// gets startCol/endCol (covering due_date·shift → end_date·end_shift) and a lane
// so overlapping spans stack instead of colliding. Columns: day*shiftsPerDay +
// (shift-1).
export function weeklyPlacements(orders, days, shiftsPerDay) {
    const dayIdx = {}; days.forEach((d, i) => { dayIdx[d.date] = i; });
    const N = days.length * shiftsPerDay;
    const colOf = (date, shift) => (date in dayIdx ? dayIdx[date] * shiftsPerDay + (Math.min(shift, shiftsPerDay) - 1) : -1);
    const items = [];
    orders.forEach((wo) => {
        const sl = weeklySlot(wo, shiftsPerDay);
        const startCol = colOf(sl.date, sl.shift);
        if (startCol < 0) return;
        let endCol = startCol;
        if (wo.end_date && (wo.end_date in dayIdx)) endCol = colOf(wo.end_date, wo.end_shift_number || sl.shift);
        else if (wo.end_shift_number && wo.end_shift_number > sl.shift) endCol = colOf(sl.date, wo.end_shift_number);
        if (endCol < startCol) endCol = startCol;
        items.push({ wo, startCol, endCol });
    });
    items.sort((a, b) => a.startCol - b.startCol || b.endCol - a.endCol);
    const laneEnds = [];
    items.forEach((it) => {
        let lane = laneEnds.findIndex((e) => e <= it.startCol);
        if (lane === -1) { lane = laneEnds.length; laneEnds.push(0); }
        laneEnds[lane] = it.endCol + 1;
        it.lane = lane;
    });
    return { items, lanes: Math.max(1, laneEnds.length), N };
}

// Occupancy-based load % for a line over the visible days × shifts.
export function lineLoad(orders, lineId, days, shiftsPerDay) {
    const total = days.length * shiftsPerDay;
    if (!total) return 0;
    const dayIdx = {}; days.forEach((d, i) => { dayIdx[d.date] = i; });
    const covered = new Set();
    orders.forEach((o) => {
        if (o.line_id !== lineId) return;
        const { date, shift } = weeklySlot(o, shiftsPerDay);
        if (date == null || !(date in dayIdx)) return;
        covered.add(dayIdx[date] * shiftsPerDay + (shift - 1));
    });
    return Math.round((covered.size / total) * 100);
}

// ── Hourly layout ───────────────────────────────────────────────────────────
// Greedy interval lane packing + conflict detection per line, for one day.
export function hourlyLanes(orders, lineId, dateStr) {
    const items = orders
        .filter((wo) => {
            if (wo.line_id !== lineId) return false;
            if (wo.planned_start_at && wo.planned_end_at) {
                return wo.planned_start_at.slice(0, 10) <= dateStr && dateStr <= wo.planned_end_at.slice(0, 10);
            }
            // Legacy: a due-date-only order on this day shows as a placeholder
            // block so it stays visible and can be dragged to get real times.
            return wo.due_date === dateStr;
        })
        .map((wo) => {
            if (!wo.planned_start_at || !wo.planned_end_at) {
                return { wo, start: 0, end: 60, spansOutside: false, placeholder: true };
            }
            const startsBefore = wo.planned_start_at.slice(0, 10) < dateStr;
            const endsAfter = wo.planned_end_at.slice(0, 10) > dateStr;
            const start = startsBefore ? 0 : minuteOfDay(wo.planned_start_at);
            const end = endsAfter ? 1440 : minuteOfDay(wo.planned_end_at);
            return { wo, start, end, spansOutside: startsBefore || endsAfter, placeholder: false };
        })
        .sort((a, b) => a.start - b.start || a.end - b.end);
    const laneEnds = [];
    items.forEach((it) => {
        let placed = -1;
        for (let l = 0; l < laneEnds.length; l++) { if (laneEnds[l] <= it.start) { placed = l; break; } }
        if (placed === -1) { placed = laneEnds.length; laneEnds.push(it.end); } else laneEnds[placed] = it.end;
        it.lane = placed;
    });
    const totalLanes = Math.max(1, laneEnds.length);
    items.forEach((a) => {
        a.conflict = items.some((b) => b !== a && a.start < b.end && b.start < a.end);
    });
    return { items, totalLanes };
}
