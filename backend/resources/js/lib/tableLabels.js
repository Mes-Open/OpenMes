import { __ } from './i18n';

/**
 * Translated chrome for `@openmes/ui`'s DataTable.
 *
 * The design-system package deliberately has no i18n of its own — every visible
 * string arrives as a prop. `ResourceTable` has always passed the full set, but
 * pages that render DataTable directly mostly passed none, so they fell back to
 * the package's English defaults (a stray "Search records…" in an otherwise
 * Polish page) and their column filters rendered with untranslated copy.
 *
 * Spread this into any direct DataTable so the two paths can't drift:
 *
 *     <DataTable {...tableLabels()} data={rows} columns={cols} />
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

/**
 * Per-column filter copy — the bits DataTable reads from a column's `meta`.
 * Merge into a column's meta alongside `filter`, or use `withFilters()` below.
 */
export function filterLabels() {
    return {
        filterPlaceholder: __('Filter…'),
        allLabel: __('All'),
        numberFilterPlaceholder: __('>10'),
        numberFilterHint: __('Examples: 12, >10, <=5, 3-8'),
        dateFilterPlaceholder: __('Any date'),
        clearDateLabel: __('Clear'),
    };
}

/**
 * Make every column filterable, matching ResourceTable's convention ("every
 * column is filterable by default, opt out with `filter: false`"). DataTable
 * itself defaults the other way — filters are opt-in per column — which is why
 * directly-rendered tables had no filter row at all.
 *
 * Columns already carrying an explicit `meta.filter` are left alone, so a page
 * can still pin a control kind or pass `filter: false` to opt a column out.
 */
export function withFilters(columns) {
    const labels = filterLabels();
    return columns.map((col) => {
        const meta = col.meta ?? {};
        if ('filter' in meta) {
            // Explicit choice — only fill in copy it didn't specify.
            return { ...col, meta: { ...labels, ...meta } };
        }
        return { ...col, meta: { ...labels, ...meta, filter: 'auto' } };
    });
}
