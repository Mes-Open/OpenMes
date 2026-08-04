import { createContext, Fragment, useContext, useEffect, useMemo, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { useLiveQuery } from '@tanstack/react-db';
import { ActionMenu, Breadcrumbs, Button, Icon, StatusPill } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { realtimeCollection } from '../lib/realtimeCollection';
import PageTitle from './PageTitle';
import Tooltip from './Tooltip';
import useConfirm from './useConfirm';
import { __ } from '../lib/i18n';
import { tableLabels, filterLabels, normalizeFilter } from '../lib/tableLabels';

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
/**
 * Action name → Lucide icon. The names the pages already pass (`icon: 'edit'`)
 * stay the vocabulary; this maps them onto the shared set so the glyphs come
 * from one maintained source instead of `d` strings pasted out of the old Blade
 * tables. Native screens can render the same names through @openmes/ui's Icon.
 */
const ACTION_ICON = {
    open: 'eye',
    edit: 'square-pen',
    delete: 'trash-2',
    deactivate: 'ban',
    activate: 'circle-check',
    // Status-transition verbs. These sit beside their label in the action rail —
    // the icon is recognition, the word is what makes it unambiguous.
    accept: 'check',
    reject: 'x',
    pause: 'circle-pause',
    resume: 'circle-play',
    complete: 'circle-check',
    reopen: 'rotate-ccw',
    cancel: 'ban',
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
/**
 * Fixed action rail — one slot per possible action, in the same order on every
 * row, so slot N starts at the same x whatever the row's status. A slot the row
 * can't use renders as empty space of the same width rather than collapsing,
 * which is what made the old variable-length button group jump around.
 *
 * Each slot is `{ key, width, resolve(row) => action | null }`; `resolve`
 * returning null leaves the slot blank. Actions carry `icon` + `label` — the
 * icon for recognition, the word so verbs like Accept/Reject stay unambiguous.
 *
 * A slot may instead resolve to `{ menu: [items] }`, which renders the overflow
 * menu — the home for everything that isn't the row's one obvious next step.
 * Items follow ActionMenu's shape (`divider`, `destructive`, `onSelect`), plus
 * an optional `confirm` this rail wires to the confirmation dialog.
 */
function ActionRail({ slots, row, confirm }) {
    return (
        <div className="flex items-center justify-end gap-1.5">
            {slots.map((slot) => {
                const a = slot.resolve(row);
                if (!a) return <span key={slot.key} className="shrink-0" style={{ width: slot.width }} />;

                if (a.menu) {
                    const items = a.menu.filter(Boolean).map((item) => {
                        // Pages name actions ('edit', 'accept'); ACTION_ICON turns
                        // that into a glyph, exactly as it does for the rail, so a
                        // verb looks the same wherever it is shown.
                        const withIcon = item.icon ? { ...item, icon: ACTION_ICON[item.icon] ?? item.icon } : item;
                        return withIcon.confirm
                            ? { ...withIcon, onSelect: () => confirm(withIcon.confirm, () => withIcon.onSelect?.()) }
                            : withIcon;
                    });
                    if (items.length === 0) {
                        return <span key={slot.key} className="shrink-0" style={{ width: slot.width }} />;
                    }

                    return (
                        <ActionMenu
                            key={slot.key}
                            items={items}
                            linkAs={Link}
                            trigger={
                                // A real <button>, not a role="button" span: it is what
                                // gives the menu Enter/Space (every non-primary row
                                // action now lives behind it), and what DataTable's
                                // double-click guard looks for before treating a click
                                // as "open this row".
                                <button
                                    type="button"
                                    aria-label={__(a.label ?? 'More')}
                                    className="inline-flex h-[30px] cursor-pointer items-center justify-center rounded-om-sm px-2 text-om-muted transition-colors hover:bg-om-chip hover:text-om-ink"
                                    style={{ width: slot.width }}
                                >
                                    <Icon name="ellipsis" size={16} className="shrink-0" />
                                </button>
                            }
                        />
                    );
                }

                const onClick = a.confirm ? () => confirm(a.confirm, () => a.onClick?.()) : a.onClick;
                const tone = RAIL_TONE[a.variant] ?? RAIL_TONE.secondary;
                const content = (
                    <>
                        {a.icon && ACTION_ICON[a.icon] && (
                            <Icon name={ACTION_ICON[a.icon]} size={15} className="shrink-0" />
                        )}
                        {a.label && !a.iconOnly && <span className="truncate">{__(a.label)}</span>}
                    </>
                );
                const cls = `inline-flex h-[30px] shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-om-sm px-2 text-[12.5px] font-semibold transition-colors ${tone}`;

                // Only an icon-only button needs a hover label — one that repeats
                // text already on the button is noise. `aria-label` names the
                // control either way; a tooltip is not an accessible name.
                const iconOnly = !a.label || a.iconOnly;
                const control = a.href ? (
                    <Link href={a.href} className={cls} style={{ width: slot.width }} data-action={a.label} aria-label={__(a.label)}>
                        {content}
                    </Link>
                ) : (
                    <button type="button" onClick={onClick} className={cls} style={{ width: slot.width }} data-action={a.label} aria-label={__(a.label)}>
                        {content}
                    </button>
                );

                // The app's own label bubble, placed the way the collapsed sidebar
                // labels its icons — not the browser's native `title` box, which
                // ignores the design system and lands wherever the OS puts it.
                return iconOnly ? (
                    <Tooltip key={slot.key} label={__(a.label)} placement="left">
                        {control}
                    </Tooltip>
                ) : (
                    <Fragment key={slot.key}>{control}</Fragment>
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
                if (a.icon && ACTION_ICON[a.icon]) {
                    const cls = `p-1.5 rounded-om-sm transition-colors ${ICON_COLOR[a.icon]}`;
                    const glyph = <Icon name={ACTION_ICON[a.icon]} size={20} />;
                    // `aria-label` (not `title`) names the control — the styled
                    // tooltip carries the visible hover label.
                    //
                    // Placed to the side, the way the collapsed sidebar labels its
                    // icons. Above (the default) drops the bubble onto the previous
                    // row's action buttons in a dense list; the actions column sits
                    // on the right edge, so `left` is the sidebar's mirror.
                    return (
                        <Tooltip key={i} label={__(a.label)} placement="left">
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

/**
 * Selection-bar chips (design §12) — the shared Button's own tones at the
 * selection bar's chip height. `warning` has no Button variant, so it keeps its
 * class; the other two would otherwise be a second copy of Button's palette.
 */
// `!` because Button's own rounded-om-sm would otherwise win on stylesheet
// order — the selection chips are deliberately tighter than a full button.
const BULK_CHIP_SIZE = 'h-[30px] rounded-[6px]! px-3 text-[12.5px]!';
const BULK_CHIP_WARNING = `inline-flex cursor-pointer items-center justify-center font-semibold transition-colors bg-om-downtime-bg text-om-downtime hover:brightness-95 ${BULK_CHIP_SIZE}`;

/**
 * The "new record" button. Rendered as the shared Button when it opens a modal;
 * the Inertia <Link> variant can't be a Button (that renders a <button>), so it
 * borrows the same primary/md classes — kept here rather than duplicated at
 * every call site.
 */
const CREATE_BTN_CLASS =
    'inline-flex cursor-pointer items-center justify-center gap-2 rounded-om-sm bg-om-ink px-4 py-[9px] text-[13px] font-semibold text-om-on-ink transition-colors hover:bg-om-ink-hover';

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
    /** Trail above this page, [{ label, href }] — the page title is appended as
     *  the current entry, so a page only lists its ancestors. */
    breadcrumbs,
    /** Lucide icon for this page's own breadcrumb entry. */
    titleIcon,
    emptyText = 'Nothing here yet.',
    filterFn,
    subtitle,
    pageSize = 50,
    enableSelection,
    bulkActions,
    fullWidth = false,
    /** Rows fill the space left under the table by default — see DataTable's
     *  `bodyMaxHeight`. Pass a CSS length to cap it at something fixed instead. */
    bodyMaxHeight = 'fill',
    /** Swap the pager for rows that keep coming as you scroll (design reference:
     *  the work-order list). The summary row then ends the table. */
    infinite = false,
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
    // Memoised: a fresh array identity every render would rebuild TanStack's
    // core row model and drop each row's value caches, making the faceting the
    // filter row depends on recompute cold.
    const visibleRows = useMemo(
        () => (filterFn ? (rows ?? []).filter(filterFn) : (rows ?? [])),
        [rows, filterFn],
    );

    // Map the declarative column config → TanStack column defs. Column ids stay
    // stable (= c.key) so sort/page/filter state survives live data re-renders.
    const tableColumns = useMemo(() => {
        // One copy of the filter-cell strings for every column below.
        const filterCopy = filterLabels();
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

    // `onCreate` opens the page's own modal; `createHref` navigates to the full
    // create page. A page may pass both — the button then opens the modal, and the
    // route stays reachable/bookmarkable.
    const createControl = (onCreate || createHref)
        ? (onCreate
            ? <Button variant="primary" className="py-[9px]!" leftIcon={<Icon name="plus" size={14} />} onClick={onCreate}>{__(createLabel)}</Button>
            : <Link href={createHref} className={CREATE_BTN_CLASS}><Icon name="plus" size={14} />{__(createLabel)}</Link>)
        : null;

    return (
        <LiveClockProvider active={hasLiveColumn}>
        {/* Full width, unlike the form pages' max-w-7xl: a list with a dozen
            columns should spend the space it has rather than scroll sideways
            inside a 1280px box on a 1900px screen. */}
        <div className="w-full">

            {/* The trail lives in the app header's title slot, sharing that bar
                with the clock instead of taking a row of its own. Pages that pass
                no `breadcrumbs` still get their title as the current entry. */}
            <PageTitle>
                <Breadcrumbs
                    linkAs={Link}
                    items={(breadcrumbs ?? []).map((b) => ({ ...b, label: __(b.label) })).concat({ label: __(title), icon: titleIcon })}
                />
            </PageTitle>

            <DataTable
                data={visibleRows}
                columns={tableColumns}
                {...tableLabels()}
                emptyLabel={__(emptyText)}
                pageSize={pageSize}
                // Cap the scroll body to what's left of the viewport so the
                // horizontal scrollbar and the pager stay on screen. Without this a
                // wide table's only sideways scrollbar sits below the last row, so
                // you had to scroll to the bottom of the page to move left/right.
                // It's a max, so short tables are unaffected.
                toolbarStart={subtitle}
                toolbarEnd={createControl}
                bodyMaxHeight={bodyMaxHeight}
                infinite={infinite}
                totalLabel={(n) => __(':count rows', { count: n })}
                // Double-click opens the record — the row stays a plain row for
                // single clicks, so selecting text and ticking checkboxes still work.
                onRowDoubleClick={detailHref ? (row) => {
                    const href = detailHref(row);
                    if (href) router.visit(href);
                } : undefined}
                enableSelection={selectable}
                getRowId={(row) => String(getKey(row))}
                // Third arg is the same confirm modal the row actions use, so a
                // destructive bulk action doesn't have to reach for window.confirm.
                bulkActions={
                    bulkActionItems
                        ? (rows, clear) =>
                              bulkActionItems
                                  .filter((b) => !b.applies || b.applies(rows))
                                  .map((b) => (
                                      <Button
                                          key={b.key}
                                          variant={b.variant === 'danger' ? 'danger' : 'secondary'}
                                          size="sm"
                                          className={
                                              b.variant === 'warning' ? BULK_CHIP_WARNING : BULK_CHIP_SIZE
                                          }
                                          onClick={() => {
                                              const run = () => b.onClick(rows, clear);
                                              // Bulk actions hit many records at once, so
                                              // they always confirm — no silent mass edits.
                                              const c = typeof b.confirm === 'function' ? b.confirm(rows) : b.confirm;
                                              c ? confirm(c, run) : run();
                                          }}
                                      >
                                          {__(b.label)}
                                      </Button>
                                  ))
                        : bulkActions
                          ? (rows, clear) => bulkActions(rows, clear, confirm)
                          : undefined
                }
                selectedLabel={__('selected')}
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
