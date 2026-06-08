import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';
import { TOOL_STATUS_LABELS } from './fields';

const STATUS_STYLES = {
    available: 'bg-green-100 text-green-800',
    in_use: 'bg-blue-100 text-blue-800',
    maintenance: 'bg-yellow-100 text-yellow-800',
    retired: 'bg-gray-200 text-gray-600',
};

export default function ToolsIndex() {
    const { workstationTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        {
            key: 'type',
            label: 'Workstation Type',
            className: 'text-gray-600',
            render: (r) => workstationTypeNames[r.workstation_type_id] ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            render: (r) => (
                <span className={`text-xs px-2 py-0.5 rounded font-medium ${STATUS_STYLES[r.status] ?? 'bg-gray-100 text-gray-700'}`}>
                    {TOOL_STATUS_LABELS[r.status] ?? r.status ?? '—'}
                </span>
            ),
        },
        { key: 'next_service_at', label: 'Next Service', className: 'text-gray-600', render: (r) => (r.next_service_at ?? '—') },
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
