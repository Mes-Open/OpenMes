import { SegmentedProgress, StatusBadge } from '@openmes/ui';

import { WO_STATUSES, woStatusBadge, woStatusLabel } from './fields';
import { __, elapsed, formatDateTime } from '../../../lib/i18n';

/**
 * The work-order list columns, shared by the admin and supervisor lists.
 *
 * The two pages show the same orders and differ only in how much they show, so
 * they had drifted: the supervisor copy had silently lost the status filter and
 * the sortable quantity column. Anything both lists show belongs here; a page
 * that wants an extra column appends its own.
 *
 * @param lineNames/productTypeNames/counts  id → label lookups from the page props
 * @param customerNames  pass to include the Customer column, omit to drop it
 * @param withScore      include the computed priority score column
 */
export function woColumns({ lineNames = {}, productTypeNames = {}, counts = {}, customerNames = null, withScore = false } = {}) {
    return [
        { key: 'order_no', label: __('Order'), className: 'font-mono font-medium text-om-ink', filter: 'text' },
        ...(customerNames
            ? [{
                key: 'customer',
                label: __('Customer'),
                className: 'text-om-muted',
                value: (r) => customerNames[r.customer_id] ?? '—',
                render: (r) => customerNames[r.customer_id] ?? '—',
            }]
            : []),
        {
            key: 'line',
            label: __('Line'),
            className: 'text-om-muted',
            value: (r) => lineNames[r.line_id] ?? '—',
            render: (r) => lineNames[r.line_id] ?? '—',
        },
        {
            key: 'product',
            label: __('Product'),
            className: 'text-om-muted',
            value: (r) => productTypeNames[r.product_type_id] ?? '—',
            render: (r) => productTypeNames[r.product_type_id] ?? '—',
        },
        {
            key: 'qty',
            label: __('Produced'),
            className: 'text-om-muted',
            value: (r) => Number(r.produced_qty),
            // Produced-against-planned is the one column that is a ratio, so it
            // carries the meter. The count stays: the bar shows eight steps, and
            // "0 / 186" is a different thing to know than "not started".
            render: (r) => {
                const produced = Number(r.produced_qty);
                const planned = Number(r.planned_qty);
                return (
                    <span className="inline-flex flex-col items-center gap-1 whitespace-nowrap">
                        <SegmentedProgress
                            value={produced}
                            max={planned}
                            label={__('Produced')}
                        />
                        <span className="tabular-nums">{produced.toFixed(0)} / {planned.toFixed(0)}</span>
                    </span>
                );
            },
            sortable: true,
            // Produced and planned are both worth totalling, and only together:
            // "8420 / 12000" is the shop's progress, either number alone isn't.
            summary: (rows) => {
                const produced = rows.reduce((a, r) => a + Number(r.original.produced_qty || 0), 0);
                const planned = rows.reduce((a, r) => a + Number(r.original.planned_qty || 0), 0);
                return `${produced.toFixed(0)} / ${planned.toFixed(0)}`;
            },
        },
        {
            key: 'status',
            label: __('Status'),
            filter: 'select',
            options: WO_STATUSES,
            optionLabel: woStatusLabel,
            allLabel: __('All statuses'),
            render: (r) => <StatusBadge {...woStatusBadge(r.status)} />,
        },
        { key: 'priority', label: __('Priority'), className: 'text-om-muted' },
        ...(withScore
            ? [{
                key: 'priority_score',
                label: __('Score'),
                className: 'text-om-muted font-mono',
                value: (r) => Number(r.priority_score ?? 0),
                render: (r) => r.priority_score ?? 0,
            }]
            : []),
        {
            key: 'due_date',
            label: __('Due'),
            className: 'text-om-muted',
            filter: 'date',
            render: (r) => (r.due_date ? r.due_date.slice(0, 10) : '—'),
        },
        {
            key: 'created_at',
            label: __('Age'),
            live: true,
            // The cell shows elapsed time, not the timestamp behind it, so a
            // filter on it would compare against something the reader can't see.
            filter: false,
            align: 'center',
            className: 'text-om-muted tabular-nums',
            render: (r, now) => <span title={formatDateTime(r.created_at)}>{elapsed(r.created_at, now)}</span>,
            // Sort by age: ascending = youngest first (largest created_at). Nulls last.
            sortAccessor: (r) => (r.created_at ? -new Date(r.created_at).getTime() : Number.POSITIVE_INFINITY),
        },
        { key: 'batches', label: __('Batches'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0, summary: 'sum' },

        // Everything else the order carries, off by default and switched on from
        // the Columns menu. They are here rather than absent because the cost of
        // a hidden column is a menu entry, while the cost of a missing one is
        // that nobody can see the field at all — but a list that opened with
        // thirty columns would be unreadable, so none of them start on.
        ...OPTIONAL_COLUMNS,
    ];
}

/** '—' for anything the order hasn't got. */
const dash = (v) => (v === null || v === undefined || v === '' ? '—' : v);

/**
 * Booleans arrive from the API as real booleans in a cell, but the filter's
 * derived options hand back whatever the column holds — including the strings
 * "true"/"false" once a value has been through a <select>. Both spellings map
 * to the same word so the cell and its filter never disagree.
 */
const yesNo = (v) => (v === true || v === 'true' || v === 1 || v === '1' ? __('Yes') : __('No'));

const dateCell = (v) => (v ? formatDateTime(v) : '—');
const dayCell = (v) => (v ? String(v).slice(0, 10) : '—');

/**
 * A JSON column holds an object, not a value — a cell can only say how much is
 * in there. The count is the useful part at a glance; the raw JSON goes in the
 * title so hovering answers "which ones?" without a detour to the detail page.
 *
 * Only `custom_fields` uses this. The order's frozen routing and its legacy
 * extras bag are deliberately NOT synced: both are blobs (the routing averages
 * ~2KB) that every subscriber would carry on every delta, and `work_orders_all`
 * also feeds the alerts list, the supervisor list and the planner — three pages
 * that would pay for a hidden column showing a key count. They are on the
 * order's detail page, fetched for one record, which is where a routing belongs.
 */
function jsonCell(v) {
    if (v === null || v === undefined) return '—';
    let parsed = v;
    if (typeof v === 'string') {
        try {
            parsed = JSON.parse(v);
        } catch {
            return <span title={v}>{v.slice(0, 24)}</span>;
        }
    }
    const n = Array.isArray(parsed) ? parsed.length : Object.keys(parsed ?? {}).length;
    if (n === 0) return '—';
    return <span title={JSON.stringify(parsed).slice(0, 500)}>{n}</span>;
}

const OPTIONAL_COLUMNS = [
    { key: 'customer_order_no', label: __('Customer Order No'), hidden: true, className: 'font-mono text-om-muted', render: (r) => dash(r.customer_order_no) },
    { key: 'description', label: __('Description'), hidden: true, className: 'text-om-muted', render: (r) => dash(r.description) },
    // No name lookup is passed to this builder, so these two show the raw FK.
    { key: 'product_revision_id', label: __('Revision'), hidden: true, className: 'text-om-muted', render: (r) => dash(r.product_revision_id) },
    { key: 'line_status_id', label: __('Line Status'), hidden: true, className: 'text-om-muted', render: (r) => dash(r.line_status_id) },
    // Summing per-unit prices would be meaningless (they're rates, not amounts);
    // the average is the number that answers "what do these orders go for?".
    { key: 'unit_price', label: __('Unit Price'), hidden: true, className: 'text-om-muted tabular-nums', render: (r) => dash(r.unit_price), summary: 'avg' },
    { key: 'counting_source', label: __('Counting Source'), hidden: true, className: 'text-om-muted', render: (r) => dash(r.counting_source) },
    { key: 'packed_qty', label: __('Packed'), hidden: true, className: 'text-om-muted tabular-nums', render: (r) => dash(r.packed_qty), summary: 'sum' },
    { key: 'planned_start_at', label: __('Planned Start'), hidden: true, filter: 'date', className: 'text-om-muted', render: (r) => dateCell(r.planned_start_at) },
    { key: 'planned_end_at', label: __('Planned End'), hidden: true, filter: 'date', className: 'text-om-muted', render: (r) => dateCell(r.planned_end_at) },
    { key: 'end_date', label: __('End date'), hidden: true, filter: 'date', className: 'text-om-muted', render: (r) => dayCell(r.end_date) },
    { key: 'completed_at', label: __('Completed'), hidden: true, filter: 'date', className: 'text-om-muted', render: (r) => dateCell(r.completed_at) },
    { key: 'updated_at', label: __('Updated'), hidden: true, filter: 'date', className: 'text-om-muted', render: (r) => dateCell(r.updated_at) },
    { key: 'shift_number', label: __('Shift'), hidden: true, className: 'text-om-muted tabular-nums', render: (r) => dash(r.shift_number) },
    { key: 'end_shift_number', label: __('End Shift'), hidden: true, className: 'text-om-muted tabular-nums', render: (r) => dash(r.end_shift_number) },
    { key: 'week_number', label: __('Week'), hidden: true, className: 'text-om-muted tabular-nums', render: (r) => dash(r.week_number) },
    { key: 'month_number', label: __('Month'), hidden: true, className: 'text-om-muted tabular-nums', render: (r) => dash(r.month_number) },
    { key: 'production_year', label: __('Year'), hidden: true, className: 'text-om-muted tabular-nums', render: (r) => dash(r.production_year) },
    {
        key: 'customer_totals_counted',
        label: __('Counted'),
        hidden: true,
        className: 'text-om-muted',
        render: (r) => yesNo(r.customer_totals_counted),
        // The filter's options come from the raw column values, so without this
        // the menu offers "true"/"false" under a cell that reads "Tak"/"Nie".
        filter: 'select',
        optionLabel: yesNo,
    },
    { key: 'custom_fields', label: __('Custom fields'), hidden: true, filter: false, className: 'text-om-muted tabular-nums', render: (r) => jsonCell(r.custom_fields) },
];
