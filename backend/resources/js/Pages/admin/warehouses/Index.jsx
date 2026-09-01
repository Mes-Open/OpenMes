import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { warehouseKinds, warehouseFields, warehouseInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function WarehousesIndex() {
    const drawer = useResourceDrawer();

    const { stockCounts = {}, documentCounts = {} } = usePage().props;

    // Built during render: page modules load before the locale chunk, so a
    // module-scope __() would freeze the untranslated key (see ./fields.js).
    const kindLabels = Object.fromEntries(warehouseKinds().map((k) => [k.value, k.label]));

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'kind', label: __('Kind'), render: (r) => kindLabels[r.kind] ?? r.kind },
        { key: 'erp_code', label: __('ERP Code'), className: 'font-mono text-om-muted', render: (r) => r.erp_code || '—' },
        {
            key: 'is_default',
            label: __('Default'),
            render: (r) => (r.is_default ? __('Yes') : '—'),
        },
        { key: 'items', label: __('Items'), render: (r) => stockCounts[r.id] ?? 0 },
        { key: 'documents', label: __('Documents'), render: (r) => documentCounts[r.id] ?? 0 },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        ...(r.is_default
            ? []
            : [{
                label: __('Make Default'),
                icon: 'activate',
                onClick: () => router.post(`/admin/warehouses/${r.id}/set-default`, {}, { preserveScroll: true }),
            }]),
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/warehouses/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete warehouse ":name"?', { name: r.name }))) {
                    router.delete(`/admin/warehouses/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Warehouses')} />
            <ResourceTable
                shape="warehouses"
                title={__('Warehouses')}
                createHref="/admin/warehouses/create"
                onCreate={drawer.create}
                createLabel={__('New Warehouse')}
                columns={columns}
                orderBy="code"
                actions={actions}
                emptyText={__('No warehouses yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/warehouses"
                fields={warehouseFields()}
                initial={warehouseInitial}
                title={{ create: __('New Warehouse'), edit: __('Edit Warehouse') }}
            />
        </>
    );
}

WarehousesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
