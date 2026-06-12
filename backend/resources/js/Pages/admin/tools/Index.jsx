import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { TOOL_STATUS_LABELS } from './fields';

const STATUS_STYLES = {
    available: 'bg-om-running-bg text-om-running',
    in_use: 'bg-om-chip text-om-accent',
    maintenance: 'bg-om-downtime-bg text-om-downtime',
    retired: 'bg-om-line2 text-om-muted',
};

export default function ToolsIndex() {
    const { workstationTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-om-muted' },
        { key: 'name', label: 'Name', className: 'font-medium text-om-ink' },
        {
            key: 'type',
            label: 'Workstation Type',
            className: 'text-om-muted',
            render: (r) => workstationTypeNames[r.workstation_type_id] ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-om-chip text-om-muted'}`}>
                    {TOOL_STATUS_LABELS[r.status] ?? r.status ?? '—'}
                </span>
            ),
        },
        { key: 'next_service_at', label: 'Next Service', className: 'text-om-muted', render: (r) => (r.next_service_at ?? '—') },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/tools/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete tool "${r.name}"?`)) {
                    router.delete(`/admin/tools/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Tools" />
            <ResourceTable
                shape="tools"
                title="Tools"
                createHref="/admin/tools/create"
                createLabel="+ New Tool"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No tools yet."
            />
        </>
    );
}

ToolsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
