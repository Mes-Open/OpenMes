import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { default as ResourceTable } from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { EVENT_STATUS_STYLES, maintenanceEventFields, maintenanceEventInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function MaintenanceEventsIndex() {
    const {
        toolNames = {},
        lineNames = {},
        workstationNames = {},
        userNames = {},
        // No defaults: these are Inertia::optional, and `ready` below tells the
        // drawer they haven't arrived by their being undefined.
        tools, lines, workstations, costSources, users } = usePage().props;

    const drawer = useResourceDrawer();

    const columns = [
        { key: 'title', label: __('Title'), className: 'font-medium text-om-ink', filter: 'text' },
        { key: 'event_type', label: __('Type'), className: 'text-om-muted' },
        {
            key: 'target',
            label: __('Target'),
            className: 'text-om-muted',
           
            value: (r) =>
                toolNames[r.tool_id] ??
                lineNames[r.line_id] ??
                workstationNames[r.workstation_id] ??
                '—',
            render: (r) =>
                toolNames[r.tool_id] ??
                lineNames[r.line_id] ??
                workstationNames[r.workstation_id] ??
                '—',
        },
        {
            key: 'assigned',
            label: __('Assigned'),
            className: 'text-om-muted',
            value: (r) => userNames[r.assigned_to_id] ?? '—',
            render: (r) => userNames[r.assigned_to_id] ?? '—',
        },
        {
            key: 'scheduled_at', filter: 'date',
            label: __('Scheduled'),
            className: 'text-om-muted',
            render: (r) => (r.scheduled_at ? r.scheduled_at.slice(0, 16).replace('T', ' ') : '—'),
        },
        {
            key: 'status',
            label: __('Status'),
           
            value: (r) => r.status,
            render: (r) => (
                <span
                    className={`text-xs px-2 py-0.5 rounded font-medium ${
                        EVENT_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'
                    }`}
                >
                    {{
                        pending: __('Pending'),
                        planned: __('Planned'),
                        in_progress: __('In Progress'),
                        completed: __('Completed'),
                        done: __('Completed'),
                        cancelled: __('Cancelled'),
                    }[r.status] ?? r.status}
                </span>
            ),
        },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete maintenance event ":name"?', { name: r.title }),
                confirmLabel: __('Delete'),
            },
            onClick: () => router.delete(`/admin/maintenance-events/${r.id}`, { preserveScroll: true }),
        },
    ];

    return (
        <>
            <Head title={__('Maintenance Events')} />
            <ResourceTable
                shape="maintenance_events"
                title={__('Maintenance Events')}
                createHref="/admin/maintenance-events/create"
                onCreate={drawer.create}
                createLabel={__('New Event')}
                columns={columns}
                orderBy="scheduled_at"
                orderDir="desc"
                actions={actions}
                emptyText={__('No maintenance events yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/maintenance-events"
                fields={maintenanceEventFields({ tools: tools ?? [], lines: lines ?? [], workstations: workstations ?? [], costSources: costSources ?? [], users: users ?? [] })}
                initial={maintenanceEventInitial}
                ensure={['tools', 'lines', 'workstations', 'costSources', 'users']}
                ready={tools !== undefined}
                title={{ create: __('New Maintenance Event'), edit: __('Edit Maintenance Event') }}
            />
        </>
    );
}

MaintenanceEventsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
