import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';

import { __ } from '../lib/i18n';
import { filterLabels, normalizeFilter } from '../lib/tableLabels';

/**
 * The list-column vocabulary, shared by `ResourceTable` and by any page that
 * embeds one of our lists inside a card.
 *
 * A page declares columns in the readable config form —
 * `{ key, label, render, value, filter, className, align, summary }` — and this
 * turns them into TanStack column defs. It lived inside `ResourceTable` until
 * the line detail page needed the work-order columns (`woColumns()`) in a panel
 * of its own: reaching for `DataTable` directly there produced a table that
 * shared none of the list's cell treatment, filters or footer totals, which is
 * exactly the drift this file exists to stop. One definition of a column, two
 * places that can render it.
 */

// Shared "now" clock for columns flagged `live: true` (e.g. an elapsed-time
// column). A single interval per table bumps this context; each live cell is a
// context consumer, so it re-renders on every tick even though the underlying
// row data (and the memoized DataTable) didn't change. 30s cadence is plenty for
// minute/hour/day-granularity durations and stays cheap.
export const NowContext = createContext(Date.now());
const LIVE_TICK_MS = 30_000;

/** A cell whose value depends on the current time; recomputes on each clock tick. */
function LiveCell({ col, row }) {
    const now = useContext(NowContext);
    return col.render ? col.render(row, now) : row[col.key];
}

/**
 * Owns the shared clock so a tick re-renders only this thin wrapper and the
 * NowContext consumers (the live cells) - NOT the parent table body, which would
 * otherwise re-run its live query/filter and hand DataTable freshly allocated
 * props every 30s, defeating its memoization. The interval runs only while
 * `active`, so tables without a live column never schedule a timer.
 */
export function LiveClockProvider({ active, children }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!active) return undefined;
        const id = setInterval(() => setNow(Date.now()), LIVE_TICK_MS);
        return () => clearInterval(id);
    }, [active]);

    return <NowContext.Provider value={now}>{children}</NowContext.Provider>;
}

/**
 * A row's identity cell, as a link to its detail page.
 *
 * Every list already knows the row's detail URL — it powers double-click-to-open
 * — but a plain-text cell gives no sign of it, so the only discoverable way in
 * was the ⋯ menu. This is the one place that link's treatment is defined.
 */
export function DetailLink({ href, children }) {
    return (
        <Link href={href} className="hover:text-om-accent hover:underline">
            {children}
        </Link>
    );
}

/**
 * Turn the column flagged `link: true` into a `DetailLink`. Opt-in per column
 * because only one cell per row should be the way in — a row of links reads as
 * a row of separate destinations.
 */
export function withDetailLinks(columns, detailHref) {
    if (!detailHref) return columns;

    return columns.map((c) => {
        if (!c.link) return c;

        const cell = c.render ?? ((row) => row[c.key]);

        return {
            ...c,
            render: (row, now) => {
                const href = detailHref(row);
                const content = cell(row, now);

                return href ? <DetailLink href={href}>{content}</DetailLink> : content;
            },
        };
    });
}

/** True when any column recomputes on the clock — i.e. the tick is worth running. */
export function hasLiveColumn(columns) {
    return columns.some((c) => c.live);
}

/** Config columns → TanStack column defs. */
export function buildColumnDefs(columns) {
    // One copy of the filter-cell strings for every column below.
    const filterCopy = filterLabels();
    // Pick the column that absorbs horizontal slack so short count/status
    // columns don't balloon: prefer the free-text column, else the first
    // left-aligned one. Pages can override per-column with `flex: true`.
    const flexKey =
        ['description', 'name', 'title', 'label'].find((k) => columns.some((c) => c.key === k)) ??
        columns.find((c) => c.align !== 'right')?.key;

    return columns.map((c) => {
        const def = {
            id: c.key,
            // `value` is what search/sort/filter operate on. Columns rendered from
            // a lookup (`lineNames[row.line_id]`) have no row field under `key`, so
            // without it the accessor yields undefined and the column silently
            // drops out of search and can't be filtered.
            accessorFn: c.value ?? ((row) => row[c.key]),
            header: __(c.label),
            enableSorting: c.sortable !== false,
            cell: ({ row }) => {
                const content = c.live
                    ? <LiveCell col={c} row={row.original} />
                    : (c.render ? c.render(row.original) : row.original[c.key]);
                return c.className ? <span className={c.className}>{content}</span> : content;
            },
            meta: {
                // Passed through as-is (undefined when unset) so DataTable can
                // auto-centre numeric columns; forcing 'left' here suppressed that.
                align: c.align,
                flex: c.flex || c.key === flexKey,
                // Filtering is on by default: every column gets a control chosen
                // from its own data, so a list never has arbitrary gaps in the
                // filter row. Pin the kind with a string, or opt a column out
                // with `filter: false` when its cell isn't what the value holds.
                ...filterCopy,
                filter: normalizeFilter(c.filter),
                options: c.options,
                optionLabel: c.optionLabel,
                allLabel: c.allLabel ?? filterCopy.allLabel,
                filterPlaceholder: c.filterPlaceholder ?? filterCopy.filterPlaceholder,
                menuLabel: __(c.label),
                // Off by default, still in the Columns menu — for fields worth
                // having but not worth the width on every screen.
                hidden: c.hidden,
                // 'sum' | 'avg' | fn(rows) — aggregate shown in the footer row.
                summary: c.summary,
            },
        };

        // Sort by a derived value (e.g. elapsed time) instead of the raw field,
        // so the arrow direction matches what the cell shows. accessorFn stays
        // the raw field so global search keeps working on it.
        if (c.sortAccessor) {
            def.sortingFn = (a, b) => {
                const va = c.sortAccessor(a.original);
                const vb = c.sortAccessor(b.original);
                return va === vb ? 0 : va < vb ? -1 : 1;
            };
        }

        return def;
    });
}

/** Memoised `buildColumnDefs` — the form a component actually wants. */
export function useColumnDefs(columns) {
    return useMemo(() => buildColumnDefs(columns), [columns]);
}
