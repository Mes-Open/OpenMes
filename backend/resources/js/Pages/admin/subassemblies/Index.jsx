import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { __ } from '../../../lib/i18n';
import { subassemblyFields, subassemblyInitial } from './fields';

export default function SubassembliesIndex() {
    const { productTypeNames = {}, counts = {}, productTypes } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-om-muted' },
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'product_type', label: __('Product Type'), className: 'text-om-muted', value: (r) => productTypeNames[r.product_type_id] ?? '—', render: (r) => productTypeNames[r.product_type_id] ?? '—' },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('View'), href: `/admin/subassemblies/${r.id}` },
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: r.is_active ? __('Deactivate') : __('Activate'),
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/subassemblies/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete subassembly ":name"?', { name: r.name }),
                confirmLabel: __('Delete subassembly'),
            },
            onClick: () => router.delete(`/admin/subassemblies/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Subassemblies')} />
            <ResourceTable
                shape="subassemblies"
                title={__('Subassemblies')}
                createHref="/admin/subassemblies/create"
                onCreate={drawer.create}
                createLabel={__('New Subassembly')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No subassemblies yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/subassemblies"
                fields={subassemblyFields(productTypes ?? [])}
                initial={subassemblyInitial}
                ensure={['productTypes']}
                ready={productTypes !== undefined}
                title={{ create: __('New Subassembly'), edit: __('Edit Subassembly') }}
            />
        </>
    );
}

SubassembliesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
