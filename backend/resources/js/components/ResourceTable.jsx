import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { realtimeCollection } from '../lib/realtimeCollection';
import Tooltip from './Tooltip';
import useConfirm from './useConfirm';
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
 * Owns the shared clock so a tick re-renders only this thin wrapper and the
 * NowContext consumers (the live cells) - NOT the parent ResourceTable body,
 * which would otherwise re-run its live query/filter and hand DataTable freshly
 * allocated props every 30s, defeating its memoization. The interval runs only
 * while `active`, so tables without a live column never schedule a timer.
 */
function LiveClockProvider({ active, children }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!active) return undefined;
        const id = setInterval(() => setNow(Date.now()), LIVE_TICK_MS);
        return () => clearInterval(id);
    }, [active]);

    return <NowContext.Provider value={now}>{children}</NowContext.Provider>;
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
 *   detailHref  — row → URL of that record's detail page; double-clicking a row
 *                 opens it. Omit on lists whose records have no detail page.
 *   createHref / createLabel — optional "new" button
 *   onCreate    — makes that button open an in-page create modal instead of
 *                 navigating; pass alongside `createHref` to keep the standalone
 *                 create route working for deep links
 *   columns     — [{ key, label, render?(row), className?, align?, sortable?,
 *                    value?(row), filter?, options?, optionLabel?, allLabel?,
 *                    filterPlaceholder?, flex?, live?, sortAccessor?(row) }]
 *                 align: 'left' | 'center' | 'right'. Omit and the table decides:
 *                   columns whose values are numbers centre themselves, everything
 *                   else is left-aligned.
 *                 value: the plain value behind the cell — what search, sorting and
 *                   filtering see. Required for any column whose `key` is not a row
 *                   field (a lookup like `lineNames[row.line_id]`, a computed total);
 *                   without it those columns are invisible to search and unfilterable.
 *                 filter: omitted (default) | 'text' | 'select' | 'number' | 'date' | false
 *                   Every column is filterable by default, with the control chosen from
 *                   the column's own data: numeric columns get a comparison box
 *                   (12, >10, <=5, 3-8), date columns a from→to range picker, a small
 *                   closed value set a dropdown whose options are derived from the rows
 *                   (so they never go stale), anything wider a search box. Pass a string
 *                   to pin the kind, `options`/`optionLabel` to fix a dropdown's list, or
 *                   `false` to opt out — needed when the cell shows something other than
 *                   the value (an "age" column rendering elapsed time from a timestamp).
 *                 live: recompute the cell against a shared 30s clock (render gets (row, now))
 *                 sortAccessor: value to sort by when the displayed value isn't the raw
 *                   field (e.g. an "age" column that shows created_at but should sort by
 *                   elapsed time); search still uses the raw field
 *   orderBy     — row field to sort by (default 'name') — initial live-query order
 *   orderDir    — 'asc' | 'desc' (default 'asc')
 *   getKey      — row → key (default row.id)
 *   actions     — row → [{ label, href?, onClick?, variant?, className?, confirm? }]
 *                 variant: 'secondary' (default) | 'primary' | 'danger' | 'warning'
 *                 className overrides the variant when you need a one-off style.
 *                 confirm: a string (title) or { title, body, confirmLabel } — routes
 *                   the action through the design's confirm modal instead of firing
 *                   immediately. Use it for anything destructive; never `window.confirm`.
 *   emptyText   — shown when no rows
 *   pageSize    — rows per page (default 12)
 *   bulkActions — (rows, clearSelection, confirm) => nodes, shown beside the selection
 *                 counter. Optional: every list has selection checkboxes anyway,
 *                 and a Clear action is always provided. `enableSelection={false}`
 *                 drops the checkbox column for a list where picking rows is
 *                 meaningless.
 *   selectionLabel — (selected, total) => string for the selection counter
 *   fullWidth   — no-op, kept so existing call sites keep working. Lists are
 *                 always full width now (see the render below); a page that
 *                 passes it gets what it asked for either way. A page whose
 *                 surrounding cards are still max-w-7xl must widen them to
 *                 match, or they'll misalign with the table.
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
    // Status-transition verbs. These sit beside their label in the action rail —
    // the icon is recognition, the word is what makes it unambiguous.
    accept: 'M5 13l4 4L19 7',
    reject: 'M6 18L18 6M6 6l12 12',
    pause: 'M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z',
    resume: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    complete: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    reopen: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
    cancel: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
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

