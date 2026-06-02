import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function FactoriesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'divisions', label: 'Divisions', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/factories/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/factories/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete factory "${r.name}"?`)) {
                    router.delete(`/admin/factories/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Factories" />
            <ResourceTable
                shape="factories"
                title="Factories"
                createHref="/admin/factories/create"
                createLabel="+ New Factory"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No factories yet."
            />
        </>
    );
}

FactoriesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
