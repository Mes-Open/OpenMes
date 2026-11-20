// Staffing cells shared by Automatic scheduling (the staffing grid) and the
// Production Planner (the badge in every line × shift cell). Both read the
// same server-side count — people available for the line against people the
// planned work needs — so both screens colour a shift the same way.

/**
 * 'spare' | 'enough' | 'short' | 'idle' | 'untracked'.
 *
 * idle: tracked, but nothing is planned (and nobody is needed) there.
 */
export function staffingTone(cell) {
    if (!cell || !cell.tracked || cell.available == null) return 'untracked';
    const needed = cell.needed ?? 0;
    if (needed === 0) return 'idle';
    if (cell.available < needed) return 'short';
    if (cell.available === needed) return 'enough';
    return 'spare';
}

/** Colour tokens per tone — the app's status palette, dark mode included. */
export const TONE_STYLE = {
    spare: { color: 'var(--om-running)', background: 'var(--om-running-bg)' },
    enough: { color: 'var(--om-downtime)', background: 'var(--om-downtime-bg)' },
    short: { color: 'var(--om-blocked)', background: 'var(--om-blocked-bg)' },
    idle: { color: 'var(--om-faint)', background: 'var(--om-chip)' },
    untracked: { color: 'var(--om-faint)', background: 'var(--om-chip)' },
};

/** "Jan Kowalski" → "Jan K.", for the small line under a cell's number. */
export function shortName(name) {
    if (!name) return '';
    const parts = String(name).trim().split(/\s+/);
    if (parts.length < 2) return parts[0];
    return `${parts[0]} ${parts[parts.length - 1].charAt(0)}.`;
}

/** Monday (YYYY-MM-DD) of the week a YYYY-MM-DD date falls in. */
export function weekOf(date) {
    const [y, m, d] = date.split('-').map(Number);
    const t = new Date(Date.UTC(y, m - 1, d));
    const dow = (t.getUTCDay() + 6) % 7;
    t.setUTCDate(t.getUTCDate() - dow);
    return t.toISOString().slice(0, 10);
}

/**
 * Lay the simulation's per-shift rows out as a grid: one row per line, one
 * column per day + shift window, windowed by week so a long horizon stays a
 * few screens wide.
 *
 * @param {Array} rows staffing.shifts from the simulation
 * @returns {{ weeks: string[], columns: Object<string, Array>, lines: Array, cell: (lineId, colKey) => object|undefined }}
 */
/**
 * A draft generated before the available/needed count stored `present`,
 * `peak_operators` and `shortfall` instead. Such a draft stays in the
 * database across an upgrade, so read the old names as the new ones rather
 * than print "undefined/undefined" until someone regenerates it — and keep
 * the shortfall it recorded: a step the people could not staff was never
 * booked, so the peak alone would turn a short shift green. As the server
 * does, a short shift needs the people available plus the shortfall.
 */
export function normalizeCell(r) {
    if (!r || ('available' in r && 'needed' in r)) return r;
    const available = r.available ?? (r.tracked ? r.present ?? null : null);
    const peak = r.peak_operators ?? 0;
    const shortfall = Number(r.shortfall) || 0;
    return {
        ...r,
        available,
        needed: r.needed ?? (available != null && shortfall > 0 ? Math.max(peak, available + shortfall) : peak),
    };
}

export function staffingGrid(rows) {
    const columns = {};
    const seen = {};
    const lines = [];
    const cells = {};
    const lineSeen = {};
    (rows ?? []).forEach((r) => {
        const key = `${r.start}|${r.end}`;
        if (!seen[key]) {
            seen[key] = true;
            const week = weekOf(r.date);
            (columns[week] ??= []).push({ key, date: r.date, start: r.start, end: r.end, shift_name: r.shift_name });
        }
        if (!lineSeen[r.line_id]) {
            lineSeen[r.line_id] = { id: r.line_id, name: r.line_name, tracked: r.tracked };
            lines.push(lineSeen[r.line_id]);
        }
        if (r.tracked) lineSeen[r.line_id].tracked = true;
        cells[`${r.line_id}|${key}`] = normalizeCell(r);
    });
    Object.values(columns).forEach((cols) => cols.sort((a, b) => (a.start < b.start ? -1 : a.start > b.start ? 1 : 0)));
    lines.sort((a, b) => String(a.name).localeCompare(String(b.name)) || a.id - b.id);

    return {
        weeks: Object.keys(columns).sort(),
        columns,
        lines,
        cell: (lineId, colKey) => cells[`${lineId}|${colKey}`],
    };
}