/**
 * Calendar footer copy — @openmes/ui ships English defaults, the app translates.
 * Built lazily (not at module load) so it reads the locale after it is applied.
 */
const calendarCopy = () => ({
    todayLabel: __('Today'),
    todayWord: __('today'),
    rangeLabel: __('Date range'),
    pickEndLabel: __('Pick an end date'),
});

/** Render one row's action buttons (icon trio + labeled domain actions). */
/**
 * Fixed action rail — one slot per possible action, in the same order on every
 * row, so slot N starts at the same x whatever the row's status. A slot the row
 * can't use renders as empty space of the same width rather than collapsing,
 * which is what made the old variable-length button group jump around.
 *
 * Each slot is `{ key, width, resolve(row) => action | null }`; `resolve`
 * returning null leaves the slot blank. Actions carry `icon` + `label` — the
 * icon for recognition, the word so verbs like Accept/Reject stay unambiguous.
 */
function ActionRail({ slots, row, confirm }) {
    return (
        <div className="flex items-center justify-end gap-1.5">
            {slots.map((slot) => {
                const a = slot.resolve(row);
                if (!a) return <span key={slot.key} className="shrink-0" style={{ width: slot.width }} />;

                const onClick = a.confirm ? () => confirm(a.confirm, () => a.onClick?.()) : a.onClick;
                const tone = RAIL_TONE[a.variant] ?? RAIL_TONE.secondary;
                const content = (
                    <>
                        {a.icon && ICON_PATH[a.icon] && (
                            <svg className="size-[15px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={ICON_PATH[a.icon]} />
                            </svg>
                        )}
                        {a.label && !a.iconOnly && <span className="truncate">{__(a.label)}</span>}
                    </>
                );
                const cls = `inline-flex h-[30px] shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-om-sm px-2 text-[12.5px] font-semibold transition-colors ${tone}`;

                return a.href ? (
                    <Link key={slot.key} href={a.href} className={cls} style={{ width: slot.width }} data-action={a.label} title={__(a.label)}>
                        {content}
                    </Link>
                ) : (
                    <button key={slot.key} type="button" onClick={onClick} className={cls} style={{ width: slot.width }} data-action={a.label} title={__(a.label)}>
                        {content}
                    </button>
                );
            })}
        </div>
    );
}

/** Rail button tones — quieter than the old solid chips, since every row shows them. */
const RAIL_TONE = {
    secondary: 'border border-om-line bg-om-card text-om-ink hover:bg-om-chip',
    primary: 'bg-om-ink text-om-on-ink hover:bg-om-ink-hover',
    warning: 'border border-om-downtime-bg bg-om-downtime-bg text-om-downtime hover:brightness-95',
    danger: 'border border-om-blocked-bg bg-om-blocked-bg text-om-blocked hover:bg-[#f8ddd6]',
    ghost: 'text-om-muted hover:bg-om-chip hover:text-om-ink',
    'ghost-danger': 'text-om-blocked hover:bg-om-blocked-bg',
};

