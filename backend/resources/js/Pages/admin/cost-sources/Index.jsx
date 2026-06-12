import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function CostSourcesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-om-muted' },
        { key: 'name', label: 'Name', className: 'font-medium text-om-ink' },
        { key: 'unit_cost', label: 'Unit Cost', render: (r) => `${r.unit_cost ?? '—'} ${r.currency ?? ''}`.trim() },
        { key: 'unit', label: 'Unit', className: 'text-om-muted' },
        { key: 'used', label: 'Used', render: (r) => counts[r.id] ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/cost-sources/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/cost-sources/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete cost source "${r.name}"?`)) {
                    router.delete(`/admin/cost-sources/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Cost Sources" />
            <ResourceTable
                shape="cost_sources"
                title="Cost Sources"
                createHref="/admin/cost-sources/create"
                createLabel="+ New Cost Source"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No cost sources yet."
            />
        </>
    );
}

CostSourcesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
