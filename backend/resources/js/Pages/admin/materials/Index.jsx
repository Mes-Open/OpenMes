import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { TRACKING_LABELS } from './fields';

export default function MaterialsIndex() {
    const { counts = {}, materialTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'type', label: 'Type', className: 'text-gray-600', render: (r) => materialTypeNames[r.material_type_id] ?? '—' },
        { key: 'unit_of_measure', label: 'UoM', className: 'text-gray-600' },
        { key: 'tracking_type', label: 'Tracking', className: 'text-gray-600', render: (r) => TRACKING_LABELS[r.tracking_type] ?? r.tracking_type ?? '—' },
        { key: 'stock_quantity', label: 'Stock', className: 'text-gray-600', render: (r) => (r.stock_quantity ?? '—') },
        { key: 'bom', label: 'In BOMs', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/materials/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/materials/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete material "${r.name}"?`)) {
                    router.delete(`/admin/materials/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Materials" />
            <ResourceTable
                shape="materials"
                title="Materials"
                createHref="/admin/materials/create"
                createLabel="+ New Material"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No materials yet."
            />
        </>
    );
}

MaterialsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
