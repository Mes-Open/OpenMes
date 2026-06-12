import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function ShiftsIndex() {
    const { lineNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-om-muted' },
        { key: 'name', label: 'Name', className: 'font-medium text-om-ink' },
        { key: 'line', label: 'Line', className: 'text-om-muted', render: (r) => (r.line_id ? lineNames[r.line_id] ?? `#${r.line_id}` : 'Global') },
        { key: 'start_time', label: 'Start', className: 'text-om-muted', render: (r) => (r.start_time ?? '').slice(0, 5) },
        { key: 'end_time', label: 'End', className: 'text-om-muted', render: (r) => (r.end_time ?? '').slice(0, 5) },
        { key: 'sort_order', label: 'Order', className: 'text-om-muted' },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/shifts/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
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