function RowActions({ actions, row, confirm }) {
    return (
        <div className="flex items-center justify-end gap-2">
            {actions(row).map((a, i) => {
                // An action carrying `confirm` routes through the shared confirm
                // modal instead of firing straight away.
                const onClick = a.confirm
                    ? () => confirm(a.confirm, () => a.onClick?.())
                    : a.onClick;
                // Icon button (Edit / toggle / Delete) — the legacy look.
                if (a.icon && ICON_PATH[a.icon]) {
                    const cls = `p-1.5 rounded-om-sm transition-colors ${ICON_COLOR[a.icon]}`;
                    const glyph = (
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d={ICON_PATH[a.icon]} />
                        </svg>
                    );
                    // `aria-label` (not `title`) names the control — the styled
                    // tooltip carries the visible hover label.
                    return (
                        <Tooltip key={i} label={__(a.label)}>
                            {a.href ? (
                                <Link href={a.href} className={cls} aria-label={__(a.label)} data-action={a.label}>
                                    {glyph}
                                </Link>
                            ) : (
                                <button onClick={onClick} className={cls} aria-label={__(a.label)} data-action={a.label}>
                                    {glyph}
                                </button>
                            )}
                        </Tooltip>
                    );
                }
                // Labeled button (domain actions without an icon).
                return a.href ? (
                    <Link key={i} href={a.href} className={actionClass(a)} data-action={a.label}>
                        {__(a.label)}
                    </Link>
                ) : (
                    <button key={i} onClick={onClick} className={actionClass(a)} data-action={a.label}>
                        {__(a.label)}
                    </button>
                );
            })}
        </div>
    );
}

/** Selection-bar chips (design §12): chip-filled, blocked-tinted when destructive. */
const BULK_CHIP_BASE = 'inline-flex h-[30px] cursor-pointer items-center justify-center rounded-[6px] px-3 text-[12.5px] font-semibold transition-colors';
const BULK_CHIP = {
    secondary: `${BULK_CHIP_BASE} bg-om-chip text-om-ink hover:bg-om-line2`,
    danger: `${BULK_CHIP_BASE} bg-om-blocked-bg text-om-blocked hover:bg-[#f8ddd6]`,
    warning: `${BULK_CHIP_BASE} bg-om-downtime-bg text-om-downtime hover:brightness-95`,
};

const CREATE_BTN_CLASS =
    'inline-flex cursor-pointer items-center justify-center rounded-om-sm bg-om-ink px-4 py-2.5 text-[13px] font-semibold text-om-on-ink transition-colors hover:bg-om-ink-hover';

