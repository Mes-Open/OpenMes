import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { formatDays } from './fields';
import { __ } from '../../../lib/i18n';

export default function CrewBreakWindowsIndex() {
    const { crewNames = {} } = usePage().props;

    const time = (t) => (t ? String(t).slice(0, 5) : '');

    const columns = [
        {
            key: 'crew',
            label: 'Crew',
            className: 'font-medium text-gray-800',
            render: (r) => crewNames[r.crew_id] ?? `#${r.crew_id}`,
        },
        { key: 'name', label: 'Name' },
        {
            key: 'time',
            label: 'Time',
            render: (r) => `${time(r.start_time)}–${time(r.end_time)}`,
        },
        {
            key: 'days',
            label: 'Days',
            className: 'text-gray-600',
            render: (r) => formatDays(r.days_of_week ?? []),
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (r) => (
                <span className={`px-2 py-0.5 rounded text-xs ${r.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                    {r.is_active ? __('Active') : __('Inactive')}
                </span>
            ),
        },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/crew-break-windows/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete this break window?'))) {
                    router.delete(`/admin/crew-break-windows/${r.id}`, {
                        preserveScroll: true,
                        onError: (e) => alert(e?.message || __('Failed to delete.')),
                    });
                }
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
                createLabel={__('+ New Break Window')}
                columns={columns}
                orderBy="start_time"
                actions={actions}
                emptyText={__('No break windows defined yet.')}
            />
        </>
    );
}

CrewBreakWindowsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
