/**
 * DataTable — Geist White system (design ref: OpenMES Components.dc.html §12).
 *
 * Web-only wrapper around @tanstack/react-table v8: global search, per-column
 * filters (column meta `{ filter: 'text' | 'select', options }`), multi-sort
 * (SHIFT-click), column visibility menu, sticky header, page-level row selection
 * with a bulk-actions toolbar, and pagination. Renders a real `<table>` so the
 * browser auto-distributes column widths to content (no manual sizing). No
 * native twin — web only.
 *
 * All user-facing strings arrive via props / column meta; only structural
 * glyphs (✓ –) are baked in; arrows and chevrons come from the shared Icon.
 *
 * Selection is keyed by `getRowId` — pass it for any live-synced list, or a
 * checked box follows the row index rather than the row.
 *
 * Rows are zebra-striped by default (`striped`), with hover and selection tints
 * layered above the stripe so all three states stay distinguishable.
 *
 * Feature toggles (default on): `searchable` (global search box), `columnToggle`
 * (column-visibility menu), `paginated` (pager footer + paging). Turn them off to
 * use DataTable as a plain styled, sortable table for short/detail lists, e.g.
 * `<DataTable searchable={false} columnToggle={false} paginated={false} … />`.
 *
 * Column def extras read from `meta`:
 *   align: 'left' | 'right'      — header/cell alignment, resize-handle side
 *   flex: true                   — column takes `minmax(140px, 1fr)` until resized
 *   filter: 'auto'|'text'|'select'|'number'|'date' — the column-filter row control.
 *                                  'auto' picks 'number' for numeric columns and
 *                                  'date' (a from→to range picker) for date columns,
 *                                  else 'select' when the column holds at most
 *                                  SELECT_FILTER_MAX distinct values, else 'text'.
 *   options: [{ value, label }]  — choices for the 'select' filter. Omit to derive
 *                                  them from the data (faceted unique values).
 *   optionLabel: (value) => str  — display label for a derived option
 *   allLabel: string             — label of the empty ("all") select option
 *   filterPlaceholder: string    — placeholder of the 'text' filter input
 *   numberFilterPlaceholder / numberFilterHint — placeholder and title of the
 *                                  'number' filter box (accepts 12, >12, <=5, 3-8)
 *   dateFilterPlaceholder / clearDateLabel / calendarProps — copy for the 'date'
 *                                  range picker and its calendar
 *   menuLabel: string            — label in the column-visibility menu
 *                                  (falls back to a string `header`, then id)
 */
import { useEffect, useMemo, useRef, useState } from 'react';

/** Floor for a `bodyMaxHeight="fill"` body — a short viewport still shows rows. */
const FILL_MIN_HEIGHT = 240;
/** Breathing room left under the pager so it doesn't sit flush on the edge. */
const FILL_BOTTOM_GAP = 8;

/**
 * The scrollable box the table lives in, or null when the page itself scrolls.
 * `fill` measures against this rather than the viewport: they coincide in the
 * usual full-page layout, but a table inside a modal, a split pane or anything
 * with a footer below it would otherwise size itself to the wrong bottom edge.
 */
