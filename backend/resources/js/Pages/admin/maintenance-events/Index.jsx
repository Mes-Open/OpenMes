import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { default as ResourceTable } from '../../../components/ResourceTable';
import { EVENT_STATUS_STYLES } from './fields';

export default function MaintenanceEventsIndex() {
    const {
        toolNames = {},
        lineNames = {},
        workstationNames = {},
        userNames = {},
    } = usePage().props;

    const columns = [
        { key: 'title', label: 'Title', className: 'font-medium text-om-ink' },
        { key: 'event_type', label: 'Type', className: 'text-om-muted' },
        {
            key: 'target',
            label: 'Target',
            className: 'text-om-muted',
            render: (r) =>
                toolNames[r.tool_id] ??
                lineNames[r.line_id] ??
                workstationNames[r.workstation_id] ??
                '—',
        },
        {
            key: 'assigned',
            label: 'Assigned',
            className: 'text-om-muted',
            render: (r) => userNames[r.assigned_to_id] ?? '—',
        },
        {
            key: 'scheduled_at',
            label: 'Scheduled',
            className: 'text-om-muted',
            render: (r) => (r.scheduled_at ? r.scheduled_at.slice(0, 16).replace('T', ' ') : '—'),
        },
        {
            key: 'status',
            label: 'Status',
            render: (r) => (
                <span
                    className={`text-xs px-2 py-0.5 rounded font-medium ${
                        EVENT_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'
                    }`}
                >
                    {r.status}
                </span>
            ),
        },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/maintenance-events/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete maintenance event "${r.title}"?`)) {
                    router.delete(`/admin/maintenance-events/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Maintenance Events" />
            <ResourceTable
                shape="maintenance_events"
                title="Maintenance Events"
                createHref="/admin/maintenance-events/create"
                createLabel="+ New Event"
                columns={columns}
                orderBy="scheduled_at"
                orderDir="desc"
                actions={actions}
                emptyText="No maintenance events yet."
            />
        </>
    );
}

MaintenanceEventsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
