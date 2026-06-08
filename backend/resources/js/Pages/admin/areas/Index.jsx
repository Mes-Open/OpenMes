import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function AreasIndex() {
    const { counts = {}, siteNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'site', label: 'Site', className: 'text-gray-600', render: (r) => siteNames[r.site_id] ?? '—' },
        { key: 'lines', label: 'Lines', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/areas/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/areas/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete area "${r.name}"?`)) {
                    router.delete(`/admin/areas/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Areas" />
            <ResourceTable
                shape="areas"
                title="Areas"
                createHref="/admin/areas/create"
                createLabel="+ New Area"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No areas yet."
            />
        </>
    );
}

AreasIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