function scrollContainer(el) {
    for (let p = el?.parentElement; p; p = p.parentElement) {
        const overflowY = getComputedStyle(p).overflowY;
        if (overflowY === 'auto' || overflowY === 'scroll') return p;
    }
    return null;
}
import {
    flexRender,
    getCoreRowModel,
    getFacetedRowModel,
    getFacetedUniqueValues,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';

import { Checkbox } from '../Checkbox';
import { Icon } from '../Icon';
import { DatePicker } from '../DatePicker';
import { Dropdown } from '../Dropdown';

/**
 * The page numbers to draw: first, last, and a window around the current page,
 * with `null` standing in for an elision. Rendering one button per page turns a
 * 20 000-row list at 50/page into 400 buttons that wrap over the table.
 */
function pageWindow(pageCount, pageIndex, radius = 2) {
    const wanted = new Set([0, pageCount - 1]);
    for (let i = pageIndex - radius; i <= pageIndex + radius; i++) {
        if (i >= 0 && i < pageCount) wanted.add(i);
    }
    const pages = [...wanted].sort((a, b) => a - b);
    return pages.flatMap((p, i) => (i > 0 && p - pages[i - 1] > 1 ? [null, p] : [p]));
}

/** Above this many distinct values an `'auto'` filter degrades to a text box. */
const SELECT_FILTER_MAX = 25;
/**
 * …and so does a column whose values are this long. Free-text columns
 * (descriptions, notes) are often few enough to enumerate, but a dropdown of
 * sentences is unreadable and unsearchable — those want a search box.
 */
const SELECT_FILTER_MAX_LEN = 28;

/**
 * The shared Checkbox at the 17px size the table uses. The wrapper swallows the
 * click so ticking a row's box never also fires `onRowClick`.
 */
function Check({ on, mixed = false, onToggle, label }) {
    return (
        // `flex`, not `inline-flex`: an inline box sits on the text baseline, and
        // an empty checkbox baselines 2px differently from one holding a ✓/–, which
        // silently changed the row height as you selected. Block-level opts out.
        <span className="flex" onClick={(e) => e.stopPropagation()}>
            <Checkbox size="sm" checked={on} indeterminate={mixed} aria-label={label} onChange={onToggle} />
        </span>
    );
}

// The min-width keeps a control legible in a narrow column: without it the field
// collapses to the column's content width and the option text is clipped to a
// couple of characters ("Wszystk▾"). It widens the column instead, which the
// table's auto-layout absorbs.
const FILTER_FIELD_CLS =
    'w-full min-w-[84px] rounded-[6px] border border-om-line bg-om-bg font-mono text-[10.5px] text-om-ink outline-none';

/** True for the `{ eq }` wrapper ColumnFilter uses to request an exact match. */
const isExact = (v) => !!v && typeof v === 'object' && 'eq' in v;
/** True for the `{ num }` wrapper the numeric filter uses. */
const isNumeric = (v) => !!v && typeof v === 'object' && 'num' in v;
/** True for the `{ from, to }` wrapper the date filter uses. */
const isDateRange = (v) => !!v && typeof v === 'object' && ('from' in v || 'to' in v);

/**
 * Which control an `'auto'` column's filter becomes, from the column's own values.
 *
 * Numbers get a comparison box (">10", "3-8") — a dropdown of every distinct
 * quantity is useless. A small, closed value set reads better as a picker, and
 * anything wider (order numbers, names, free text) stays a search box. `values`
 * is the unsorted distinct set, or null when the caller supplied `options`.
 */
function autoFilterKind(meta, values, labelOf) {
    if (meta.options) return 'select';
    // A dropdown needs something to enumerate — with no values yet (an entirely
    // empty column) it would be a dead control, so start as a search box that
    // works once data arrives.
    if (!values?.length) return 'text';
    if (values.every((v) => typeof v === 'number')) return 'number';
    if (values.every((v) => isoDay(v))) return 'date';
    if (
        values.length <= SELECT_FILTER_MAX &&
        values.every((v) => String(labelOf(v)).length <= SELECT_FILTER_MAX_LEN)
    ) {
        return 'select';
    }
    return 'text';
}

/**
 * The ISO day of a cell value, or '' when it holds no date.
 *
 * Row values arrive in whatever shape the API sent — '2026-07-13',
 * '2026-07-13T09:30:00Z', or a Date — and ISO days compare correctly as plain
 * strings, so everything is normalised to the leading YYYY-MM-DD.
 */
const isoDay = (v) => {
    if (!v) return '';
    if (v instanceof Date) {
        const p = (n) => String(n).padStart(2, '0');
        return `${v.getFullYear()}-${p(v.getMonth() + 1)}-${p(v.getDate())}`;
    }
    const m = String(v).match(/^\d{4}-\d{2}-\d{2}/);
    return m ? m[0] : '';
};

/**
 * Parse what someone typed into a numeric filter box into a predicate.
 *
 * Accepts `12`, `>12`, `>=12`, `<12`, `<=12` and the inclusive range `3-8`
 * (also `3..8`). Returns null for anything unparsable — including the
 * half-typed `>` on the way to `>12` — so the table simply stays unfiltered
 * rather than blanking out mid-keystroke.
 */
function parseNumericFilter(input) {
    const text = String(input).trim().replace(/\s+/g, '');
    if (!text) return null;

    const range = text.match(/^(-?\d+(?:[.,]\d+)?)(?:\.\.|-)(-?\d+(?:[.,]\d+)?)$/);
    if (range) {
        const lo = Number(range[1].replace(',', '.'));
        const hi = Number(range[2].replace(',', '.'));
        if (Number.isNaN(lo) || Number.isNaN(hi)) return null;
        return (n) => n >= Math.min(lo, hi) && n <= Math.max(lo, hi);
    }

    const cmp = text.match(/^(>=|<=|>|<|=)?(-?\d+(?:[.,]\d+)?)$/);
    if (!cmp) return null;
    const target = Number(cmp[2].replace(',', '.'));
    if (Number.isNaN(target)) return null;
    switch (cmp[1]) {
        case '>':
            return (n) => n > target;
        case '>=':
            return (n) => n >= target;
        case '<':
            return (n) => n < target;
        case '<=':
            return (n) => n <= target;
        default:
            return (n) => n === target;
    }
}

/** Shared filterFn for every column the filter row drives. See `cols` below. */
const filterRowFn = (row, id, value) => {
    const raw = row.getValue(id);

    if (isDateRange(value)) {
        const day = isoDay(raw);
        if (!day) return false; // a row with no date is in no range
        return (!value.from || day >= value.from) && (!value.to || day <= value.to);
    }

    if (isNumeric(value)) {
        const test = parseNumericFilter(value.num);
        if (!test) return true; // unparsable / half-typed → don't filter
        // A row with no value is not a zero — without this, `<=5` returns every
        // un-measured row alongside the genuinely small ones.
        if (raw === null || raw === undefined || raw === '') return false;
        const n = typeof raw === 'number' ? raw : Number(String(raw).replace(',', '.'));
        return !Number.isNaN(n) && test(n);
    }

    const cell = String(raw ?? '');
    return isExact(value)
        ? cell === String(value.eq)
        : cell.toLowerCase().includes(String(value).toLowerCase());
};

/**
 * One cell of the filter row. Resolves `meta.filter: 'auto'` against the column's
 * faceted values and, for a select without explicit `options`, derives the choice
 * list from the data — so a page opts a column into filtering with a single flag
 * instead of hand-maintaining an option list that drifts from the rows.
 */
function ColumnFilter({ col }) {
    const meta = col.columnDef.meta ?? {};
    if (!meta.filter) return null; // e.g. the actions column — nothing to filter on
    // No accessor means `row.getValue(id)` is always undefined, so any value
    // typed here would match no row and silently blank the list. Display-only
    // columns (a Restore button, a rendered badge with no backing field) hit
    // this now that filters default to on for every column.
    if (!col.accessorFn) return null;

    const raw = col.getFilterValue();
    const value = (isExact(raw) ? raw.eq : isNumeric(raw) ? raw.num : raw) ?? '';

    // Faceted values reflect the rows currently in the table, so options for a
    // live-synced list stay in step with the data without any page-side wiring.
    // Only ask for them when they'd actually be used: faceting a column with no
    // accessorFn throws inside TanStack, and it's wasted work for a text box.
    // No memo needed — getFacetedUniqueValues is itself memoized by the table.
    // The raw distinct values — unsorted and unlabelled. Deciding the control
    // only needs to *inspect* them; collating them into a labelled, sorted
    // option list is deferred to the `select` branch below, because a
    // high-cardinality column (2000 distinct order numbers) would otherwise pay
    // a full localeCompare sort on every render only to fall through to a text
    // box. Filters default to 'auto' on every column, so that is the hot path.
    const values =
        meta.options || !col.accessorFn || (meta.filter !== 'select' && meta.filter !== 'auto')
            ? null
            : [...col.getFacetedUniqueValues().keys()].filter((v) => v !== null && v !== undefined && v !== '');

    const labelOf = (v) => (meta.optionLabel ? meta.optionLabel(v) : String(v));
    const kind = meta.filter === 'auto' ? autoFilterKind(meta, values, labelOf) : meta.filter;

    if (kind === 'select') {
        // `optionLabel` applies to a bare-value list too — that's the common case
        // for an enum column: `options: WO_STATUSES, optionLabel: woStatusLabel`.
        // Values are normalised to strings: Dropdown matches its selection with
        // ===, and a derived option list can hold numbers while the stored filter
        // value is a string.
        // Only now is the collated list worth building — see `values` above.
        const derived = values
            ?.slice()
            .sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true }))
            .map((v) => ({ value: v, label: labelOf(v) }));
        const options = (meta.options ?? derived ?? []).map((opt) => {
            const o =
                typeof opt === 'object'
                    ? opt
                    : { value: opt, label: meta.optionLabel ? meta.optionLabel(opt) : opt };
            return { value: String(o.value), label: o.label };
        });
        // The shared Dropdown, not a native <select> — same control (and same
        // keyboard/hover behaviour) the rest of the app uses, in its compact size.
        return (
            <Dropdown
                size="sm"
                aria-label={meta.menuLabel}
                options={[{ value: '', label: meta.allLabel ?? '' }, ...options]}
                value={String(value)}
                onChange={(v) => col.setFilterValue(v === '' ? undefined : { eq: v })}
            />
        );
    }

    if (kind === 'date') {
        const current = isDateRange(raw) ? raw : {};
        const set = (next) =>
            col.setFilterValue(next?.from || next?.to ? { from: next.from, to: next.to } : undefined);
        return (
            <DatePicker
                size="sm"
                range
                value={current}
                onChange={set}
                placeholder={meta.dateFilterPlaceholder}
                aria-label={meta.menuLabel}
                calendarProps={meta.calendarProps}
                footer={
                    <button
                        type="button"
                        onClick={() => col.setFilterValue(undefined)}
                        className="text-[12.5px] font-semibold text-om-accent hover:opacity-70"
                    >
                        {meta.clearDateLabel}
                    </button>
                }
            />
        );
    }

    if (kind === 'number') {
        return (
            <input
                value={value}
                inputMode="numeric"
                onChange={(e) => col.setFilterValue(e.target.value ? { num: e.target.value } : undefined)}
                placeholder={meta.numberFilterPlaceholder}
                title={meta.numberFilterHint}
                aria-label={meta.menuLabel}
                className={`${FILTER_FIELD_CLS} px-2 py-[5px] text-center`}
            />
        );
    }

    if (kind === 'text') {
        return (
            <input
                value={value}
                onChange={(e) => col.setFilterValue(e.target.value || undefined)}
                placeholder={meta.filterPlaceholder}
                aria-label={meta.menuLabel}
                className={`${FILTER_FIELD_CLS} px-2 py-[5px]`}
            />
        );
    }

    return null;
}

