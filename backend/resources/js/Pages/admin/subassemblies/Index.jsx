import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function SubassembliesIndex() {
    const { productTypeNames = {}, counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink' },
        { key: 'product_type', label: __('Product Type'), className: 'text-om-muted', render: (r) => productTypeNames[r.product_type_id] ?? '—' },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/subassemblies/${r.id}/edit` },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/subassemblies/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete subassembly ":name"?', { name: r.name }))) {
                    router.delete(`/admin/subassemblies/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Subassemblies')} />
            <ResourceTable
                shape="subassemblies"
                title={__('Subassemblies')}
                createHref="/admin/subassemblies/create"
                createLabel={__('+ New Subassembly')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No subassemblies yet.')}
            />
        </>
    );
}

SubassembliesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
