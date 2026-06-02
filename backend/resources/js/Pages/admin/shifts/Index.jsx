import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function ShiftsIndex() {
    const { lineNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'line', label: 'Line', className: 'text-gray-600', render: (r) => (r.line_id ? lineNames[r.line_id] ?? `#${r.line_id}` : 'Global') },
        { key: 'start_time', label: 'Start', className: 'text-gray-600', render: (r) => (r.start_time ?? '').slice(0, 5) },
        { key: 'end_time', label: 'End', className: 'text-gray-600', render: (r) => (r.end_time ?? '').slice(0, 5) },
        { key: 'sort_order', label: 'Order', className: 'text-gray-600' },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/shifts/${r.id}/edit` },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete shift "${r.name}"?`)) {
                    router.delete(`/admin/shifts/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Shifts" />
            <ResourceTable
                shape="shifts"
                title="Shifts"
                createHref="/admin/shifts/create"
                createLabel="+ New Shift"
                columns={columns}
                orderBy="sort_order"
                actions={actions}
                emptyText="No shifts yet."
            />
        </>
    );
}

ShiftsIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
