import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function WorkstationTypesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'workstations', label: 'Workstations', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/workstation-types/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/workstation-types/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
            onClick: () => {
                if (confirm(`Delete workstation type "${r.name}"?`)) {
                    router.delete(`/admin/workstation-types/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Workstation Types" />
            <ResourceTable
                shape="workstation_types"
                title="Workstation Types"
                createHref="/admin/workstation-types/create"
                createLabel="+ New Type"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No workstation types yet."
            />
        </>
    );
}

WorkstationTypesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
