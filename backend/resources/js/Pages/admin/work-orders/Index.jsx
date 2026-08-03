import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Modal } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import usePrompt from '../../../components/usePrompt';
import WorkOrderForm from './WorkOrderForm';
import { WO_STATUSES, WO_STATUS_STYLES, woStatusLabel } from './fields';
import { __, elapsed, formatDateTime } from '../../../lib/i18n';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

export default function WorkOrdersIndex() {
    const {
        counts = {}, lineNames = {}, productTypeNames = {}, customerNames = {},
        // Create-form options, same props the standalone create page receives.
        lines = [], productTypes = [], customers = [], bomTemplates = [], customFields = [],
    } = usePage().props;

    // Creating from the list happens in a modal so you keep your filters, page and
    // selection. /admin/work-orders/create still renders the same form standalone.
    const [creating, setCreating] = useState(false);
    const { prompt, dialog: promptDialog } = usePrompt();

    // Honor dashboard KPI deep-links (e.g. ?status=IN_PROGRESS&line_id=3) so a
    // click lands on the matching filtered list instead of the full table.
    const params = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : new URLSearchParams();
    const statusFilter = params.get('status');
    const lineFilter = params.get('line_id');
    const filterFn = (statusFilter || lineFilter)
        ? (r) => (!statusFilter || r.status === statusFilter) && (!lineFilter || String(r.line_id) === String(lineFilter))
        : undefined;
    const subtitle = filterFn ? (
        <div className="flex items-center gap-2 text-sm">
            {statusFilter && (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${WO_STATUS_STYLES[statusFilter] ?? 'bg-om-chip text-om-muted'}`}>
                    {statusFilter}
                </span>
            )}
            {lineFilter && (
                <span className="text-xs px-2 py-0.5 rounded bg-om-chip text-om-muted">
                    {lineNames[lineFilter] ?? `Line ${lineFilter}`}
                </span>
            )}
            <a href="/admin/work-orders" className="text-om-accent hover:underline">{__('Clear')}</a>
        </div>
    ) : undefined;

    const post = (id, verb, data = {}) => router.post(`/admin/work-orders/${id}/${verb}`, data, { preserveScroll: true });

    // Bulk transitions over the checkbox selection. The backend skips orders the
    // action doesn't apply to (a selection spanning mixed statuses is normal) and
    // reports how many it skipped, so there's nothing to pre-filter here.
    const bulk = (action, rows, clear) => {
        router.post(
            '/admin/work-orders/bulk',
            { action, ids: rows.map((r) => r.id) },
            { preserveScroll: true, onSuccess: clear },
        );
    };

    // Status transitions act on the checkbox selection through the design's
    // action bar (§12) rather than living on every row — that is what keeps the
    // Actions column narrow and aligned. The endpoint skips orders a verb doesn't
    // apply to, so a mixed selection is fine; each chip only appears when at
    // least one selected order could actually use it.
    const BULK_VERBS = [
        { key: 'accept', label: __('Accept'), from: ['PENDING'], title: 'Accept :count selected work order(s)?', confirmLabel: __('Accept orders') },
        { key: 'reject', label: __('Reject'), from: ['PENDING', 'ACCEPTED'], title: 'Reject :count selected work order(s)?', confirmLabel: __('Reject orders') },
        { key: 'pause', label: __('Pause'), from: ['IN_PROGRESS'], title: 'Pause :count selected work order(s)?', confirmLabel: __('Pause orders') },
        { key: 'resume', label: __('Resume'), from: ['PAUSED'], title: 'Resume :count selected work order(s)?', confirmLabel: __('Resume orders') },
        { key: 'reopen', label: __('Reopen'), from: TERMINAL, title: 'Reopen :count selected work order(s)?', confirmLabel: __('Reopen orders') },
        {
            key: 'cancel', label: __('Cancel'), variant: 'warning',
            from: ['PENDING', 'ACCEPTED', 'IN_PROGRESS', 'PAUSED', 'BLOCKED'],
            title: 'Cancel :count selected work order(s)?', confirmLabel: __('Cancel orders'),
            body: __('Cancelled orders stop production and can be reopened later.'),
        },
    ];

    const bulkActionItems = BULK_VERBS.map((v) => ({
        key: v.key,
        label: v.label,
        variant: v.variant,
        applies: (rows) => rows.some((r) => v.from.includes(r.status)),
        confirm: (rows) => ({
            title: __(v.title, { count: rows.length }),
            body: v.body ?? __("Orders this action doesn't apply to are skipped."),
            confirmLabel: v.confirmLabel,
        }),
        onClick: (rows, clear) => bulk(v.key, rows, clear),
    }));

    // `value` feeds search/sort/filter for the lookup-rendered columns; `filter`
    // adds the column's control to the filter row (options derived from the rows).
    const columns = [
        { key: 'order_no', label: __('Order'), className: 'font-mono font-medium text-om-ink', filter: 'text' },
        { key: 'customer', label: __('Customer'), className: 'text-om-muted', value: (r) => customerNames[r.customer_id] ?? '—', render: (r) => customerNames[r.customer_id] ?? '—' },
        { key: 'line', label: __('Line'), className: 'text-om-muted', value: (r) => lineNames[r.line_id] ?? '—', render: (r) => lineNames[r.line_id] ?? '—' },
        { key: 'product', label: __('Product'), className: 'text-om-muted', value: (r) => productTypeNames[r.product_type_id] ?? '—', render: (r) => productTypeNames[r.product_type_id] ?? '—' },
        { key: 'qty', label: __('Produced / Planned'), className: 'text-om-muted', value: (r) => Number(r.produced_qty), render: (r) => `${Number(r.produced_qty).toFixed(0)} / ${Number(r.planned_qty).toFixed(0)}`, sortable: true },
        {
            key: 'status', label: __('Status'),
            filter: 'select', options: WO_STATUSES, optionLabel: woStatusLabel, allLabel: __('All statuses'),
            render: (r) => <span className={`text-xs px-2 py-0.5 rounded font-medium ${WO_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>{__(r.status)}</span>,
        },
        { key: 'priority', label: __('Prio'), className: 'text-om-muted' },
        { key: 'priority_score', label: __('Score'), className: 'text-om-muted font-mono', value: (r) => Number(r.priority_score ?? 0), render: (r) => r.priority_score ?? 0 },
        { key: 'due_date', label: __('Due'), className: 'text-om-muted', filter: 'date', render: (r) => (r.due_date ? r.due_date.slice(0, 10) : '—') },
        {
            key: 'created_at', label: __('Age'), live: true, filter: false, align: 'center', className: 'text-om-muted tabular-nums',
            render: (r, now) => <span title={formatDateTime(r.created_at)}>{elapsed(r.created_at, now)}</span>,
            // Sort by age: ascending = youngest first (largest created_at). Nulls last.
            sortAccessor: (r) => (r.created_at ? -new Date(r.created_at).getTime() : Number.POSITIVE_INFINITY),
        },
        { key: 'batches', label: __('Batches'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
    ];

    // Fixed action rail: the same five slots on every row, in the same order, so
    // they line up down the column whatever each order's status allows. A slot the
    // row can't use stays blank instead of letting the others slide left.
    //
    // Slots 2 and 3 hold the status transitions — at most two apply at once
    // (Accept+Reject, or Pause+Complete), so two slots cover every status.
    // With the status verbs in the selection bar, the row keeps only what the bar
    // can't do: Edit, and Complete — which needs a produced quantity per order, so
    // it can't sensibly be applied to a multi-row selection. Fixed-width slots, so
    // the column still lines up when Complete doesn't apply.
    const actionSlots = [
        {
            key: 'edit',
            width: 34,
            resolve: (r) => ({ label: __('Edit'), icon: 'edit', iconOnly: true, variant: 'ghost', href: `/admin/work-orders/${r.id}/edit` }),
        },
        {
            key: 'complete',
            width: 116,
            resolve: (r) =>
                r.status !== 'IN_PROGRESS'
                    ? null
                    : {
                          label: __('Complete'),
                          icon: 'complete',
                          variant: 'primary',
                          onClick: () => prompt(
                              {
                                  title: __('Complete'),
                                  label: __('Produced quantity'),
                                  defaultValue: r.planned_qty,
                                  type: 'number',
                                  min: 0,
                                  confirmLabel: __('Complete'),
                              },
                              (qty) => post(r.id, 'complete', { produced_qty: qty }),
                          ),
                      },
        },
        {
            key: 'delete',
            width: 34,
            resolve: (r) => ({
                label: __('Delete'),
                icon: 'delete',
                iconOnly: true,
                variant: 'ghost-danger',
                confirm: {
                    title: __('Delete work order :order?', { order: r.order_no }),
                    body: __('Only allowed if it has no batches. Logged output stays in reports.'),
                    confirmLabel: __('Delete order'),
                },
                onClick: () => router.delete(`/admin/work-orders/${r.id}`, { preserveScroll: true }),
            }),
        },
    ];

    return (
        <>
            <Head title={__('Work Orders')} />
            <ResourceTable
                shape="work_orders_all"
                detailHref={(r) => `/admin/work-orders/${r.id}`}
                title={__('Work Orders')}
                createHref="/admin/work-orders/create"
                onCreate={() => setCreating(true)}
                createLabel={__('+ New Work Order')}
                columns={columns}
                orderBy="order_no"
                actionSlots={actionSlots}
                emptyText={__('No work orders yet.')}
                filterFn={filterFn}
                subtitle={subtitle}
                bulkActionItems={bulkActionItems}
            />

            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title={__('New Work Order')}
                closeLabel={__('Close')}
                className="max-w-[720px]"
            >
                {/* THE work-order form — the same component the create page and the
                    planner render, so a field added there appears here too.
                    `stay` makes the controller send us back to this list instead of
                    redirecting, keeping filters and paging intact. */}
                <WorkOrderForm
                    lines={lines}
                    productTypes={productTypes}
                    customers={customers}
                    bomTemplates={bomTemplates}
                    customFields={customFields}
                    stay
                    onCancel={() => setCreating(false)}
                    onSuccess={() => setCreating(false)}
                />
            </Modal>
            {promptDialog}
        </>
    );
}

WorkOrdersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
