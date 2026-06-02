import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function ProcessSegmentsIndex() {
    const { workstationTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'segment_type', label: 'Type', className: 'text-gray-600', render: (r) => r.segment_type },
        { key: 'wstype', label: 'Workstation Type', className: 'text-gray-600', render: (r) => workstationTypeNames[r.workstation_type_id] ?? '—' },
        { key: 'required_operators', label: 'Operators', className: 'text-gray-600' },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/process-segments/${r.id}/edit` },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete process segment "${r.name}"?`)) {
                    router.delete(`/admin/process-segments/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Process Segments" />
            <ResourceTable
                shape="process_segments"
                title="Process Segments"
                createHref="/admin/process-segments/create"
                createLabel="+ New Segment"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No process segments yet."
            />
        </>
    );
}

ProcessSegmentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
