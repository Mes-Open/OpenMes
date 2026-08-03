import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { WAREHOUSE_KINDS } from './fields';
import { __ } from '../../../lib/i18n';

const KIND_LABELS = Object.fromEntries(WAREHOUSE_KINDS.map((k) => [k.value, k.label]));

export default function WarehousesIndex() {
    const { stockCounts = {}, documentCounts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'kind', label: __('Kind'), render: (r) => KIND_LABELS[r.kind] ?? r.kind },
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
        { label: __('Edit'), icon: 'edit', href: `/admin/warehouses/${r.id}/edit` },
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
                createLabel={__('+ New Warehouse')}
                columns={columns}
                orderBy="code"
                actions={actions}
                emptyText={__('No warehouses yet.')}
            />
        </>
    );
}

WarehousesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
