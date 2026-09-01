import { RANGE_PRESETS } from '@openmes/ui';

import { __, locale, localeTag } from './i18n';

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

/**
 * Month and weekday names for the active locale.
 *
 * Taken from `Intl` rather than the lang files: these are 31 strings per locale
 * that no translator should have to retype, and getting one wrong is invisible
 * until someone opens the calendar in that language.
 *
 * English is the exception — the package's own defaults are used. The app maps
 * `en` to `en-GB`, whose abbreviation for September is "Sept", and the design
 * spells it "Sep". Nothing about an English UI should change here.
 */
let monthNameCache = null;

function localeMonthNames() {
    if (locale() === 'en') return {};
    const tag = localeTag();
    // Built once per locale, not per call: `calendarCopy()` runs on every render
    // of every DatePicker (AppDatePicker spreads it inline), and three
    // `Intl.DateTimeFormat` constructions plus 31 formatted strings is real work
    // to redo on each keystroke in a filter row. The locale only changes via a
    // full reload, so a module-level cache cannot go stale.
    if (monthNameCache?.tag === tag) return monthNameCache.value;
    const long = new Intl.DateTimeFormat(tag, { month: 'long' });
    const short = new Intl.DateTimeFormat(tag, { month: 'short' });
    const wd = new Intl.DateTimeFormat(tag, { weekday: 'short' });
    const monthOf = (fmt) => Array.from({ length: 12 }, (_, m) => fmt.format(new Date(2026, m, 1)));
    const value = {
        monthLabels: monthOf(long),
        monthShortLabels: monthOf(short),
        // 1 Jan 2024 was a Monday, and the grid is Monday-first.
        weekdayLabels: Array.from({ length: 7 }, (_, i) => wd.format(new Date(2024, 0, 1 + i))),
    };
    monthNameCache = { tag, value };
    return value;
}

/**
 * Copy for the calendar popover — its chrome, its month names, and the strings
 * only a screen reader hears.
 *
 * The preset labels are keyed off `RANGE_PRESETS` rather than restated, so a
 * preset added to the package can't ship an English chip into a Polish UI —
 * `__()` falls back to the key's own text, and the missing key shows up in the
 * lang-file parity check instead of on screen unnoticed.
 *
 * Called rather than a constant so it picks up the loaded catalogue; the
 * `Intl`-derived half is cached per locale (see `localeMonthNames`).
 */
export const calendarCopy = () => ({
    todayLabel: __('Today'),
    todayWord: __('today'),
    rangeLabel: __('Date range'),
    pickEndLabel: __('Pick an end date'),
    prevMonthLabel: __('Previous month'),
    nextMonthLabel: __('Next month'),
    monthGridLabel: __('Month'),
    yearGridLabel: __('Year'),
    // Drives the spoken day names ("Saturday, 22 August 2026"), which is all the
    // grid gives a screen reader — the cells themselves are bare digits.
    locale: localeTag(),
    announceRangeStart: (date) => __('Start date :date selected. Now pick an end date.', { date }),
    presetLabels: Object.fromEntries(RANGE_PRESETS.map((p) => [p.key, __(p.label)])),
    ...localeMonthNames(),
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
        dateDialogLabel: __('Choose date'),
        clearDateLabel: __('Clear'),
        calendarProps: calendarCopy(),
    };
}

/**
 * Normalise a column's `filter` flag to what DataTable's `meta.filter` expects:
 * omitted/true → 'auto' (pick the control from the column's own data), `false` →
 * `false` (no control — DataTable reads it as falsy), a string → that kind
 * pinned.
 *
 * Every column is filterable by default so a list never has arbitrary gaps in
 * its filter row; `false` is for columns whose cell shows something other than
 * what the value holds.
 *
 * **Idempotent, deliberately.** "Off" stays `false` rather than collapsing to
 * `undefined`, because a normalised column can go through here twice — once in
 * `buildColumnDefs`, again in `withFilters` when the defs pass through
 * `AppDataTable` — and `undefined` on the second pass is indistinguishable from
 * "never said", which is `'auto'`. That silently handed a filter back to a
 * column that had opted out.
 */
export function normalizeFilter(filter) {
    if (filter === false) return false;
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
