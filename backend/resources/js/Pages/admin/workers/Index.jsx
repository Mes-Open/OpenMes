import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function WorkersIndex() {
    const { crewNames = {}, wageGroupNames = {}, personnelClassNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'email', label: 'Email', className: 'text-gray-600' },
        { key: 'crew', label: 'Crew', className: 'text-gray-600', render: (r) => crewNames[r.crew_id] ?? '—' },
        { key: 'class', label: 'Class', className: 'text-gray-600', render: (r) => personnelClassNames[r.personnel_class_id] ?? '—' },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/workers/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/workers/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete worker "${r.name}"?`)) {
                    router.delete(`/admin/workers/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Workers" />
            <ResourceTable
                shape="workers"
                title="Workers"
                createHref="/admin/workers/create"
                createLabel="+ New Worker"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No workers yet."
            />
        </>
    );
}

WorkersIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