/** 1px × 13px drag bar in a 9px hit area at the header-cell edge. */
function ResizeHandle({ header, side }) {
    const handler = header.getResizeHandler();
    return (
        <span
            onMouseDown={handler}
            onTouchStart={handler}
            onClick={(e) => e.stopPropagation()}
            className={`absolute top-0 flex h-full w-[9px] cursor-col-resize items-center ${
                side === 'left' ? 'left-0 justify-start' : 'right-0 justify-end'
            }`}
        >
            <span className="h-[13px] w-px bg-om-line2" />
        </span>
    );
}

export function DataTable({
    data,
    columns,
    searchPlaceholder = '',
    enableSelection = false,
    bulkActions,
    /** The word beside the count badge, e.g. "selected". */
    selectedLabel,
    /** Row identity for selection — keep it stable or live updates reshuffle it. */
    getRowId,
    selectAllLabel,
    selectRowLabel,
    clearSelectionLabel,
    columnsLabel,
    columnsMenuLabel,
    /** Labels for the filter-row chrome (all optional). */
    filtersLabel,
    clearFiltersLabel,
    emptyLabel = '',
    rangeLabel = (start, end, total) => (total === 0 ? '0' : `${start}–${end} / ${total}`),
    pageSize = 6,
    /** Caps the scroll body (sticky header). Default: uncapped — the table grows
     *  with its rows and the page scrolls (pagination keeps the count sane).
     *  Pass `"fill"` to measure the space left below the body and grow into it,
     *  so the table ends at the bottom of the viewport instead of leaving dead
     *  space. Any other value is used verbatim as a CSS max-height. */
    bodyMaxHeight,
    onRowClick,
    /** Double-click a row — used for "open this record's detail page". */
    onRowDoubleClick,
    /** Toolbar/footer feature toggles — turn chrome off for plain styled tables. */
    searchable = true,
    columnToggle = true,
    /** Nodes placed at the start / end of the toolbar row — e.g. a filter summary
     *  on the left, a "new record" button on the right. */
    toolbarStart = null,
    toolbarEnd = null,
    paginated = true,
    /** Fit columns to the container (default). `false` = fixed-width, resizable (§12 demo). */
    fluid = true,
    /** Alternating row tint, so the eye tracks a row across a wide table. */
    striped = true,
    className = '',
    ...props
}) {
    // When pagination is off, show every row on a single page.
    const effectivePageSize = paginated ? pageSize : Number.MAX_SAFE_INTEGER;


    // Filter semantics follow the control the filter row actually rendered, which
    // only ColumnFilter knows (an 'auto' column resolves to either). It encodes
    // that in the value: an `{ eq }` wrapper means "match the whole value",
    // a bare string means substring. Exact matches compare as strings because a
    // <select> hands back a string even when the column value is a number.
    const cols = useMemo(
        () =>
            columns.map((c) =>
                !c.meta?.filter || c.filterFn ? c : { ...c, filterFn: filterRowFn },
            ),
        [columns],
    );

    const table = useReactTable({
        data,
        columns: cols,
        initialState: { pagination: { pageIndex: 0, pageSize: effectivePageSize } },
        enableRowSelection: !!enableSelection,
        enableMultiSort: true,
        sortDescFirst: false, // toggle cycle: asc → desc → off
        globalFilterFn: 'includesString',
        // Every Reverb delta is a new `data` array, and the default auto-reset
        // would bounce whoever is reading page 3 back to page 1. Paging is reset
        // deliberately below instead — on filter/search change, as the design does.
        autoResetPageIndex: false,
        // Without this, a row's identity is its index — live-synced rows would
        // hand a checked box to whatever row slid into that slot.
        getRowId,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        // Powers select-filter options derived from the data.
        getFacetedRowModel: getFacetedRowModel(),
        getFacetedUniqueValues: getFacetedUniqueValues(),
    });

    const state = table.getState();
    const visibleCols = table.getVisibleLeafColumns();
    const hideableColumns = table.getAllLeafColumns().filter((col) => col.getCanHide());

    // Numeric columns centre themselves. Ragged-width numbers read as noise when
    // flushed left, and the table already knows which columns hold numbers — it
    // uses the same faceted values to pick the filter control. An explicit
    // `meta.align` always wins.
    // Memoised: this walks every column's distinct values, so re-running it on
    // unrelated renders (pagination, selection, a parent re-render) is pure waste.
    const alignByColumn = useMemo(() => Object.fromEntries(
        visibleCols.map((col) => {
            const explicit = col.columnDef.meta?.align;
            if (explicit) return [col.id, explicit];
            // Faceting a column with no accessorFn throws inside TanStack.
            if (!col.accessorFn) return [col.id, 'left'];
            const values = [...col.getFacetedUniqueValues().keys()].filter(
                (v) => v !== null && v !== undefined && v !== '',
            );
            const numeric = values.length > 0 && values.every((v) => typeof v === 'number');
            return [col.id, numeric ? 'center' : 'left'];
        }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    ), [visibleCols, data, state.columnFilters, state.globalFilter]);
    const alignClass = (id) =>
        alignByColumn[id] === 'right' ? 'text-right' : alignByColumn[id] === 'center' ? 'text-center' : 'text-left';

    const pageRows = table.getRowModel().rows;
    const total = table.getFilteredRowModel().rows.length;
    const pageIndex = state.pagination.pageIndex;
    const pageCount = Math.max(1, table.getPageCount()); // prototype always shows page "1"
    const rangeStart = total === 0 ? 0 : pageIndex * state.pagination.pageSize + 1;
    const rangeEnd = pageIndex * state.pagination.pageSize + pageRows.length;

    const selectedRows = table.getSelectedRowModel().flatRows.map((r) => r.original);
    const selCount = selectedRows.length;
    const clearSelection = () => table.resetRowSelection();

    const hasFilterRow = visibleCols.some((col) => col.columnDef.meta?.filter);
    const activeFilters = state.columnFilters.length;

    // Narrowing the list means starting over at page 1 — otherwise the result
    // set shrinks under a page index that no longer points at anything. The
    // state slices are immutable, so identity comparison is enough; serialising
    // them would re-stringify the whole query on every keystroke.
    const mounted = useRef(false);
    useEffect(() => {
        if (mounted.current) table.setPageIndex(0);
        mounted.current = true;
    }, [state.columnFilters, state.globalFilter, table]);

    // Rows can also vanish underneath us (a delete arrives, someone else filters):
    // clamp rather than stranding the reader on a blank page.
    useEffect(() => {
        if (pageIndex > 0 && pageIndex >= pageCount) table.setPageIndex(pageCount - 1);
    }, [pageIndex, pageCount, table]);
    const pagerBtnCls =
        'flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[6px]';

    // `bodyMaxHeight="fill"`: grow the scroll body into whatever vertical space is
    // left below it, so the table ends at the bottom of the viewport instead of
    // stopping short. Measured rather than a `calc(100vh - Xpx)` guess, because
    // what sits above the body varies per page (subtitle, filter row, toolbar)
    // and a fixed constant is wrong on most of them.
    const rootRef = useRef(null);
    const bodyRef = useRef(null);
    const [fillHeight, setFillHeight] = useState(null);
    const autoFill = bodyMaxHeight === 'fill';

    useEffect(() => {
        if (!autoFill) return undefined;
        const body = bodyRef.current;
        const root = rootRef.current;
        if (!body || !root) return undefined;

        const measure = () => {
            const bodyRect = body.getBoundingClientRect();
            // Chrome below the body (pager + gaps). Constant whatever the body's
            // own height is, so feeding it back in doesn't loop.
            const below = root.getBoundingClientRect().bottom - bodyRect.bottom;
            // Stop at the container's CONTENT edge, not its border box: sizing
            // into its bottom padding pushes the page past its own scroll height
            // and raises a second scrollbar next to the table's own.
            const container = scrollContainer(root);
            let floorY = window.innerHeight;
            let gap = FILL_BOTTOM_GAP;
            if (container) {
                const padBottom = parseFloat(getComputedStyle(container).paddingBottom) || 0;
                floorY = container.getBoundingClientRect().bottom - padBottom;
                // That padding already is the breathing room the gap provides.
                gap = Math.max(0, FILL_BOTTOM_GAP - padBottom);
            }
            const available = floorY - bodyRect.top - below - gap;
            // Not enough room to be worth capping — the table starts at or below
            // the fold (a report page with charts stacked above it, or a very
            // short viewport). Capping there would nest a scrollbar inside a
            // page that has to scroll anyway, so let the table grow instead.
            setFillHeight(available < FILL_MIN_HEIGHT ? null : Math.round(available));
        };

        // Both sources fire for the same resize, and the observer watches a box
        // that `measure` itself resizes — so coalesce into one frame instead of
        // measuring two or three times per tick. Also keeps the setState out of
        // the ResizeObserver callback, the usual source of its "loop completed
        // with undelivered notifications" warning.
        let frame = null;
        const schedule = () => {
            if (frame !== null) return;
            frame = requestAnimationFrame(() => {
                frame = null;
                measure();
            });
        };

        measure();
        window.addEventListener('resize', schedule);
        const observer = new ResizeObserver(schedule);
        observer.observe(root);
        return () => {
            if (frame !== null) cancelAnimationFrame(frame);
            window.removeEventListener('resize', schedule);
            observer.disconnect();
        };
    }, [autoFill]);

    return (
        <div ref={rootRef} className={className} {...props}>
            {/* toolbar */}
            {/* Boxed like the table below it, so the controls read as one bar
                rather than floating on the page background. */}
            {(searchable || columnToggle || enableSelection || toolbarStart || toolbarEnd) && (
            <div className="flex items-center gap-3 border border-b-0 border-om-line bg-om-card px-3 py-1.5">
                {toolbarStart}
                {searchable && (
                <div className="flex max-w-[300px] flex-1 items-center gap-[9px] rounded-om-sm border border-om-line bg-om-bg px-3 py-2">
                    <Icon name="search" size={14} className="shrink-0 text-om-faint" />
                    <input
                        value={state.globalFilter ?? ''}
                        onChange={(e) => table.setGlobalFilter(e.target.value)}
                        placeholder={searchPlaceholder}
                        className="min-w-0 flex-1 border-none bg-transparent text-[13px] text-om-ink outline-none"
                    />
                </div>
                )}
                {columnToggle && (
                <Dropdown
                    multiple
                    header={columnsMenuLabel}
                    label={columnsLabel}
                    leftIcon={<Icon name="columns-3" size={14} />}
                    triggerClassName="py-2! font-semibold"
                    options={hideableColumns.map((col) => ({
                        value: col.id,
                        label:
                            col.columnDef.meta?.menuLabel ??
                            (typeof col.columnDef.header === 'string' ? col.columnDef.header : col.id),
                    }))}
                    values={hideableColumns.filter((col) => col.getIsVisible()).map((col) => col.id)}
                    // Dropdown hands back the next set of checked ids; translate that
                    // into TanStack's visibility map in one update.
                    onChange={(next) =>
                        table.setColumnVisibility(
                            Object.fromEntries(hideableColumns.map((col) => [col.id, next.includes(col.id)])),
                        )
                    }
                />
                )}
                {/* The filter row scrolls out of sight on a long table, so surface
                    that filters are narrowing the list — and let them be dropped. */}
                {hasFilterRow && activeFilters > 0 && (
                    <div className="flex items-center gap-[7px] font-mono text-[11px] text-om-muted">
                        {filtersLabel && <span>{filtersLabel(activeFilters)}</span>}
                        {/* Same × glyph the selection bar clears with, so "drop this
                            state" reads the same wherever it appears in the toolbar. */}
                        <button
                            type="button"
                            aria-label={clearFiltersLabel}
                            title={clearFiltersLabel}
                            onClick={() => table.resetColumnFilters()}
                            className="inline-flex cursor-pointer items-center gap-[5px] rounded-[6px] px-[5px] py-[2px] text-om-accent hover:bg-om-chip"
                        >
                            <span aria-hidden="true" className="text-[14px] leading-none">×</span>
                            {clearFiltersLabel}
                        </button>
                    </div>
                )}
                {/* Selection action bar (design §12): panel-filled pill holding an
                    accent count badge, a hairline divider, the bulk chips, and a ×
                    that clears the selection. */}
                {enableSelection && selCount > 0 && (
                    <div className="ml-auto flex h-[42px] items-center gap-[10px] rounded-om-sm border border-om-line bg-om-panel pr-[6px] pl-3">
                        <span className="inline-flex items-center gap-[7px]">
                            <span className="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-om-accent px-[5px] font-mono text-[10px] font-semibold text-white">
                                {selCount}
                            </span>
                            {selectedLabel && (
                                <span className="font-mono text-[11px] text-om-muted">{selectedLabel}</span>
                            )}
                        </span>
                        {bulkActions && <span className="h-5 w-px bg-om-line2" />}
                        {bulkActions && bulkActions(selectedRows, clearSelection)}
                        {clearSelectionLabel && (
                            <button
                                type="button"
                                aria-label={clearSelectionLabel}
                                title={clearSelectionLabel}
                                onClick={clearSelection}
                                className="flex size-7 cursor-pointer items-center justify-center rounded-[6px] text-[15px] leading-none text-om-muted hover:bg-om-chip hover:text-om-ink"
                            >
                                ×
                            </button>
                        )}
                    </div>
                )}
                {/* Right-aligned tail: the first `ml-auto` absorbs the free space,
                    so the selection pill and this slot sit together on the right. */}
                {toolbarEnd && <div className="ml-auto flex shrink-0 items-center gap-2">{toolbarEnd}</div>}
            </div>
            )}

            {/* table */}
            <div className="overflow-hidden border border-om-line">
                <div
                    ref={bodyRef}
                    className="overflow-auto"
                    style={{ maxHeight: autoFill ? fillHeight ?? undefined : bodyMaxHeight }}
                >
                    {/* Real <table> — browser auto-layout distributes column widths to
                        their content and fills the width, no manual sizing/ballooning.

                        Separate borders, not collapsed: `border-collapse: collapse`
                        paints borders and row backgrounds at the table level, which
                        forces the whole table onto the main-thread paint path. The
                        sticky header then lags compositor-driven scrolling and the
                        rows underneath tear through it. Separate borders let the
                        header composite on its own. Cells carry the borders, since
                        the separated model ignores borders set on `tr`. */}
                    <table className="w-full border-separate border-spacing-0 text-[13.5px]">
                        {/* The background lives on the `th`s, not the `tr`. Under
                            `border-collapse: collapse` a sticky thead paints its cells
                            in the sticky layer while the row background stays behind in
                            the row group — transparent cells then let the scrolled rows
                            show through the header. The `tr` colour is kept as the
                            non-sticky fallback. */}
                        <thead className="sticky top-0 z-[3]">
                            <tr className="bg-om-panel">
                                {enableSelection && (
                                    <th className="w-[38px] border-b border-r border-om-line2 bg-om-bg px-4 py-[10px] text-left align-middle last:border-r-0">
                                        <Check
                                            on={table.getIsAllPageRowsSelected()}
                                            mixed={table.getIsSomePageRowsSelected()}
                                            label={selectAllLabel}
                                            onToggle={() => table.toggleAllPageRowsSelected()}
                                        />
                                    </th>
                                )}
                                {table.getHeaderGroups().map((hg) =>
                                    hg.headers.map((header) => {
                                        const col = header.column;
                                        const align = alignByColumn[col.id] ?? 'left';
                                        const sorted = col.getIsSorted(); // 'asc' | 'desc' | false
                                        const orderBadge =
                                            sorted && state.sorting.length > 1
                                                ? String(col.getSortIndex() + 1)
                                                : '';
                                        return (
                                            <th
                                                key={header.id}
                                                onClick={
                                                    col.getCanSort()
                                                        ? col.getToggleSortingHandler()
                                                        : undefined
                                                }
                                                aria-sort={
                                                    sorted
                                                        ? sorted === 'asc'
                                                            ? 'ascending'
                                                            : 'descending'
                                                        : undefined
                                                }
                                                className={`whitespace-nowrap border-b border-r border-om-line2 last:border-r-0 bg-om-bg px-4 py-[10px] font-mono text-[11px] font-bold tracking-[0.08em] uppercase select-none ${
                                                    sorted ? 'text-om-ink' : 'text-om-muted'
                                                } ${col.getCanSort() ? 'cursor-pointer' : ''} ${
                                                    align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left'
                                                }`}
                                            >
                                                {flexRender(col.columnDef.header, header.getContext())}
                                                {sorted && (
                                                    <>
                                                        <Icon
                                                            name={sorted === 'asc' ? 'chevron-up' : 'chevron-down'}
                                                            size={12}
                                                            className="ml-1 inline-block shrink-0 align-middle"
                                                        />
                                                        {orderBadge}
                                                    </>
                                                )}
                                            </th>
                                        );
                                    }),
                                )}
                            </tr>
                            {hasFilterRow && (
                                <tr className="bg-om-card">
                                    {enableSelection && <th className="border-b border-r border-om-line2 bg-om-card last:border-r-0" />}
                                    {visibleCols.map((col) => (
                                        <th
                                            key={col.id}
                                            className={`border-b border-r border-om-line2 last:border-r-0 bg-om-card px-4 py-2 align-middle font-normal ${alignClass(col.id)}`}
                                        >
                                            <ColumnFilter col={col} />
                                        </th>
                                    ))}
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {pageRows.map((row, i) => (
                                <tr
                                    key={row.id}
                                    onClick={
                                        onRowClick ? () => onRowClick(row.original, row) : undefined
                                    }
                                    onDoubleClick={
                                        onRowDoubleClick
                                            ? (e) => {
                                                  // Ignore double-clicks that land on a
                                                  // control — a checkbox, row action or
                                                  // link has its own meaning.
                                                  if (e.target.closest('button, a, input, select, textarea, [role="checkbox"]')) return;
                                                  // Otherwise the browser leaves the
                                                  // double-clicked word selected behind
                                                  // the page we're about to open.
                                                  window.getSelection?.()?.removeAllRanges();
                                                  onRowDoubleClick(row.original, row);
                                              }
                                            : undefined
                                    }
                                    // Four surfaces, ordered so each state stays legible
                                    // on top of the one below it: card/bg alternate as
                                    // the zebra stripe (panel was too faint to read as
                                    // striping at all), hover (chip) is a step past
                                    // both stripes, and selection is a different hue
                                    // entirely rather than another grey.
                                    // Row separator lives on the cells — `tr` borders
                                    // are ignored in the separated-borders model.
                                    className={`[&>td]:border-b [&>td]:border-r [&>td]:border-om-line2 [&>td:last-child]:border-r-0 last:[&>td]:border-b-0 transition-colors duration-100 ${
                                        row.getIsSelected()
                                            ? 'bg-om-selected'
                                            : `${striped && i % 2 === 1 ? 'bg-om-bg' : 'bg-om-card'} hover:bg-om-chip`
                                    } ${onRowClick ? 'cursor-pointer' : ''}`}
                                >
                                    {enableSelection && (
                                        <td className="px-4 py-3 align-middle">
                                            <Check
                                                on={row.getIsSelected()}
                                                label={selectRowLabel}
                                                onToggle={() => row.toggleSelected()}
                                            />
                                        </td>
                                    )}
                                    {row.getVisibleCells().map((cell) => (
                                        <td
                                            key={cell.id}
                                            className={`px-4 py-3 align-middle ${alignClass(cell.column.id)}`}
                                        >
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                            {total === 0 && (
                                <tr>
                                    <td
                                        colSpan={visibleCols.length + (enableSelection ? 1 : 0)}
                                        className="p-[34px] text-center text-[13.5px] text-om-faint"
                                    >
                                        {emptyLabel}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {/* footer / pagination */}
                {paginated && (
                <div className="flex items-center justify-between gap-3 bg-om-panel px-4 py-[11px]">
                    <span className="font-mono text-[10.5px] text-om-faint">
                        {rangeLabel(rangeStart, rangeEnd, total)}
                    </span>
                    <div className="flex items-center gap-[6px]">
                        <span
                            onClick={() => table.previousPage()}
                            className={`${pagerBtnCls} border border-om-line text-[14px] ${
                                table.getCanPreviousPage() ? 'text-om-muted' : 'text-om-faintest'
                            }`}
                        >
                            <Icon name="chevron-left" size={14} />
                        </span>
                        {pageWindow(pageCount, pageIndex).map((i, idx) => (
                            i === null ? (
                                <span key={`gap-${idx}`} className="px-1 font-mono text-[11px] text-om-faint">…</span>
                            ) : (
                            <span
                                key={i}
                                onClick={() => table.setPageIndex(i)}
                                className={`${pagerBtnCls} font-mono text-[11px] ${
                                    i === pageIndex
                                        ? 'bg-om-ink text-om-on-ink'
                                        : 'border border-om-line text-om-muted'
                                }`}
                            >
                                {i + 1}
                            </span>
                            )
                        ))}
                        <span
                            onClick={() => table.nextPage()}
                            className={`${pagerBtnCls} border border-om-line text-[14px] ${
                                table.getCanNextPage() ? 'text-om-muted' : 'text-om-faintest'
                            }`}
                        >
                            <Icon name="chevron-right" size={14} />
                        </span>
                    </div>
                </div>
                )}
            </div>
        </div>
    );
}
