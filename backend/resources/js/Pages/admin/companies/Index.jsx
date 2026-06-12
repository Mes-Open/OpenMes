import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function CompaniesIndex() {
    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-om-muted' },
        { key: 'name', label: 'Name', className: 'font-medium text-om-ink' },
        { key: 'type', label: 'Type', className: 'text-om-muted' },
        { key: 'email', label: 'Email', className: 'text-om-muted' },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/companies/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            icon: r.is_active ? 'deactivate' : 'activate',
            onClick: () => router.post(`/admin/companies/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => {
                if (confirm(`Delete company "${r.name}"?`)) {
                    router.delete(`/admin/companies/${r.id}`, { preserveScroll: true });
                }
            },
        },
    ];

    return (
        <>
            <Head title="Companies" />
            <ResourceTable
                shape="companies"
                title="Companies"
                createHref="/admin/companies/create"
                createLabel="+ New Company"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No companies yet."
            />
        </>
    );
}

CompaniesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
