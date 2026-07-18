import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { realtimeCollection } from '../lib/realtimeCollection';
import { __ } from '../lib/i18n';

// Shared "now" clock for columns flagged `live: true` (e.g. an elapsed-time
// column). A single interval per table bumps this context; each live cell is a
// context consumer, so it re-renders on every tick even though the underlying
// row data (and the memoized DataTable) didn't change. 30s cadence is plenty for
// minute/hour/day-granularity durations and stays cheap.
const NowContext = createContext(Date.now());
const LIVE_TICK_MS = 30_000;

/** A cell whose value depends on the current time; recomputes on each clock tick. */
function LiveCell({ col, row }) {
    const now = useContext(NowContext);
    return col.render ? col.render(row, now) : row[col.key];
}

/**
 * Generic admin list backed by a Reverb-synced collection + TanStack DB live
 * query, rendered through the shared `DataTable` (design §12): global search,
 * click-to-sort headers (SHIFT for multi-sort), column-visibility menu and
 * pagination come for free; optional per-column filters and row selection.
 *
 * Extracted from the Product Types pilot — the shared shape of every admin CRUD
 * list. Rows live-sync (create/edit/delete reflect without refresh); the page
 * just declares columns and per-row actions.
 *
 * Props:
 *   shape       — collection name (must be in ShapeRegistry)
 *   title       — heading
 *   createHref / createLabel — optional "new" button
 *   columns     — [{ key, label, render?(row), className?, align?, sortable?,
 *                    filter?: 'text'|'select', options?, allLabel?, filterPlaceholder?, flex?,
 *                    live?, sortAccessor?(row) }]
 *                 live: recompute the cell against a shared 30s clock (render gets (row, now))
 *                 sortAccessor: value to sort by when the displayed value isn't the raw
 *                   field (e.g. an "age" column that shows created_at but should sort by
 *                   elapsed time); search still uses the raw field
 *   orderBy     — row field to sort by (default 'name') — initial live-query order
 *   orderDir    — 'asc' | 'desc' (default 'asc')
 *   getKey      — row → key (default row.id)
 *   actions     — row → [{ label, href?, onClick?, variant?, className? }]
 *                 variant: 'secondary' (default) | 'primary' | 'danger' | 'warning'
 *                 className overrides the variant when you need a one-off style.
 *   emptyText   — shown when no rows
 *   pageSize    — rows per page (default 12)
 *   enableSelection / bulkActions / selectionLabel — opt-in row selection toolbar
 */

/**
 * Icon-button row actions — the pre-React-migration look. The standard CRUD trio
 * (Edit / toggle-active / Delete) renders as a compact colored icon button with a
 * tooltip; SVG paths are copied verbatim from the legacy Blade tables so the icons
 * match exactly. Pass `icon: 'edit' | 'delete' | 'activate' | 'deactivate'`.
 */
const ICON_PATH = {
    edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
    delete: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
    deactivate: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
    activate: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
};
const ICON_COLOR = {
    edit: 'text-om-muted hover:text-om-ink hover:bg-om-chip',
    delete: 'text-om-blocked hover:bg-om-blocked-bg',
    deactivate: 'text-om-muted hover:text-om-ink hover:bg-om-chip',
    activate: 'text-om-muted hover:text-om-ink hover:bg-om-chip',
};

/**
 * Labeled-button styles for non-CRUD domain actions (Accept, Pause, Cancel, …)
 * that have no obvious icon — kept as real buttons rather than plain links.
 * Geist White (light-only v1 — dark: variants removed).
 */
const ACTION_BASE = 'inline-flex items-center justify-center rounded-om-sm px-3 py-1.5 text-[12.5px] font-semibold transition-colors';
const ACTION_CLASS = {
    primary: `${ACTION_BASE} bg-om-ink text-om-on-ink hover:bg-om-ink-hover`,
    secondary: `${ACTION_BASE} bg-om-chip text-om-ink hover:bg-om-line2`,
    danger: `${ACTION_BASE} bg-om-blocked-bg text-om-blocked hover:bg-[#f8ddd6]`,
    warning: `${ACTION_BASE} bg-om-downtime-bg text-om-downtime hover:brightness-95`,
};
const actionClass = (a) => a.className ?? ACTION_CLASS[a.variant] ?? ACTION_CLASS.secondary;

/** Render one row's action buttons (icon trio + labeled domain actions). */
function RowActions({ actions, row }) {
    return (
        <div className="flex items-center justify-end gap-2">
            {actions(row).map((a, i) => {
                // Icon button (Edit / toggle / Delete) — the legacy look.
                if (a.icon && ICON_PATH[a.icon]) {
                    const cls = `p-1.5 rounded-om-sm transition-colors ${ICON_COLOR[a.icon]}`;
                    const glyph = (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={ICON_PATH[a.icon]} />
                        </svg>
                    );
                    return a.href ? (
                        <Link key={i} href={a.href} className={cls} title={__(a.label)} aria-label={__(a.label)} data-action={a.label}>
                            {glyph}
                        </Link>
                    ) : (
                        <button key={i} onClick={a.onClick} className={cls} title={__(a.label)} aria-label={__(a.label)} data-action={a.label}>
                            {glyph}
                        </button>
                    );
                }
                // Labeled button (domain actions without an icon).
                return a.href ? (
                    <Link key={i} href={a.href} className={actionClass(a)} data-action={a.label}>
                        {__(a.label)}
                    </Link>
                ) : (
                    <button key={i} onClick={a.onClick} className={actionClass(a)} data-action={a.label}>
                        {__(a.label)}
                    </button>
                );
            })}
        </div>
    );
}

