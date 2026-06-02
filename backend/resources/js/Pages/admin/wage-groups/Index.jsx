import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function WageGroupsIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'rate', label: 'Base Rate', render: (r) => `${r.base_hourly_rate ?? '—'} ${r.currency ?? ''}`.trim() },
        { key: 'workers', label: 'Workers', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/wage-groups/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/wage-groups/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete wage group "${r.name}"?`)) {
                    router.delete(`/admin/wage-groups/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Wage Groups" />
            <ResourceTable
                shape="wage_groups"
                title="Wage Groups"
                createHref="/admin/wage-groups/create"
                createLabel="+ New Wage Group"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No wage groups yet."
            />
        </>
    );
}

WageGroupsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
