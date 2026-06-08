import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { STATUS_STYLES } from './fields';

export default function MaterialLotsIndex() {
    const { materialNames = {}, sourceNames = {} } = usePage().props;

    const columns = [
        { key: 'lot_number', label: 'Lot Number', className: 'font-mono text-gray-700' },
        { key: 'material', label: 'Material', className: 'text-gray-600', render: (r) => materialNames[r.material_id] ?? '—' },
        { key: 'qty', label: 'Avail / Recv', className: 'text-gray-600', render: (r) => `${r.quantity_available ?? '—'} / ${r.quantity_received ?? '—'}` },
        { key: 'unit_of_measure', label: 'Unit', className: 'text-gray-600' },
        { key: 'expiry_date', label: 'Expiry', className: 'text-gray-500', render: (r) => (r.expiry_date ? r.expiry_date.slice(0, 10) : '—') },
        {
            key: 'status',
            label: 'Status',
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-gray-100 text-gray-700'}`}>
                    {r.status}
                </span>
            ),
        },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/material-lots/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete material lot "${r.lot_number}"?`)) {
                    router.delete(`/admin/material-lots/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Material Lots" />
            <ResourceTable
                shape="material_lots"
                title="Material Lots"
                createHref="/admin/material-lots/create"
                createLabel="+ New Lot"
                columns={columns}
                orderBy="lot_number"
                actions={actions}
                emptyText="No material lots yet."
            />
        </>
    );
}

MaterialLotsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
