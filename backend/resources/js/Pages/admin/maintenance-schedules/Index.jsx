import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function MaintenanceSchedulesIndex() {
    const { toolNames = {}, lineNames = {}, workstationNames = {} } = usePage().props;

    const columns = [
        { key: 'name', label: __('Name'), className: 'font-medium text-om-ink', filter: 'text' },
        {
            key: 'target',
            label: __('Target'),
            className: 'text-om-muted',
           
            value: (r) => toolNames[r.tool_id] ?? lineNames[r.line_id] ?? workstationNames[r.workstation_id] ?? '—',
            render: (r) => toolNames[r.tool_id] ?? lineNames[r.line_id] ?? workstationNames[r.workstation_id] ?? '—',
        },
        { key: 'frequency', label: __('Frequency'), className: 'text-om-muted' },
        { key: 'interval_value', label: __('Every'), className: 'text-om-muted' },
        {
            key: 'next_due_at', filter: 'date',
            label: __('Next Due'),
            className: 'text-om-muted',
            render: (r) => (r.next_due_at ? r.next_due_at.slice(0, 16).replace('T', ' ') : '—'),
        },
        { key: 'is_active', label: __('Status'), value: (r) => __(r.is_active ? 'Active' : 'Inactive'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/maintenance-schedules/${r.id}/edit` },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete maintenance schedule ":name"?', { name: r.name }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/maintenance-schedules/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Maintenance Schedules')} />
            <ResourceTable
                shape="maintenance_schedules"
                title={__('Maintenance Schedules')}
                createHref="/admin/maintenance-schedules/create"
                createLabel={__('+ New Schedule')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No maintenance schedules yet.')}
            />
        </>
    );
}

MaintenanceSchedulesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
