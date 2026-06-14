import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';
import { __ } from '../../../lib/i18n';

export default function ProcessSegmentsIndex() {
    const { workstationTypeNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: __('Code'), className: 'font-mono text-gray-700' },
        { key: 'name', label: __('Name'), className: 'font-medium text-gray-800' },
        { key: 'segment_type', label: __('Type'), className: 'text-gray-600', render: (r) => r.segment_type },
        { key: 'wstype', label: __('Workstation Type'), className: 'text-gray-600', render: (r) => workstationTypeNames[r.workstation_type_id] ?? '—' },
        { key: 'required_operators', label: __('Operators'), className: 'text-gray-600' },
        { key: 'is_active', label: __('Status'), render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: __('Edit'), icon: 'edit', href: `/admin/process-segments/${r.id}/edit` },
        {
            label: __('Delete'),
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(__('Delete process segment ":name"?', { name: r.name }))) {
                    router.delete(`/admin/process-segments/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title={__('Process Segments')} />
            <ResourceTable
                shape="process_segments"
                title={__('Process Segments')}
                createHref="/admin/process-segments/create"
                createLabel={__('+ New Segment')}
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText={__('No process segments yet.')}
            />
        </>
    );
}

ProcessSegmentsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
