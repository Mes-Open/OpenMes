import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { TRACKING_LABELS } from './fields';
import { __ } from '../../../lib/i18n';

export default function MaterialsIndex() {
    const { counts = {}, materialTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-gray-700' },
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'type', label: __('Type'), className: 'text-gray-600', render: (r) => materialTypeNames[r.material_type_id] ?? '—' },
        { key: 'unit_of_measure', label: __('UoM'), className: 'text-gray-600' },
        { key: 'tracking_type', label: __('Tracking'), className: 'text-gray-600', render: (r) => TRACKING_LABELS[r.tracking_type] ?? r.tracking_type ?? '—' },
        { key: 'stock_quantity', label: __('Stock'), className: 'text-gray-600', render: (r) => (r.stock_quantity ?? '—') },
        { key: 'bom', label: __('In BOMs'), render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/materials/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/materials/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete material ":name"?', { name: r.name }))) {
                    router.delete(`/admin/materials/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Materials')} />
            <ResourceTable
                shape="materials"
                title={__('Materials')}
                createHref="/admin/materials/create"
                createLabel={__('+ New Material')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No materials yet.')}
            />
        </>
    );
}

MaterialsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