export default function ResourceTable({
    shape,
    title,
    createHref,
    /** Called instead of navigating to `createHref` — for an in-page create modal. */
    onCreate,
    /** row → detail URL. Double-clicking a row opens it; omit for lists with no detail page. */
    detailHref,
    createLabel = '+ New',
    columns,
    orderBy = 'name',
    orderDir = 'asc',
    getKey = (row) => row.id,
    actions,
    /** Fixed action rail: [{ key, width, resolve(row) => action|null }]. Takes
     *  precedence over `actions` — same actions, but in aligned per-row slots. */
    actionSlots,
    /** Design §12 selection chips: [{ key, label, variant, confirm, onClick(rows, clear) }].
     *  Rendered as styled chips with the shared confirm modal wired in — preferred
     *  over the raw `bulkActions` render-prop, which leaves confirmation to callers. */
    bulkActionItems,
    emptyText = 'Nothing here yet.',
    filterFn,
    subtitle,
    pageSize = 50,
    enableSelection,
    bulkActions,
    selectionLabel = (n, m) => __(':n of :m selected', { n, m }),
    fullWidth = false,
    /** Height left for rows after the page chrome (title, toolbar, header, pager). */
    bodyMaxHeight = 'max(240px, calc(100vh - 300px))',
}) {
    // Every list gets selection checkboxes, whether or not the page defines bulk
    // actions — picking rows is useful on its own (counting a subset, keeping your
    // place while scanning a long list). `enableSelection={false}` opts out.
    const selectable = enableSelection ?? true;
    // One dialog for the whole table, shared by every row's actions.
    const { confirm, dialog: confirmDialog } = useConfirm();
    const collection = useMemo(() => realtimeCollection(shape, getKey), [shape]);

    const { data: rows } = useLiveQuery((q) =>
        q.from({ r: collection }).orderBy(({ r }) => r[orderBy], orderDir),
    );

    // A live column (e.g. elapsed time) opts the table into a shared clock. The
    // clock state lives in LiveClockProvider (below the memoized DataTable), so a
    // tick never re-renders this component or its DataTable props.
    const hasLiveColumn = useMemo(() => columns.some((c) => c.live), [columns]);

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
                    filter: c.filter === false ? undefined : c.filter === true || c.filter == null ? 'auto' : c.filter,
                    options: c.options,
                    optionLabel: c.optionLabel,
                    allLabel: c.allLabel ?? __('All'),
                    filterPlaceholder: c.filterPlaceholder ?? __('Filter…'),
                    numberFilterPlaceholder: '>10',
                    numberFilterHint: __('Examples: 12, >10, <=5, 3-8'),
                    dateFilterPlaceholder: __('Any date'),
                    clearDateLabel: __('Clear'),
                    calendarProps: calendarCopy(),
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
        if (actionSlots || actions) {
            defs.push({
                id: '_actions',
                header: __('Actions'),
                enableSorting: false,
                enableHiding: false,
                cell: ({ row }) =>
                    actionSlots ? (
                        <ActionRail slots={actionSlots} row={row.original} confirm={confirm} />
                    ) : (
                        <RowActions actions={actions} row={row.original} confirm={confirm} />
                    ),
                meta: { align: 'right', menuLabel: __('Actions') },
            });
        }
        return defs;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [columns, actions, actionSlots]);

    return (
        <LiveClockProvider active={hasLiveColumn}>
        {/* Full width, unlike the form pages' max-w-7xl: a list with a dozen
            columns should spend the space it has rather than scroll sideways
            inside a 1280px box on a 1900px screen. */}
        <div className="w-full">

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">{__(title)}</h1>
                    {subtitle && <div className="mt-1">{subtitle}</div>}
                </div>
                {/* `onCreate` opens the page's own modal; `createHref` navigates to
                    the full create page. A page may pass both — the button then
                    opens the modal, and the route stays reachable/bookmarkable. */}
                {(onCreate || createHref) && (
                    onCreate ? (
                        <button type="button" onClick={onCreate} className={CREATE_BTN_CLASS}>
                            {__(createLabel)}
                        </button>
                    ) : (
                        <Link href={createHref} className={CREATE_BTN_CLASS}>
                            {__(createLabel)}
                        </Link>
                    )
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
                filtersLabel={(n) => __('Filters: :n', { n })}
                clearFiltersLabel={__('Clear filters')}
                pageSize={pageSize}
                // Cap the scroll body to what's left of the viewport so the
                // horizontal scrollbar and the pager stay on screen. Without this a
                // wide table's only sideways scrollbar sits below the last row, so
                // you had to scroll to the bottom of the page to move left/right.
                // It's a max, so short tables are unaffected.
                bodyMaxHeight={bodyMaxHeight}
                // Double-click opens the record — the row stays a plain row for
                // single clicks, so selecting text and ticking checkboxes still work.
                onRowDoubleClick={detailHref ? (row) => {
                    const href = detailHref(row);
                    if (href) router.visit(href);
                } : undefined}
                enableSelection={selectable}
                getRowId={(row) => String(getKey(row))}
                selectAllLabel={__('Select all rows on this page')}
                selectRowLabel={__('Select row')}
                clearSelectionLabel={__('Clear')}
                // Third arg is the same confirm modal the row actions use, so a
                // destructive bulk action doesn't have to reach for window.confirm.
                bulkActions={
                    bulkActionItems
                        ? (rows, clear) =>
                              bulkActionItems
                                  .filter((b) => !b.applies || b.applies(rows))
                                  .map((b) => (
                                      <button
                                          key={b.key}
                                          type="button"
                                          onClick={() => {
                                              const run = () => b.onClick(rows, clear);
                                              // Bulk actions hit many records at once, so
                                              // they always confirm — no silent mass edits.
                                              const c = typeof b.confirm === 'function' ? b.confirm(rows) : b.confirm;
                                              c ? confirm(c, run) : run();
                                          }}
                                          className={BULK_CHIP[b.variant] ?? BULK_CHIP.secondary}
                                      >
                                          {__(b.label)}
                                      </button>
                                  ))
                        : bulkActions
                          ? (rows, clear) => bulkActions(rows, clear, confirm)
                          : undefined
                }
                selectedLabel={__('selected')}
                selectionLabel={selectionLabel}
            />
            {confirmDialog}
        </div>
        </LiveClockProvider>
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
