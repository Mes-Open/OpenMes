import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import { useToast } from '@openmes/ui';
import ResourceTable from '../../../components/ResourceTable';
import { ABSENCE_TYPE_LABELS, ABSENCE_STATUS_STYLES } from './fields';

export default function WorkerAbsencesIndex() {
    const toast = useToast();
    const { workerNames = {} } = usePage().props;

    const fmt = (d) => d ?? '—';
    const time = (t) => (t ? String(t).slice(0, 5) : '');

    const columns = [
        {
            key: 'worker',
            label: 'Worker',
            className: 'font-medium text-om-ink',
            value: (r) => workerNames[r.worker_id] ?? `#${r.worker_id}`,
           
            render: (r) => workerNames[r.worker_id] ?? `#${r.worker_id}`,
        },
        { key: 'type', label: 'Type', value: (r) => ABSENCE_TYPE_LABELS[r.type] ?? r.type, render: (r) => ABSENCE_TYPE_LABELS[r.type] ?? r.type },
        {
            key: 'range',
            label: 'Dates',
            value: (r) => (r.starts_on === r.ends_on ? fmt(r.starts_on) : `${fmt(r.starts_on)} → ${fmt(r.ends_on)}`),
            render: (r) => (r.starts_on === r.ends_on ? fmt(r.starts_on) : `${fmt(r.starts_on)} → ${fmt(r.ends_on)}`),
        },
        {
            key: 'span',
            label: 'Span',
            className: 'text-om-muted',
            value: (r) => (r.all_day ? 'All day' : `${time(r.start_time)}–${time(r.end_time)}`),
            render: (r) => (r.all_day ? 'All day' : `${time(r.start_time)}–${time(r.end_time)}`),
        },
        {
            key: 'status',
            label: 'Status',
            value: (r) => r.status,
           
            render: (r) => (
                <span className={`px-2 py-0.5 rounded text-xs ${ABSENCE_STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>
                    {r.status}
                </span>
            ),
        },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/worker-absences/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            confirm: {
                title: 'Delete this absence?',
                confirmLabel: 'Delete absence',
            },
            onClick: () =>
                router.delete(`/admin/worker-absences/${r.id}`, {
                    preserveScroll: true,
                    onError: (e) => toast({ severity: 'error', title: 'Failed to delete.', body: e?.message }),
                }),
        },
    ];

    return (
        <>
            <Head title="Worker Absences" />
            <ResourceTable
                shape="worker_absences"
                title="Worker Absences"
                createHref="/admin/worker-absences/create"
                createLabel="+ New Absence"
                columns={columns}
                orderBy="starts_on"
                actions={actions}
                emptyText="No absences recorded yet."
            />
        </>
    );
}

WorkerAbsencesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
