import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { WO_STATUS_STYLES } from './fields';
import { __ } from '../../../lib/i18n';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

export default function WorkOrdersIndex() {
    const { counts = {}, lineNames = {}, productTypeNames = {} } = usePage().props;

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

    const columns = [
        { key: 'order_no', label: __('Order'), className: 'font-mono font-medium text-om-ink' },
        { key: 'line', label: __('Line'), className: 'text-om-muted', render: (r) => lineNames[r.line_id] ?? '—' },
        { key: 'product', label: __('Product'), className: 'text-om-muted', render: (r) => productTypeNames[r.product_type_id] ?? '—' },
        { key: 'qty', label: __('Produced / Planned'), className: 'text-om-muted', render: (r) => `${Number(r.produced_qty).toFixed(0)} / ${Number(r.planned_qty).toFixed(0)}` },
        {
            key: 'status', label: __('Status'),
            render: (r) => <span className={`text-xs px-2 py-0.5 rounded font-medium ${WO_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>{__(r.status)}</span>,
        },
        { key: 'priority', label: __('Prio'), className: 'text-om-muted' },
        { key: 'due_date', label: __('Due'), className: 'text-om-muted', render: (r) => (r.due_date ? r.due_date.slice(0, 10) : '—') },
        { key: 'batches', label: __('Batches'), render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => {
        const a = [{ label: __('Edit'), icon: 'edit', href: `/admin/work-orders/${r.id}/edit` }];
        const s = r.status;

        if (s === 'PENDING') {
            a.push({ label: __('Accept'), onClick: () => post(r.id, 'accept') });
            a.push({ label: __('Reject'), onClick: () => post(r.id, 'reject') });
        } else if (s === 'ACCEPTED') {
            a.push({ label: __('Reject'), onClick: () => post(r.id, 'reject') });
        } else if (s === 'IN_PROGRESS') {
            a.push({ label: __('Pause'), onClick: () => post(r.id, 'pause') });
            a.push({
                label: __('Complete'),
                onClick: () => {
                    const qty = prompt(__('Produced quantity to complete with:'), r.planned_qty);
                    if (qty) post(r.id, 'complete', { produced_qty: qty });
                },
            });
        } else if (s === 'PAUSED') {
            a.push({ label: __('Resume'), onClick: () => post(r.id, 'resume') });
        }

        if (TERMINAL.includes(s)) {
            a.push({ label: __('Reopen'), onClick: () => post(r.id, 'reopen') });
        } else {
            a.push({ label: __('Cancel'), variant: 'warning', onClick: () => { if (confirm(__('Cancel work order :order?', { order: r.order_no }))) post(r.id, 'cancel'); } });
        }

        a.push({
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => { if (confirm(__('Delete work order :order? (only allowed if it has no batches)', { order: r.order_no }))) router.delete(`/admin/work-orders/${r.id}`, { preserveScroll: true }); },
        });
        return a;
    };

    return (
        <>
            <Head title={__('Work Orders')} />
            <ResourceTable
                shape="work_orders_all"
                title={__('Work Orders')}
                createHref="/admin/work-orders/create"
                createLabel={__('+ New Work Order')}
                columns={columns}
                orderBy="order_no"
                actions={actions}
                emptyText={__('No work orders yet.')}
                filterFn={filterFn}
                subtitle={subtitle}
            />
        </>
    );
}

WorkOrdersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
