import { __ } from './i18n';

/**
 * Translated chrome for `@openmes/ui`'s DataTable.
 *
 * The design-system package deliberately has no i18n of its own — every visible
 * string arrives as a prop. This module owns that copy once, so `ResourceTable`
 * and the pages that render DataTable directly can't drift apart.
 *
 * Prefer `components/AppDataTable.jsx` over reaching for these directly: it
 * applies the chrome and the filter defaults in one place, so a new call site
 * can't forget half of it.
 *
 * Called (not a constant) because the active locale can change at runtime.
 */
export function tableLabels() {
    return {
        searchPlaceholder: __('Search…'),
        columnsLabel: __('Columns'),
        columnsMenuLabel: __('Toggle columns'),
        emptyLabel: __('Nothing here yet.'),
        rangeLabel: (start, end, total) => (total === 0 ? __('0 results') : `${start}–${end} / ${total}`),
        filtersLabel: (n) => __('Filters: :n', { n }),
        clearFiltersLabel: __('Clear filters'),
        selectAllLabel: __('Select all rows on this page'),
        selectRowLabel: __('Select row'),
        clearSelectionLabel: __('Clear'),
    };
}

/** Copy for the date filter's calendar popover. */
const calendarCopy = () => ({
    todayLabel: __('Today'),
    todayWord: __('today'),
    rangeLabel: __('Date range'),
    pickEndLabel: __('Pick an end date'),
});

/**
 * Per-column filter copy — the bits DataTable reads from a column's `meta`.
 * Identical for every column, so it's spread in rather than restated.
 */
export function filterLabels() {
    return {
        allLabel: __('All'),
        filterPlaceholder: __('Filter…'),
        numberFilterPlaceholder: __('>10'),
        numberFilterHint: __('Examples: 12, >10, <=5, 3-8'),
        dateFilterPlaceholder: __('Any date'),
        clearDateLabel: __('Clear'),
        calendarProps: calendarCopy(),
    };
}

/**
 * Normalise a column's `filter` flag to what DataTable's `meta.filter` expects:
 * omitted/true → 'auto' (pick the control from the column's own data), `false` →
 * undefined (no control), a string → that kind pinned.
 *
 * Every column is filterable by default so a list never has arbitrary gaps in
 * its filter row; `false` is for columns whose cell shows something other than
 * what the value holds.
 */
export function normalizeFilter(filter) {
    if (filter === false) return undefined;
    if (filter === true || filter == null) return 'auto';
    return filter;
}

/**
 * Make every column filterable, with the shared filter copy merged in.
 * A column's own `meta` wins, so `filter: false` or a pinned kind still applies.
 */
export function withFilters(columns) {
    const labels = filterLabels();
    return columns.map((col) => ({
        ...col,
        meta: {
            ...labels,
            ...col.meta,
            filter: normalizeFilter(col.meta?.filter),
        },
    }));
}
