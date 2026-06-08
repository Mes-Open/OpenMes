import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function LinesIndex() {
    const { counts = {}, areaNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'area', label: 'Area', className: 'text-gray-600', render: (r) => areaNames[r.area_id] ?? '—' },
        { key: 'ws', label: 'Stations', render: (r) => counts[r.id]?.workstations ?? 0 },
        { key: 'wo', label: 'Work Orders', render: (r) => counts[r.id]?.work_orders ?? 0 },
        { key: 'ops', label: 'Operators', render: (r) => counts[r.id]?.operators ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Configure', href: `/admin/lines/${r.id}` },
        { label: 'Edit', icon: 'edit', href: `/admin/lines/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/lines/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => { if (confirm(`Delete line "${r.name}"? (only if it has no work orders)`)) router.delete(`/admin/lines/${r.id}`, { preserveScroll: true }); },
        },
    ];

    return (
        <>
            <Head title="Production Lines" />
            <ResourceTable
                shape="lines_all"
                title="Production Lines"
                createHref="/admin/lines/create"
                createLabel="+ New Line"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No production lines yet."
            />
        </>
    );
}

LinesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
