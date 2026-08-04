import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Modal } from '@openmes/ui';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import usePrompt from '../../../components/usePrompt';
import WorkOrderForm from './WorkOrderForm';
import { WO_STATUS_STYLES } from './fields';
import { woColumns } from './columns';
import { __ } from '../../../lib/i18n';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

export default function WorkOrdersIndex() {
    const {
        counts = {}, lineNames = {}, productTypeNames = {}, customerNames = {},
        // Create-form options, same props the standalone create page receives.
        lines = [], productTypes = [], customers = [], bomTemplates = [], productRevisions = [], customFields = [],
    } = usePage().props;

    // Creating from the list happens in a modal so you keep your filters, page and
    // selection. /admin/work-orders/create still renders the same form standalone.
    const [creating, setCreating] = useState(false);
    // Bumped after a successful create to remount the form — see the modal below.
    const [formKey, setFormKey] = useState(0);
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
    const columns = woColumns({ lineNames, productTypeNames, counts, customerNames, withScore: true });

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
                titleIcon="clipboard-list"
                // Reference implementation for the header breadcrumb trail — the
                // page title is appended as the current entry, so only ancestors
                // are listed here. ('Orders' is skipped: the nav group and this
                // page share a label, which would read "Zlecenia / Zlecenia".)
                breadcrumbs={[{ label: 'Dashboard', href: '/admin/dashboard', icon: 'layout-dashboard' }]}
                createHref="/admin/work-orders/create"
                onCreate={() => setCreating(true)}
                createLabel={__('New Work Order')}
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
                // A misclick on the scrim shouldn't cost a half-filled order.
                keepMounted
            >
                {/* THE work-order form — the same component the create page and the
                    planner render, so a field added there appears here too.
                    `stay` makes the controller send us back to this list instead of
                    redirecting, keeping filters and paging intact. */}
                {/* `keepMounted` holds the form's state, which is the point when
                    you close by accident — but the order you just created must
                    not linger in the next one. Bumping the key remounts the form,
                    and only on success. */}
                <WorkOrderForm
                    key={formKey}
                    lines={lines}
                    productTypes={productTypes}
                    customers={customers}
                    bomTemplates={bomTemplates}
                    productRevisions={productRevisions}
                    customFields={customFields}
                    stay
                    onCancel={() => setCreating(false)}
                    onSuccess={() => {
                        setCreating(false);
                        setFormKey((k) => k + 1);
                    }}
                />
            </Modal>
            {promptDialog}
        </>
    );
}

WorkOrdersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
