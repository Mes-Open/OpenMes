import { Head, router } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable, { ActiveBadge } from '../../../components/ResourceTable';

export default function CompaniesIndex() {
    const columns = [
        { key: 'code', label: 'Code', className: 'font-mono text-gray-700' },
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'type', label: 'Type', className: 'text-gray-600' },
        { key: 'email', label: 'Email', className: 'text-gray-600' },
        { key: 'is_active', label: 'Status', render: (r) => <ActiveBadge active={r.is_active} /> },
    ];

    const actions = (r) => [
        { label: 'Edit', href: `/admin/companies/${r.id}/edit` },
        {
            label: r.is_active ? 'Deactivate' : 'Activate',
            onClick: () => router.post(`/admin/companies/${r.id}/toggle-active`, {}, { preserveScroll: true }),
        },
        {
            label: 'Delete',
            className: 'text-red-600 hover:underline',
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
