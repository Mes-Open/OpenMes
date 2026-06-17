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
 * glyphs (‹ › ▾ ✓ ↑ ↓ –) are baked in.
 *
 * Feature toggles (default on): `searchable` (global search box), `columnToggle`
 * (column-visibility menu), `paginated` (pager footer + paging). Turn them off to
 * use DataTable as a plain styled, sortable table for short/detail lists, e.g.
 * `<DataTable searchable={false} columnToggle={false} paginated={false} … />`.
 *
 * Column def extras read from `meta`:
 *   align: 'left' | 'right'      — header/cell alignment, resize-handle side
 *   flex: true                   — column takes `minmax(140px, 1fr)` until resized
 *   filter: 'text' | 'select'    — renders the column-filter row control
 *   options: [{ value, label }]  — choices for the 'select' filter
 *   allLabel: string             — label of the empty ("all") select option
 *   filterPlaceholder: string    — placeholder of the 'text' filter input
 *   menuLabel: string            — label in the column-visibility menu
 *                                  (falls back to a string `header`, then id)
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';

/** 17px radius-5 checkbox — accent bg + white ✓ on, 2px faintest border off. */
function Check({ on, onClick }) {
    return (
        <span
            role="checkbox"
            aria-checked={on}
            onClick={onClick}
            className={`flex size-[17px] shrink-0 cursor-pointer items-center justify-center rounded-[5px] ${
                on ? 'bg-om-accent' : 'border-2 border-om-faintest'
            }`}
        >
            {on && <span className="text-[10px] leading-none font-bold text-white">✓</span>}
        </span>
    );
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
    selectionLabel,
    columnsLabel,
    columnsMenuLabel,
    emptyLabel = '',
    rangeLabel = (start, end, total) => (total === 0 ? '0' : `${start}–${end} / ${total}`),
    pageSize = 6,
    /** Caps the scroll body (sticky header). Default: uncapped — the table grows
     *  with its rows and the page scrolls (pagination keeps the count sane). */
    bodyMaxHeight,
    onRowClick,
    /** Toolbar/footer feature toggles — turn chrome off for plain styled tables. */
    searchable = true,
    columnToggle = true,
    paginated = true,
    /** Fit columns to the container (default). `false` = fixed-width, resizable (§12 demo). */
    fluid = true,
    className = '',
    ...props
}) {
    // When pagination is off, show every row on a single page.
    const effectivePageSize = paginated ? pageSize : Number.MAX_SAFE_INTEGER;
    const [colsMenu, setColsMenu] = useState(false);
    const menuRef = useRef(null);

    // Close the columns menu on outside click.
    useEffect(() => {
        if (!colsMenu) return;
        const close = (e) => {
            if (menuRef.current && !menuRef.current.contains(e.target)) setColsMenu(false);
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [colsMenu]);

    // 'select' column filters match exactly unless the caller picked a filterFn.
    const cols = useMemo(
        () =>
            columns.map((c) =>
                c.meta?.filter === 'select' && !c.filterFn ? { ...c, filterFn: 'equals' } : c,
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
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
    });

    const state = table.getState();
    const visibleCols = table.getVisibleLeafColumns();

    const pageRows = table.getRowModel().rows;
    const total = table.getFilteredRowModel().rows.length;
    const pageIndex = state.pagination.pageIndex;
    const pageCount = Math.max(1, table.getPageCount()); // prototype always shows page "1"
    const rangeStart = total === 0 ? 0 : pageIndex * state.pagination.pageSize + 1;
    const rangeEnd = pageIndex * state.pagination.pageSize + pageRows.length;

    const selectedRows = table.getSelectedRowModel().flatRows.map((r) => r.original);
    const selCount = selectedRows.length;
    const totalRows = table.getPreFilteredRowModel().rows.length;
    const clearSelection = () => table.resetRowSelection();

    const hasFilterRow = visibleCols.some((col) => col.columnDef.meta?.filter);
    const filterFieldCls =
        'w-full rounded-[6px] border border-om-line bg-om-bg font-mono text-[10.5px] text-om-ink outline-none';
    const pagerBtnCls =
        'flex h-[26px] min-w-[26px] cursor-pointer items-center justify-center rounded-[6px]';

    return (
        <div className={className} {...props}>
            {/* toolbar */}
            {(searchable || columnToggle || enableSelection) && (
            <div className="mb-3 flex items-center gap-3">
                {searchable && (
                <div className="flex max-w-[300px] flex-1 items-center gap-[9px] rounded-om-sm border border-om-line bg-om-bg px-3 py-2">
                    <span className="size-[13px] shrink-0 rounded-full border-2 border-om-faint" />
                    <input
                        value={state.globalFilter ?? ''}
                        onChange={(e) => table.setGlobalFilter(e.target.value)}
                        placeholder={searchPlaceholder}
                        className="min-w-0 flex-1 border-none bg-transparent text-[13px] text-om-ink outline-none"
                    />
                </div>
                )}
                {columnToggle && (
                <div ref={menuRef} className="relative">
                    <button
                        type="button"
                        onClick={() => setColsMenu((v) => !v)}
                        className="inline-flex cursor-pointer items-center gap-[7px] rounded-om-sm border border-om-line bg-om-card px-[13px] py-2 text-[12.5px] font-semibold text-om-ink"
                    >
                        {columnsLabel} ▾
                    </button>
                    {colsMenu && (
                        <div className="absolute top-[42px] left-0 z-20 w-[178px] rounded-om border border-om-line bg-om-card p-[6px] shadow-[0_18px_44px_-18px_rgba(0,0,0,0.3)]">
                            {columnsMenuLabel && (
                                <div className="px-[9px] pt-[7px] pb-[6px] font-mono text-[9px] tracking-[0.1em] text-om-faint uppercase">
                                    {columnsMenuLabel}
                                </div>
                            )}
                            {table
                                .getAllLeafColumns()
                                .filter((col) => col.getCanHide())
                                .map((col) => (
                                    <div
                                        key={col.id}
                                        onClick={() => col.toggleVisibility()}
                                        className="flex cursor-pointer items-center gap-[10px] rounded-[6px] px-[9px] py-2"
                                    >
                                        <Check on={col.getIsVisible()} />
                                        <span className="text-[13px] text-om-ink">
                                            {col.columnDef.meta?.menuLabel ??
                                                (typeof col.columnDef.header === 'string'
                                                    ? col.columnDef.header
                                                    : col.id)}
                                        </span>
                                    </div>
                                ))}
                        </div>
                    )}
                </div>
                )}
                {enableSelection && selCount > 0 && (
                    <div className="ml-auto flex items-center gap-[10px]">
                        {selectionLabel && (
                            <span className="font-mono text-[11px] text-om-muted">
                                {selectionLabel(selCount, totalRows)}
                            </span>
                        )}
                        {bulkActions && bulkActions(selectedRows, clearSelection)}
                    </div>
                )}
            </div>
            )}

            {/* table */}
            <div className="overflow-hidden rounded-om border border-om-line">
                <div className="overflow-auto" style={{ maxHeight: bodyMaxHeight }}>
                    {/* Real <table> — browser auto-layout distributes column widths to
                        their content and fills the width, no manual sizing/ballooning. */}
                    <table className="w-full border-collapse text-[13.5px]">
                        <thead className="sticky top-0 z-[3]">
                            <tr className="bg-om-panel">
                                {enableSelection && (
                                    <th className="w-[38px] border-b border-om-line2 px-4 py-[10px] text-left align-middle">
                                        <Check
                                            on={table.getIsAllPageRowsSelected()}
                                            onClick={() => table.toggleAllPageRowsSelected()}
                                        />
                                    </th>
                                )}
                                {table.getHeaderGroups().map((hg) =>
                                    hg.headers.map((header) => {
                                        const col = header.column;
                                        const align = col.columnDef.meta?.align ?? 'left';
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
                                                className={`whitespace-nowrap border-b border-om-line2 px-4 py-[10px] font-mono text-[9px] font-medium tracking-[0.1em] uppercase select-none ${
                                                    sorted ? 'text-om-ink' : 'text-om-faint'
                                                } ${col.getCanSort() ? 'cursor-pointer' : ''} ${
                                                    align === 'right' ? 'text-right' : 'text-left'
                                                }`}
                                            >
                                                {flexRender(col.columnDef.header, header.getContext())}
                                                {sorted && ` ${sorted === 'asc' ? '↑' : '↓'}${orderBadge}`}
                                            </th>
                                        );
                                    }),
                                )}
                            </tr>
                            {hasFilterRow && (
                                <tr className="bg-om-card">
                                    {enableSelection && <th className="border-b border-om-line2" />}
                                    {visibleCols.map((col) => {
                                        const meta = col.columnDef.meta ?? {};
                                        return (
                                            <th
                                                key={col.id}
                                                className="border-b border-om-line2 px-4 py-2 align-middle font-normal"
                                            >
                                                {meta.filter === 'text' && (
                                                    <input
                                                        value={col.getFilterValue() ?? ''}
                                                        onChange={(e) =>
                                                            col.setFilterValue(e.target.value || undefined)
                                                        }
                                                        placeholder={meta.filterPlaceholder}
                                                        className={`${filterFieldCls} px-2 py-[5px]`}
                                                    />
                                                )}
                                                {meta.filter === 'select' && (
                                                    <select
                                                        value={col.getFilterValue() ?? ''}
                                                        onChange={(e) =>
                                                            col.setFilterValue(e.target.value || undefined)
                                                        }
                                                        className={`${filterFieldCls} cursor-pointer px-[6px] py-[5px]`}
                                                    >
                                                        <option value="">{meta.allLabel ?? ''}</option>
                                                        {(meta.options ?? []).map((opt) => {
                                                            const o =
                                                                typeof opt === 'object'
                                                                    ? opt
                                                                    : { value: opt, label: opt };
                                                            return (
                                                                <option key={o.value} value={o.value}>
                                                                    {o.label}
                                                                </option>
                                                            );
                                                        })}
                                                    </select>
                                                )}
                                            </th>
                                        );
                                    })}
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {pageRows.map((row) => (
                                <tr
                                    key={row.id}
                                    onClick={
                                        onRowClick ? () => onRowClick(row.original, row) : undefined
                                    }
                                    className={`border-b border-om-line2 last:border-0 ${
                                        row.getIsSelected() ? 'bg-om-selected' : 'bg-om-card'
                                    } ${onRowClick ? 'cursor-pointer hover:bg-om-bg' : ''}`}
                                >
                                    {enableSelection && (
                                        <td className="px-4 py-3 align-middle">
                                            <Check
                                                on={row.getIsSelected()}
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    row.getToggleSelectedHandler()(e);
                                                }}
                                            />
                                        </td>
                                    )}
                                    {row.getVisibleCells().map((cell) => (
                                        <td
                                            key={cell.id}
                                            className={`px-4 py-3 align-middle ${
                                                cell.column.columnDef.meta?.align === 'right'
                                                    ? 'text-right'
                                                    : 'text-left'
                                            }`}
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
                            ‹
                        </span>
                        {Array.from({ length: pageCount }, (_, i) => (
                            <span
                                key={i}
                                onClick={() => table.setPageIndex(i)}
                                className={`${pagerBtnCls} font-mono text-[11px] ${
                                    i === pageIndex
                                        ? 'bg-om-ink text-white'
                                        : 'border border-om-line text-om-muted'
                                }`}
                            >
                                {i + 1}
                            </span>
                        ))}
                        <span
                            onClick={() => table.nextPage()}
                            className={`${pagerBtnCls} border border-om-line text-[14px] ${
                                table.getCanNextPage() ? 'text-om-muted' : 'text-om-faintest'
                            }`}
                        >
                            ›
                        </span>
                    </div>
                </div>
                )}
            </div>
        </div>
    );
}