export default function ResourceTable({
    shape,
    title,
    createHref,
    createLabel = '+ New',
    columns,
    orderBy = 'name',
    orderDir = 'asc',
    getKey = (row) => row.id,
    actions,
    emptyText = 'Nothing here yet.',
    filterFn,
    subtitle,
    pageSize = 50,
    enableSelection = false,
    bulkActions,
    selectionLabel,
}) {
    const collection = useMemo(() => realtimeCollection(shape, getKey), [shape]);

    const { data: rows } = useLiveQuery((q) =>
        q.from({ r: collection }).orderBy(({ r }) => r[orderBy], orderDir),
    );

    // Drive a shared clock only when a column opts into live updates, so tables
    // without a time-based column never schedule a needless interval.
    const hasLiveColumn = useMemo(() => columns.some((c) => c.live), [columns]);
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (!hasLiveColumn) return undefined;
        const id = setInterval(() => setNow(Date.now()), LIVE_TICK_MS);
        return () => clearInterval(id);
    }, [hasLiveColumn]);

    // Optional client-side filter (e.g. a dashboard KPI deep-link like
    // ?status=IN_PROGRESS) — applied over the live rows so it stays reactive.
    const visibleRows = filterFn ? (rows ?? []).filter(filterFn) : (rows ?? []);

    // Map the declarative column config → TanStack column defs. Column ids stay
    // stable (= c.key) so sort/page/filter state survives live data re-renders.
    const tableColumns = useMemo(() => {
        // Pick the column that absorbs horizontal slack so short count/status
        // columns don't balloon: prefer the free-text column, else the first
        // left-aligned one. Pages can override per-column with `flex: true`.
        const flexKey =
            ['description', 'name', 'title', 'label'].find((k) => columns.some((c) => c.key === k)) ??
            columns.find((c) => c.align !== 'right')?.key;

        const defs = columns.map((c) => {
            const def = {
                id: c.key,
                accessorFn: (row) => row[c.key],
                header: __(c.label),
                enableSorting: c.sortable !== false,
                cell: ({ row }) => {
                    const content = c.live
                        ? <LiveCell col={c} row={row.original} />
                        : (c.render ? c.render(row.original) : row.original[c.key]);
                    return c.className ? <span className={c.className}>{content}</span> : content;
                },
                meta: {
                    align: c.align === 'right' ? 'right' : 'left',
                    flex: c.flex || c.key === flexKey,
                    filter: c.filter,
                    options: c.options,
                    allLabel: c.allLabel,
                    filterPlaceholder: c.filterPlaceholder,
                    menuLabel: __(c.label),
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
        if (actions) {
            defs.push({
                id: '_actions',
                header: __('Actions'),
                enableSorting: false,
                enableHiding: false,
                cell: ({ row }) => <RowActions actions={actions} row={row.original} />,
                meta: { align: 'right', menuLabel: __('Actions') },
            });
        }
        return defs;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [columns, actions]);

    return (
        <NowContext.Provider value={now}>
        <div className="max-w-7xl mx-auto">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__(title)}</h1>
                    {subtitle && <div className="mt-1">{subtitle}</div>}
                </div>
                {createHref && (
                    <Link
                        href={createHref}
                        className="inline-flex items-center justify-center rounded-om-sm bg-om-ink px-4 py-2.5 text-[13px] font-semibold text-om-on-ink transition-colors hover:bg-om-ink-hover"
                    >
                        {__(createLabel)}
                    </Link>
                )}
            </div>

            <DataTable
                data={visibleRows}
                columns={tableColumns}
                searchPlaceholder={__('Search…')}
                columnsLabel={__('Columns')}
                columnsMenuLabel={__('Toggle columns')}
                emptyLabel={__(emptyText)}
                rangeLabel={(start, end, total) => (total === 0 ? __('0 results') : `${start}–${end} / ${total}`)}
                pageSize={pageSize}
                enableSelection={enableSelection}
                bulkActions={bulkActions}
                selectionLabel={selectionLabel}
            />
        </div>
        </NowContext.Provider>
    );
}

/** Reusable Active/Inactive pill for an `is_active` boolean column. */
export function ActiveBadge({ active }) {
    return (
        <StatusPill
            status={active ? 'running' : 'pending'}
            pulse={false}
            label={__(active ? 'Active' : 'Inactive')}
        />
    );
}
