import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import ResourceFormDrawer, { useResourceDrawer } from '../../../components/ResourceFormDrawer';
import { formatDays, crewBreakWindowFields, crewBreakWindowInitial } from './fields';
import { useToast } from '@openmes/ui';
import { __ } from '../../../lib/i18n';

export default function CrewBreakWindowsIndex() {
    const toast = useToast();
    const { crewNames = {}, crews } = usePage().props;

    const drawer = useResourceDrawer();

    const time = (t) => (t ? String(t).slice(0, 5) : '');

    const columns = [
        {
            key: 'crew',
            label: 'Crew',
            className: 'font-medium text-om-ink',
            value: (r) => crewNames[r.crew_id] ?? `#${r.crew_id}`,
           
            render: (r) => crewNames[r.crew_id] ?? `#${r.crew_id}`,
        },
        { key: 'name', label: 'Name', filter: 'text' },
        {
            key: 'time',
            label: 'Time',
            value: (r) => `${time(r.start_time)}–${time(r.end_time)}`,
            render: (r) => `${time(r.start_time)}–${time(r.end_time)}`,
        },
        {
            key: 'days',
            label: 'Days',
            className: 'text-om-muted',
            value: (r) => formatDays(r.days_of_week ?? []),
            render: (r) => formatDays(r.days_of_week ?? []),
        },
        {
            key: 'is_active',
            label: 'Status',
            value: (r) => __(r.is_active ? 'Active' : 'Inactive'),
           
            render: (r) => (
                <span className={`px-2 py-0.5 rounded text-xs ${r.is_active ? 'bg-om-running-bg text-om-running' : 'bg-om-chip text-om-muted'}`}>
                    {r.is_active ? __('Active') : __('Inactive')}
                </span>
            ),
        },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', onClick: () => drawer.edit(r) },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: __('Delete this break window?'),
                confirmLabel: __('Delete'),
            },
            onClick: () => {
                router.delete(`/admin/crew-break-windows/${r.id}`, {
                    preserveScroll: true,
                    onError: (e) => toast({ severity: 'error', title: __('Failed to delete.'), body: e?.message }),
                });
            },
        },
    ];

    return (
        <>
            <Head title={__('Crew Break Windows')} />
            <ResourceTable
                shape="crew_break_windows"
                title={__('Crew Break Windows')}
                createHref="/admin/crew-break-windows/create"
                onCreate={drawer.create}
                createLabel={__('New Break Window')}
                columns={columns}
                orderBy="start_time"
                actions={actions}
                emptyText={__('No break windows defined yet.')}
            />

            <ResourceFormDrawer
                {...drawer.props}
                action="/admin/crew-break-windows"
                fields={crewBreakWindowFields(crews ?? [])}
                initial={crewBreakWindowInitial}
                ensure={['crews']}
                ready={crews !== undefined}
                title={{ create: __('New Break Window'), edit: __('Edit Break Window') }}
            />
        </>
    );
}

CrewBreakWindowsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
