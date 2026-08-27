/**
 * ISO-day helpers and the date-range presets, with no React in sight.
 *
 * Split out of `DatePicker/index.web.jsx` so the arithmetic can be unit-tested:
 * quarter edges, Monday-first week starts, month lengths and year rollovers are
 * the parts that break silently — in December, on a leap year, or in the one
 * quarter nobody clicked — and a screenshot of the picker proves none of it.
 * Everything here is a pure function of an anchor date.
 *
 * Days are plain `YYYY-MM-DD` strings. They compare correctly as strings, and
 * they carry no time or zone, which is what a date filter actually means: "the
 * 12th" is the 12th wherever the reader is standing.
 */

const pad = (n) => String(n).padStart(2, '0');

/** month is 0-based, matching `Date`. */
export const toISO = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`;

export const parseISO = (s) => {
    if (!s) return null;
    const [y, m, d] = String(s).split('-').map(Number);
    if (!y || !m || !d) return null;
    return { y, m: m - 1, d };
};

export const todayISO = () => {
    const t = new Date();
    return toISO(t.getFullYear(), t.getMonth(), t.getDate());
};

/** ISO YYYY-MM-DD compares correctly as plain strings. */
export const inRange = (iso, min, max) => (!min || iso >= min) && (!max || iso <= max);

/** A `Date` back to its ISO day. */
export const dISO = (dt) => toISO(dt.getFullYear(), dt.getMonth(), dt.getDate());

/** Local midnight for an ISO day, so arithmetic never lands mid-DST-shift. */
export const dayOf = (iso) => {
    const p = parseISO(iso);
    return p ? new Date(p.y, p.m, p.d) : null;
};

// Every helper below goes through the Date constructor's own overflow handling
// — `new Date(2026, 12, 1)` is January 2027, `day 0` is the previous month's
// last — so month lengths, leap years and year boundaries need no special case.
const addDays = (dt, n) => new Date(dt.getFullYear(), dt.getMonth(), dt.getDate() + n);
/** Monday of the week `dt` falls in — the grid is Monday-first, so these agree. */
const startOfWeek = (dt) => addDays(dt, -((dt.getDay() + 6) % 7));
const startOfMonth = (dt, offset = 0) => new Date(dt.getFullYear(), dt.getMonth() + offset, 1);
/** Last day of the month `offset` months away — day 0 of the month after it. */
const endOfMonth = (dt, offset = 0) => new Date(dt.getFullYear(), dt.getMonth() + offset + 1, 0);
const startOfQuarter = (dt, offset = 0) =>
    new Date(dt.getFullYear(), Math.floor(dt.getMonth() / 3) * 3 + offset * 3, 1);
const endOfQuarter = (dt, offset = 0) =>
    new Date(dt.getFullYear(), Math.floor(dt.getMonth() / 3) * 3 + offset * 3 + 3, 0);

/**
 * The chips under a range grid.
 *
 * Every one resolves to a **whole** period rather than a period-to-date: this
 * filter runs over due dates and expiries, which mostly sit in the future, so
 * "This week" meaning Monday→today would hide the rows the reader opened the
 * filter to find. The day-counted ones are the exception and say so in the name.
 *
 * Labels are the English defaults; the app passes translations through
 * `presetLabels`, keeping this package locale-free like the rest of it.
 */
export const RANGE_PRESETS = [
    { key: 'today', label: 'Today', resolve: (t) => ({ from: dISO(t), to: dISO(t) }) },
    { key: 'yesterday', label: 'Yesterday', resolve: (t) => ({ from: dISO(addDays(t, -1)), to: dISO(addDays(t, -1)) }) },
    { key: 'this_week', label: 'This week', resolve: (t) => ({ from: dISO(startOfWeek(t)), to: dISO(addDays(startOfWeek(t), 6)) }) },
    { key: 'last_week', label: 'Last week', resolve: (t) => ({ from: dISO(addDays(startOfWeek(t), -7)), to: dISO(addDays(startOfWeek(t), -1)) }) },
    { key: 'last_7_days', label: 'Last 7 days', resolve: (t) => ({ from: dISO(addDays(t, -6)), to: dISO(t) }) },
    { key: 'this_month', label: 'This month', resolve: (t) => ({ from: dISO(startOfMonth(t)), to: dISO(endOfMonth(t)) }) },
    { key: 'last_month', label: 'Last month', resolve: (t) => ({ from: dISO(startOfMonth(t, -1)), to: dISO(endOfMonth(t, -1)) }) },
    { key: 'last_30_days', label: 'Last 30 days', resolve: (t) => ({ from: dISO(addDays(t, -29)), to: dISO(t) }) },
    { key: 'this_quarter', label: 'This quarter', resolve: (t) => ({ from: dISO(startOfQuarter(t)), to: dISO(endOfQuarter(t)) }) },
    { key: 'last_quarter', label: 'Last quarter', resolve: (t) => ({ from: dISO(startOfQuarter(t, -1)), to: dISO(endOfQuarter(t, -1)) }) },
    // Four quarters ending with the one we are in, not the last four finished.
    { key: 'last_4_quarters', label: 'Last 4 quarters', resolve: (t) => ({ from: dISO(startOfQuarter(t, -3)), to: dISO(endOfQuarter(t)) }) },
    { key: 'this_year', label: 'This year', resolve: (t) => ({ from: toISO(t.getFullYear(), 0, 1), to: toISO(t.getFullYear(), 11, 31) }) },
    { key: 'last_year', label: 'Last year', resolve: (t) => ({ from: toISO(t.getFullYear() - 1, 0, 1), to: toISO(t.getFullYear() - 1, 11, 31) }) },
    { key: 'last_12_months', label: 'Last 12 months', resolve: (t) => ({ from: dISO(startOfMonth(t, -11)), to: dISO(endOfMonth(t)) }) },
];

/**
 * Which preset (if any) `{ from, to }` is exactly, so the matching chip can read
 * as active. Matches on both ends, so a range picked by hand that happens to be
 * this month lights that chip too — the chip says what the selection *is*, not
 * how it was made.
 */
export function matchPreset(value, today) {
    if (!value?.from || !value?.to) return null;
    const t = dayOf(today);
    if (!t) return null;

    return RANGE_PRESETS.find((p) => {
        const r = p.resolve(t);
        return r.from === value.from && r.to === value.to;
    })?.key ?? null;
}

/**
 * ISO-day arithmetic for the grid's keyboard navigation (arrows, Home/End,
 * PageUp/PageDown). Kept here with the rest of the date maths so the month
 * lengths, leap years and year rollovers are testable without a DOM.
 */
export const shiftDays = (iso, n) => {
    const dt = dayOf(iso);
    return dt ? dISO(addDays(dt, n)) : null;
};

/** Monday of the week `iso` falls in — the grid is Monday-first. */
export const isoWeekStart = (iso) => {
    const dt = dayOf(iso);
    return dt ? dISO(startOfWeek(dt)) : null;
};

export const isoWeekEnd = (iso) => {
    const dt = dayOf(iso);
    return dt ? dISO(addDays(startOfWeek(dt), 6)) : null;
};

/**
 * The same day number `n` months away, **clamped** to the target month's length
 * rather than overflowing: PageUp from 31 March lands on 28 February, not 3
 * March. `new Date(y, m, 31)` would roll forward, so the day is capped first.
 */
export const shiftMonths = (iso, n) => {
    const p = parseISO(iso);
    if (!p) return null;
    const first = new Date(p.y, p.m + n, 1);
    return withYearMonth(iso, first.getFullYear(), first.getMonth());
};

/** The same day number in an arbitrary month, clamped the same way. */
export const withYearMonth = (iso, y, m) => {
    const p = parseISO(iso);
    if (!p) return null;
    const len = new Date(y, m + 1, 0).getDate();
    return toISO(y, m, Math.min(p.d, len));
};
