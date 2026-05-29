import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function MaintenanceSchedulesIndex() {
    const { toolNames = {}, lineNames = {}, workstationNames = {} } = usePage().props;

    const columns = [
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        {
            key: 'target',
            label: 'Target',
            className: 'text-gray-600',
            render: (r) => toolNames[r.tool_id] ?? lineNames[r.line_id] ?? workstationNames[r.workstation_id] ?? '—',
        },
        { key: 'frequency', label: 'Frequency', className: 'text-gray-600' },
        { key: 'interval_value', label: 'Every', className: 'text-gray-600' },
        {
            key: 'next_due_at',
            label: 'Next Due',
            className: 'text-gray-500',
            render: (r) => (r.next_due_at ? r.next_due_at.slice(0, 16).replace('T', ' ') : '—'),
        },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/maintenance-schedules/${r.id}/edit` },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete maintenance schedule "${r.name}"?`)) {
                    router.delete(`/admin/maintenance-schedules/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Maintenance Schedules" />
            <ResourceTable
                shape="maintenance_schedules"
                title="Maintenance Schedules"
                createHref="/admin/maintenance-schedules/create"
                createLabel="+ New Schedule"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No maintenance schedules yet."
            />
        </>
    );
}

MaintenanceSchedulesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
