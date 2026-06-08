import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceTable from '../../../components/ResourceTable';

export default function ViewTemplatesIndex() {
    const { counts = {} } = usePage().props;

    const columns = [
        { key: 'name', label: 'Name', className: 'font-medium text-gray-800' },
        { key: 'description', label: 'Description', className: 'text-gray-600' },
        { key: 'lines', label: 'Lines using', render: (r) => counts[r.id] ?? 0 },
    ];

    const actions = (r) => [
        { label: 'Edit', icon: 'edit', href: `/admin/view-templates/${r.id}/edit` },
        {
            label: 'Delete',
            icon: 'delete',
            variant: 'danger',
            onClick: () => { if (confirm(`Delete view template "${r.name}"?`)) router.delete(`/admin/view-templates/${r.id}`, { preserveScroll: true }); },
        },
    ];

    return (
        <>
            <Head title="View Templates" />
            <ResourceTable
                shape="view_templates"
                title="View Templates"
                createHref="/admin/view-templates/create"
                createLabel="+ New Template"
                columns={columns}
                orderBy="name"
                actions={actions}
                emptyText="No view templates yet."
            />
        </>
    );
}

ViewTemplatesIndex.layout = (page) => <AppLayout>{page}</AppLayout>;
