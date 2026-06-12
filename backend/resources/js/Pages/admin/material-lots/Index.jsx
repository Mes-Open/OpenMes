import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { STATUS_STYLES } from './fields';

export default function MaterialLotsIndex() {
    const { materialNames = {}, sourceNames = {} } = usePage().props;

    const columns = [
        { key: 'lot_number', label: 'Lot Number', className: 'font-mono text-om-muted' },
        { key: 'material', label: 'Material', className: 'text-om-muted', render: (r) => materialNames[r.material_id] ?? '—' },
        { key: 'qty', label: 'Avail / Recv', className: 'text-om-muted', render: (r) => `${r.quantity_available ?? '—'} / ${r.quantity_received ?? '—'}` },
        { key: 'unit_of_measure', label: 'Unit', className: 'text-om-muted' },
        { key: 'expiry_date', label: 'Expiry', className: 'text-om-muted', render: (r) => (r.expiry_date ? r.expiry_date.slice(0, 10) : '—') },
        {
            key: 'status',
            label: 'Status',
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>
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
