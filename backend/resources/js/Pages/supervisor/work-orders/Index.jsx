import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { WO_STATUS_STYLES } from '../../admin/work-orders/fields';

const TERMINAL = ['DONE', 'REJECTED', 'CANCELLED'];

export default function SupervisorWorkOrdersIndex() {
    const { counts = {}, lineNames = {}, productTypeNames = {} } = usePage().props;

    const post = (id, verb, data = {}) => router.post(`/supervisor/work-orders/${id}/${verb}`, data, { preserveScroll: true });

    const columns = [
        { key: 'order_no', label: 'Order', className: 'font-mono font-medium text-gray-800' },
        { key: 'line', label: 'Line', className: 'text-gray-600', render: (r) => lineNames[r.line_id] ?? '—' },
        { key: 'product', label: 'Product', className: 'text-gray-600', render: (r) => productTypeNames[r.product_type_id] ?? '—' },
        { key: 'qty', label: 'Produced / Planned', className: 'text-gray-600', render: (r) => `${Number(r.produced_qty).toFixed(0)} / ${Number(r.planned_qty).toFixed(0)}` },
        {
            key: 'status', label: 'Status',
            render: (r) => <span className={`text-xs px-2 py-0.5 rounded font-medium ${WO_STATUS_STYLES[r.status] ?? 'bg-gray-100 text-gray-700'}`}>{r.status}</span>,
        },
        { key: 'priority', label: 'Prio', className: 'text-gray-600' },
        { key: 'due_date', label: 'Due', className: 'text-gray-500', render: (r) => (r.due_date ? r.due_date.slice(0, 10) : '—') },
        { key: 'batches', label: 'Batches', render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => {
        const a = [{ label: 'Edit', href: `/supervisor/work-orders/${r.id}/edit` }];
        const s = r.status;

        if (s === 'PENDING') {
            a.push({ label: 'Accept', onClick: () => post(r.id, 'accept') });
            a.push({ label: 'Reject', onClick: () => post(r.id, 'reject') });
        } else if (s === 'ACCEPTED') {
            a.push({ label: 'Reject', onClick: () => post(r.id, 'reject') });
        } else if (s === 'IN_PROGRESS') {
            a.push({ label: 'Pause', onClick: () => post(r.id, 'pause') });
            a.push({
                label: 'Complete',
                onClick: () => {
                    const qty = prompt('Produced quantity to complete with:', r.planned_qty);
                    if (qty) post(r.id, 'complete', { produced_qty: qty });
                },
            });
        } else if (s === 'PAUSED') {
            a.push({ label: 'Resume', onClick: () => post(r.id, 'resume') });
        }

        if (TERMINAL.includes(s)) {
            a.push({ label: 'Reopen', onClick: () => post(r.id, 'reopen') });
        } else {
            a.push({ label: 'Cancel', className: 'text-amber-600 hover:underline', onClick: () => { if (confirm(`Cancel work order ${r.order_no}?`)) post(r.id, 'cancel'); } });
        }

        return a;
    };

    return (
        <>
            <Head title="Work Orders" />
            <ResourceTable
                shape="work_orders_all"
                title="Work Orders"
                columns={columns}
                orderBy="order_no"
                actions={actions}
                emptyText="No work orders yet."
            />
        </>
    );
}

SupervisorWorkOrdersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
