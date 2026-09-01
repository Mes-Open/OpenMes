import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { TRACKING_LABELS, materialFields, materialInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function MaterialsIndex() {
    const { counts = {}, materialTypeNames = {}, materialTypes, customFields } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text', link: true },
        { key: 'type', label: __('Type'), className: 'text-om-muted', value: (r) => materialTypeNames[r.material_type_id] ?? '—', render: (r) => materialTypeNames[r.material_type_id] ?? '—' },
        { key: 'unit_of_measure', label: __('UoM'), className: 'text-om-muted' },
        { key: 'tracking_type', label: __('Tracking'), className: 'text-om-muted', render: (r) => TRACKING_LABELS[r.tracking_type] ?? r.tracking_type ?? '—' },
        { key: 'stock_quantity', label: __('Stock'), className: 'text-om-muted', value: (r) => Number(r.stock_quantity ?? 0), render: (r) => (r.stock_quantity ?? '—') },
        { key: 'bom', label: __('In BOMs'), value: (r) => counts[r.id] ?? 0, render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/materials/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete material ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/materials/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Materials')} />
            <ResourceTable
                shape="materials"
                detailHref={(r) => `/admin/materials/${r.id}`}
                title={__('Materials')}
                createHref="/admin/materials/create"
                onCreate={drawer.create}
                createLabel={__('New Material')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No materials yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/materials"
                fields={materialFields(materialTypes ?? [])}
                initial={materialInitial}
                customFields={customFields}
                ensure={['materialTypes', 'customFields']}
                ready={materialTypes !== undefined}
                title={{ create: __('New Material'), edit: __('Edit Material') }}
            />
        </>
    );
}

MaterialsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
