import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function DivisionsIndex() {
    const { counts = {}, factoryNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'factory', label: 'Factory', className: 'text-gray-600', render: (r) => factoryNames[r.factory_id] ?? '—' },
        { key: 'crews', label: 'Crews', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/divisions/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/divisions/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete division "${r.name}"?`)) {
                    router.delete(`/admin/divisions/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Divisions" />
            <ResourceTable
                shape="divisions"
                title="Divisions"
                createHref="/admin/divisions/create"
                createLabel="+ New Division"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No divisions yet."
            />
        </>
    );
}

DivisionsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
