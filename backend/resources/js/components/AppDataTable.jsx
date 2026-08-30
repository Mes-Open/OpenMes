import { useMemo } from 'react';
import { DataTable } from '@openmes/ui/table';
import { tableLabels, withFilters } from '../lib/tableLabels';

/**
 * DataTable with this app's conventions already applied: translated chrome and
 * a filter control on every column.
 *
 * `@openmes/ui`'s DataTable is deliberately locale-free and defaults filters to
 * opt-in per column — correct for a design-system package, wrong as the thing a
 * page reaches for. Left to call sites, both had to be remembered every time,
 * and weren't: pages shipped English chrome inside a Polish UI and lists with no
 * filter row at all. This is the one place that decision lives now.
 *
 * Use this for any table fed from `usePage().props`. Lists backed by a synced
 * collection should still use `ResourceTable`, which builds on the same helpers.
 *
 *   bodyMaxHeight — defaults to "fill", the same as ResourceTable's lists: the
 *                rows grow into the space left below them so the pager stays on
 *                screen and the page itself doesn't scroll. Falls back to no cap
 *                when there isn't room to be worth it (see DataTable).
 *   filterable — defaults to on for a full-chrome table, off once the caller
 *                turns search off (DataTable's documented "plain styled table
 *                for short/detail lists" mode, where a filter row is noise).
 *                Pass explicitly to override either way.
 */
export default function AppDataTable({ columns, filterable, bodyMaxHeight = 'fill', ...props }) {
    const filtersOn = filterable ?? props.searchable !== false;

    // Memoised: the pages hand us a `useMemo`d column array, and mapping it
    // inline would hand DataTable a new identity every render and rebuild its
    // column defs — throwing away the memoisation the call site paid for.
    const cols = useMemo(() => (filtersOn ? withFilters(columns) : columns), [columns, filtersOn]);

    // Labels first so a page can still override any single one.
    return <DataTable {...tableLabels()} columns={cols} bodyMaxHeight={bodyMaxHeight} {...props} />;
}
