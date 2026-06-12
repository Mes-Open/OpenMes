import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function SitesIndex() {
    const { counts = {}, companyNames = {} } = usePage().props;

    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-om-muted' },
        { key: 'name', label: 'Name', className: 'font-medium text-om-ink' },
        { key: 'company', label: 'Company', className: 'text-om-muted', render: (r) => companyNames[r.company_id] ?? '—' },
        { key: 'city', label: 'City', className: 'text-om-muted' },
        { key: 'areas', label: 'Areas', render: (r) => counts[r.id]?.areas ?? 0 },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/sites/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/sites/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete site "${r.name}"?`)) {
                    router.delete(`/admin/sites/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Sites" />
            <ResourceTable
                shape="sites"
                title="Sites"
                createHref="/admin/sites/create"
                createLabel="+ New Site"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No sites yet."
            />
        </>
    );
}

SitesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
