import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function SubassembliesIndex() {
    const { productTypeNames = {}, counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-om-muted' },
        { key: 'name', label: 'Name', className: 'font-medium text-om-ink' },
        { key: 'product_type', label: 'Product Type', className: 'text-om-muted', render: (r) => productTypeNames[r.product_type_id] ?? '—' },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/subassemblies/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/subassemblies/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete subassembly "${r.name}"?`)) {
                    router.delete(`/admin/subassemblies/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Subassemblies" />
            <ResourceTable
                shape="subassemblies"
                title="Subassemblies"
                createHref="/admin/subassemblies/create"
                createLabel="+ New Subassembly"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No subassemblies yet."
            />
        </>
    );
}

SubassembliesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
