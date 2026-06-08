import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function CrewsIndex() {
    const { counts = {}, divisionNames = {}, leaderNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'division', label: 'Division', className: 'text-gray-600', render: (r) => divisionNames[r.division_id] ?? '—' },
        { key: 'leader', label: 'Leader', className: 'text-gray-600', render: (r) => leaderNames[r.leader_id] ?? '—' },
        { key: 'workers', label: 'Workers', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/crews/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/crews/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete crew "${r.name}"?`)) {
                    router.delete(`/admin/crews/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Crews" />
            <ResourceTable
                shape="crews"
                title="Crews"
                createHref="/admin/crews/create"
                createLabel="+ New Crew"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No crews yet."
            />
        </>
    );
}

CrewsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
