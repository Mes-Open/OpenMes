import { Icon, SegmentedProgress } from '@openmes/ui';

import { WO_STATUSES, WO_STATUS_STYLES, WO_STATUS_ICONS, woStatusLabel } from './fields';
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
                    <span className="inline-flex items-center gap-2.5 whitespace-nowrap">
                        <span className="tabular-nums">{produced.toFixed(0)} / {planned.toFixed(0)}</span>
                        <SegmentedProgress
                            value={produced}
                            max={planned}
                            label={__('Produced')}
                        />
                    </span>
                );
            },
            sortable: true,
        },
        {
            key: 'status',
            label: __('Status'),
            filter: 'select',
            options: WO_STATUSES,
            optionLabel: woStatusLabel,
            allLabel: __('All statuses'),
            render: (r) => (
                <span className={`inline-flex items-center gap-1.5 text-xs px-2 py-0.5 rounded font-medium ${WO_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>
                    {WO_STATUS_ICONS[r.status] && (
                        <Icon name={WO_STATUS_ICONS[r.status]} size={12} className="shrink-0" />
                    )}
                    {__(r.status)}
                </span>
            ),
        },
        { key: 'priority', label: __('Prio'), className: 'text-om-muted' },
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
        { key: 'batches', label: __('Batches'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
    ];
}
